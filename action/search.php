<?php

/**
 * DokuWiki Plugin elasticsearch (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Form\Form;
use Elastica\Aggregation\Terms;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\Range;
use Elastica\Query\SimpleQueryString;
use Elastica\Query\Term;
use Elastica\ResultSet;

/**
 * Main search helper
 */
class action_plugin_elasticsearch_search extends ActionPlugin
{
    /**
     * Example array element for search field 'tagging':
     * 'tagging' => [                       // also used as search query parameter
     *   'label' => 'Tag',
     *   'fieldPath' => 'tagging',          // dot notation in more complex mappings
     *   'limit' => '50',
     * ]
     *
     * @var Array
     */
    protected static $pluginSearchConfigs;

    /**
     * Search will be performed on those fields only.
     *
     * @var string[]
     */
    protected $searchFields = [
        'title*',
        'abstract*',
        'content*',
        'uri',
    ];

    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {

        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleActPreprocess');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'handleActUnknown');
        $controller->register_hook('FORM_QUICKSEARCH_OUTPUT', 'BEFORE', $this, 'handleQuicksearchOutput');
    }

    /**
     * allow our custom do command
     *
     * @param Event $event
     * @param $param
     */
    public function handleActPreprocess(Event $event, $param)
    {
        if ($event->data !== 'search') return;
        $event->preventDefault();
        $event->stopPropagation();
    }

    /**
     * do the actual search
     *
     * @param Event $event
     * @param $param
     */
    public function handleActUnknown(Event $event, $param)
    {
        if ($event->data !== 'search') return;
        $event->preventDefault();
        $event->stopPropagation();
        global $QUERY;
        global $INPUT;
        global $ID;

        if (empty($QUERY)) $QUERY = $INPUT->str('q');
        if (empty($QUERY)) $QUERY = $ID;

        // get extended search configurations from plugins
        Event::createAndTrigger('PLUGIN_ELASTICSEARCH_FILTERS', self::$pluginSearchConfigs);

        /** @var helper_plugin_elasticsearch_client $hlp */
        $hlp = plugin_load('helper', 'elasticsearch_client');

        $client = $hlp->connect();
        $index = $client->getIndex($this->getConf('indexname'));

        // store copy of the original query string
        $q = $QUERY;
        // let plugins manipulate the query
        $additions = [];
        Event::createAndTrigger('PLUGIN_ELASTICSEARCH_QUERY', $additions);
        // if query is empty, return all results
        if (empty(trim($QUERY))) $QUERY = '*';

        // get fields to use in query
        $fields = [];
        Event::createAndTrigger('PLUGIN_ELASTICSEARCH_SEARCHFIELDS', $fields);

        if ($this->getConf('searchSyntax')) {
            $this->searchFields[] = 'syntax*';
        }

        // finally define the elastic query
        $qstring = new SimpleQueryString($QUERY, array_merge($this->searchFields, $fields));
        // restore the original query
        $QUERY = $q;
        // append additions provided by plugins
        if (!empty($additions)) {
            $QUERY .= ' ' . implode(' ', $additions);
        }

        // create the actual search object
        $equery = new Query();
        $subqueries = new BoolQuery();
        $subqueries->addMust($qstring);

        $equery->setHighlight(
            [
                "pre_tags" => ['ELASTICSEARCH_MARKER_IN'],
                "post_tags" => ['ELASTICSEARCH_MARKER_OUT'],
                "fields" => [
                    $this->getConf('snippets') => new \stdClass(),
                    'title' => new \stdClass()]
            ]
        );

        // paginate
        $equery->setSize($this->getConf('perpage'));
        $equery->setFrom($this->getConf('perpage') * ($INPUT->int('p', 1, true) - 1));

        // add ACL subqueries
        $this->addACLSubqueries($subqueries);

        // add language subquery
        $this->addLanguageSubquery($subqueries, $this->getLanguageFilter());

        // add date subquery
        if ($INPUT->has('min')) {
            $this->addDateSubquery($subqueries, $INPUT->str('min'));
        }

        // add namespace filter
        if ($INPUT->has('ns')) {
            $nsSubquery = new BoolQuery();
            foreach ($INPUT->arr('ns') as $ns) {
                $term = new Term();
                $term->setTerm('namespace', $ns);
                $nsSubquery->addShould($term);
            }
            $equery->setPostFilter($nsSubquery);
        }


        // add aggregations for namespaces
        $agg = new Terms('namespace');
        $agg->setField('namespace.keyword');
        $agg->setSize(25);

        $equery->addAggregation($agg);

        // add search configurations from other plugins
        $this->addPluginConfigurations($equery, $subqueries);

        $equery->setQuery($subqueries);

        try {
            $result = $index->search($equery);
            $aggs = $result->getAggregations();

            $this->printIntro();
            /** @var helper_plugin_elasticsearch_form $hlpform */
            $hlpform = plugin_load('helper', 'elasticsearch_form');
            $hlpform->tpl($aggs);
            if ($this->printResults($result)) {
                $this->printPagination($result);
            }
        } catch (Exception $e) {
            msg('Something went wrong on searching please try again later or ask an admin for help.<br /><pre>' .
                hsc($e->getMessage()) . '</pre>', -1);
        }
    }

    /**
     * Optionally disable "quick search"
     *
     * @param Event $event
     */
    public function handleQuicksearchOutput(Event $event)
    {
        if (!$this->getConf('disableQuicksearch')) return;

        /** @var Form $form */
        $form = $event->data;
        $pos = $form->findPositionByAttribute('id', 'qsearch__out');
        $form->removeElement($pos);
        $form->removeElement($pos + 1); // div closing tag
    }

    /**
     * @return array
     */
    public static function getRawPluginSearchConfigs()
    {
        return self::$pluginSearchConfigs;
    }

    /**
     * Add search configurations supplied by other plugins
     *
     * @param Query $equery
     * @param \Elastica\Query\BoolQuery
     */
    protected function addPluginConfigurations($equery, $subqueries)
    {
        global $INPUT;

        if (!empty(self::$pluginSearchConfigs)) {
            foreach (self::$pluginSearchConfigs as $param => $config) {
                // handle search parameter
                if ($INPUT->has($param)) {
                    $pluginSubquery = new BoolQuery();
                    foreach ($INPUT->arr($param) as $item) {
                        $eterm = new Term();
                        $eterm->setTerm($param, $item);
                        $pluginSubquery->addShould($eterm);
                    }
                    $subqueries->addMust($pluginSubquery);
                }

                // build aggregation for use as filter in advanced search
                $agg = new Terms($param);
                $agg->setField($config['fieldPath']);
                if (isset($config['limit'])) {
                    $agg->setSize($config['limit']);
                }
                $equery->addAggregation($agg);
            }
        }
    }

    /**
     * Adds date subquery
     *
     * @param BoolQuery $subqueries
     * @param string $min Modified at the latest one {year|month|week} ago
     */
    protected function addDateSubquery($subqueries, $min)
    {
        if (!in_array($min, ['year', 'month', 'week'])) return;

        $dateSubquery = new Range(
            'modified',
            ['gte' => date('Y-m-d', strtotime('1 ' . $min . ' ago'))]
        );
        $subqueries->addMust($dateSubquery);
    }

    /**
     * Adds language subquery
     *
     * @param BoolQuery $subqueries
     * @param array $langFilter
     */
    protected function addLanguageSubquery($subqueries, $langFilter)
    {
        if (empty($langFilter)) return;

        $langSubquery = new MatchQuery();
        $langSubquery->setField('language', implode(',', $langFilter));

        $subqueries->addMust($langSubquery);
    }

    /**
     * Languages to be used in the current search, determined by:
     * 1. $INPUT variables, or 2. translation plugin
     *
     * @return array
     */
    protected function getLanguageFilter()
    {
        global $ID;
        global $INPUT;

        $ns = getNS($ID);
        $langFilter = $INPUT->arr('lang');

        /** @var helper_plugin_translation $transplugin */
        $transplugin = plugin_load('helper', 'translation');

        // optional translation detection: use current top namespace if it matches translation config
        if (empty($langFilter) && $transplugin && $this->getConf('detectTranslation') && $ns) {
            $topNs = strtok($ns, ':');
            if (in_array($topNs, $transplugin->translations)) {
                $langFilter = [$topNs];
                $INPUT->set('lang', $langFilter);
            }
        } elseif (empty($langFilter) && $transplugin) {
            // select all available translations
            $INPUT->set('lang', $transplugin->translations);
        }

        return $langFilter;
    }

    /**
     * Inserts subqueries based on current user's ACLs, none for superusers
     *
     * @param BoolQuery $subqueries
     */
    protected function addACLSubqueries($subqueries)
    {
        global $USERINFO;
        global $INFO;

        $groups = array_merge(['ALL'], $USERINFO['grps'] ?: []);

        // no ACL filters for superusers
        if ($INFO['isadmin']) return;

        // include if group OR user have read permissions, allows for ACLs such as "block @group except user"
        $includeSubquery = new BoolQuery();
        foreach ($groups as $group) {
            $term = new Term();
            $term->setTerm('groups_include', $group);
            $includeSubquery->addShould($term);
        }
        if (isset($_SERVER['REMOTE_USER'])) {
            $userIncludeSubquery = new BoolQuery();
            $term = new Term();
            $term->setTerm('users_include', $_SERVER['REMOTE_USER']);
            $userIncludeSubquery->addMust($term);
            $includeSubquery->addShould($userIncludeSubquery);
        }
        $subqueries->addMust($includeSubquery);

        // groups exclusion SHOULD be respected, not MUST, since that would not allow for exceptions
        $groupExcludeSubquery = new BoolQuery();
        foreach ($groups as $group) {
            $term = new Term();
            $term->setTerm('groups_exclude', $group);
            $groupExcludeSubquery->addShould($term);
        }
        $excludeSubquery = new BoolQuery();
        $excludeSubquery->addMustNot($groupExcludeSubquery);

        $subqueries->addShould($excludeSubquery);

        // user specific excludes must always be respected
        if (isset($_SERVER['REMOTE_USER'])) {
            $term = new Term();
            $term->setTerm('users_exclude', $_SERVER['REMOTE_USER']);
            $subqueries->addMustNot($term);
        }
    }

    /**
     * Prints the introduction text
     */
    protected function printIntro()
    {
        global $QUERY;
        global $ID;
        global $lang;

        // just reuse the standard search page intro:
        $intro = p_locale_xhtml('searchpage');
        // allow use of placeholder in search intro
        $pagecreateinfo = '';
        if (auth_quickaclcheck($ID) >= AUTH_CREATE) {
            $pagecreateinfo = sprintf($lang['searchcreatepage'], $QUERY);
        }
        $intro = str_replace(
            ['@QUERY@', '@SEARCH@', '@CREATEPAGEINFO@'],
            [hsc(rawurlencode($QUERY)), hsc($QUERY), $pagecreateinfo],
            $intro
        );
        echo $intro;
        flush();
    }

    /**
     * Output the search results
     *
     * @param ResultSet $results
     * @return bool true when results where shown
     */
    protected function printResults($results)
    {
        global $lang;

        // output results
        $found = $results->getTotalHits();

        if (!$found) {
            echo '<h2>' . $lang['nothingfound'] . '</h2>';
            return (bool)$found;
        }

        echo '<dl class="search_results">';
        echo '<h2>' . sprintf($this->getLang('totalfound'), $found) . '</h2>';
        foreach ($results as $row) {

            /** @var Elastica\Result $row */
            $doc = $row->getSource();
            $page = $doc['uri'];
            if (
                (!page_exists($page) && !is_file(mediaFN($page))) ||
                isHiddenPage($page) ||
                auth_quickaclcheck($page) < AUTH_READ
            ) {
                continue;
            }

            // get highlighted title
            $highlightsTitle = $row->getHighlights()['title'] ?? '';
            $title = str_replace(
                ['ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'],
                ['<strong class="search_hit">', '</strong>'],
                hsc(implode(' … ', (array)$highlightsTitle))
            );
            if (!$title) $title = hsc($doc['title']);
            if (!$title) $title = hsc(p_get_first_heading($page));
            if (!$title) $title = hsc($page);

            // get highlighted snippet
            $highlightedSnippets = $row->getHighlights()[$this->getConf('snippets')] ?? [];
            $snippet = str_replace(
                ['ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'],
                ['<strong class="search_hit">', '</strong>'],
                hsc(implode(' … ', $highlightedSnippets))
            );
            if (!$snippet) $snippet = hsc($doc['abstract']); // always fall back to abstract

            // assume page if no doctype is set, because old index won't have doctypes
            $isPage = empty($doc['doctype']) || $doc['doctype'] === \action_plugin_elasticsearch_indexing::DOCTYPE_PAGE;
            $href = $isPage ? wl($page) : ml($page);

            echo '<dt>';
            if (!$isPage && is_file(DOKU_INC . 'lib/images/fileicons/' . $doc['ext'] . '.png')) {
                echo sprintf(
                    '<img src="%s" alt="%s" /> ',
                    DOKU_BASE . 'lib/images/fileicons/' . $doc['ext'] . '.png',
                    $doc['ext']
                );
            }
            echo '<a href="' . $href . '" class="wikilink1" title="' . hsc($page) . '">';
            echo $title;
            echo '</a>';
            echo '</dt>';

            // meta
            echo '<dd class="meta elastic-resultmeta">';
            if (!empty($doc['namespace'])) {
                echo '<span class="ns">' . $this->getLang('ns') . ' ' . hsc($doc['namespace']) . '</span>';
            }
            if ($doc['modified']) {
                $lastmod = strtotime($doc['modified']);
                echo ' <span class="">' . $lang['lastmod'] . ' ' . dformat($lastmod) . '</span>';
            }
            if (!empty($doc['user'])) {
                echo ' <span class="author">' . $this->getLang('author') . ' ' . userlink($doc['user']) . '</span>';
            }
            echo '</dd>';

            // snippets
            echo '<dd class="snippet">';
            echo $snippet;
            echo '</dd>';
        }
        echo '</dl>';

        return (bool)$found;
    }

    /**
     * @param ResultSet $result
     */
    protected function printPagination($result)
    {
        global $INPUT;
        global $QUERY;

        $all = $result->getTotalHits();
        $pages = ceil($all / $this->getConf('perpage'));
        $cur = $INPUT->int('p', 1, true);

        if ($pages < 2) return;

        // which pages to show
        $toshow = [1, 2, $cur, $pages, $pages - 1];
        if ($cur - 1 > 1) $toshow[] = $cur - 1;
        if ($cur + 1 < $pages) $toshow[] = $cur + 1;
        $toshow = array_unique($toshow);
        // fill up to seven, if possible
        if (count($toshow) < 7) {
            if ($cur < 4) {
                if ($cur + 2 < $pages && count($toshow) < 7) $toshow[] = $cur + 2;
                if ($cur + 3 < $pages && count($toshow) < 7) $toshow[] = $cur + 3;
                if ($cur + 4 < $pages && count($toshow) < 7) $toshow[] = $cur + 4;
            } else {
                if ($cur - 2 > 1 && count($toshow) < 7) $toshow[] = $cur - 2;
                if ($cur - 3 > 1 && count($toshow) < 7) $toshow[] = $cur - 3;
                if ($cur - 4 > 1 && count($toshow) < 7) $toshow[] = $cur - 4;
            }
        }
        sort($toshow);
        $showlen = count($toshow);

        echo '<ul class="elastic_pagination">';
        if ($cur > 1) {
            $p = [
                'q' => $QUERY,
                'do' => 'search',
                'ns' => $INPUT->arr('ns'),
                'min' => $INPUT->arr('min'),
                'p' => ($cur - 1)
            ];
            echo '<li class="prev">';
            echo '<a href="' . wl('', $p) . '">';
            echo '«';
            echo '</a>';
            echo '</li>';
        }

        for ($i = 0; $i < $showlen; $i++) {
            if ($toshow[$i] == $cur) {
                echo '<li class="cur">' . $toshow[$i] . '</li>';
            } else {
                $p = [
                    'q' => $QUERY,
                    'do' => 'search',
                    'ns' => $INPUT->arr('ns'),
                    'min' => $INPUT->arr('min'),
                    'p' => $toshow[$i]
                ];
                echo '<li>';
                echo '<a href="' . wl('', $p) . '">';
                echo $toshow[$i];
                echo '</a>';
                echo '</li>';
            }

            // show seperator when a jump follows
            if (isset($toshow[$i + 1]) && $toshow[$i + 1] - $toshow[$i] > 1) {
                echo '<li class="sep">…</li>';
            }
        }

        if ($cur < $pages) {
            $p = [
                'q' => $QUERY,
                'do' => 'search',
                'ns' => $INPUT->arr('ns'),
                'min' => $INPUT->arr('min'),
                'p' => ($cur + 1)
            ];
            echo '<li class="next">';
            echo '<a href="' . wl('', $p) . '">';
            echo '»';
            echo '</a>';
            echo '</li>';
        }

        echo '</ul>';
    }
}
