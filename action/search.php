<?php
/**
 * DokuWiki Plugin elasticsearch (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

use dokuwiki\Extension\Event;

/**
 * Main search helper
 */
class action_plugin_elasticsearch_search extends DokuWiki_Action_Plugin {

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
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_preprocess');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'handle_action');

    }

    /**
     * allow our custom do command
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handle_preprocess(Doku_Event $event, $param) {
        if ($event->data !== 'search') return;
        $event->preventDefault();
        $event->stopPropagation();
    }

    /**
     * do the actual search
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handle_action(Doku_Event $event, $param) {
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
        $index  = $client->getIndex($this->getConf('indexname'));

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
            array_push($this->searchFields, 'syntax*');
        }

        // finally define the elastic query
        $qstring = new \Elastica\Query\SimpleQueryString($QUERY, array_merge($this->searchFields, $fields));
        // restore the original query
        $QUERY = $q;
        // append additions provided by plugins
        if (!empty($additions)) {
            $QUERY .= ' ' . implode(' ', $additions);
        }

        // create the actual search object
        $equery = new \Elastica\Query();
        $subqueries = new \Elastica\Query\BoolQuery();
        $subqueries->addMust($qstring);

        $equery->setHighlight(
            [
                "pre_tags"  => ['ELASTICSEARCH_MARKER_IN'],
                "post_tags" => ['ELASTICSEARCH_MARKER_OUT'],
                "fields"    => [
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
        if($INPUT->has('ns')) {
            $nsSubquery = new \Elastica\Query\BoolQuery();
            foreach($INPUT->arr('ns') as $ns) {
                $eterm = new \Elastica\Query\Term();
                $eterm->setTerm('namespace', $ns);
                $nsSubquery->addShould($eterm);
            }
            $equery->setPostFilter($nsSubquery);
        }

        $equery->setQuery($subqueries);

        // add aggregations for namespaces
        $agg = new \Elastica\Aggregation\Terms('namespace');
        $agg->setField('namespace.keyword');
        $agg->setSize(25);
        $equery->addAggregation($agg);

        // add search configurations from other plugins
        $this->addPluginConfigurations($equery);

        try {
            $result = $index->search($equery);
            $aggs = $result->getAggregations();

            $this->print_intro();
            /** @var helper_plugin_elasticsearch_form $hlpform */
            $hlpform = plugin_load('helper', 'elasticsearch_form');
            $hlpform->tpl($aggs);
            $this->print_results($result) && $this->print_pagination($result);
        } catch(Exception $e) {
            msg('Something went wrong on searching please try again later or ask an admin for help.<br /><pre>' . hsc($e->getMessage()) . '</pre>', -1);
        }
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
     * @param \Elastica\Query $equery
     */
    protected function addPluginConfigurations($equery)
    {
        global $INPUT;

        if (!empty(self::$pluginSearchConfigs)) {
            foreach (self::$pluginSearchConfigs as $param => $config) {
                // handle search parameter
                if ($INPUT->has($param)) {
                    $pluginSubquery = new \Elastica\Query\BoolQuery();
                    foreach($INPUT->arr($param) as $item) {
                        $eterm = new \Elastica\Query\Term();
                        $eterm->setTerm($param, $item);
                        $pluginSubquery->addShould($eterm);
                    }
                    $equery->setPostFilter($pluginSubquery);
                }
                // build aggregation for use as filter in advanced search
                $agg = new \Elastica\Aggregation\Terms($param);
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
     * @param Elastica\Query\BoolQuery $subqueries
     * @param string $min Modified at the latest one {year|month|week} ago
     */
    protected function addDateSubquery($subqueries, $min)
    {
        if (!in_array($min, ['year', 'month', 'week'])) return;

        $dateSubquery = new \Elastica\Query\Range(
            'modified',
            ['gte' => date('Y-m-d', strtotime('1 ' . $min . ' ago'))]
        );
        $subqueries->addMust($dateSubquery);
    }

    /**
     * Adds language subquery
     *
     * @param Elastica\Query\BoolQuery $subqueries
     * @param array $langFilter
     */
    protected function addLanguageSubquery($subqueries, $langFilter)
    {
        if (empty($langFilter)) return;

        $langSubquery = new \Elastica\Query\Match();
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
        } else if (empty($langFilter) && $transplugin) {
            // select all available translations
            $INPUT->set('lang', $transplugin->translations);
        }

        return $langFilter;
    }

    /**
     * Inserts subqueries based on current user's ACLs, none for superusers
     *
     * @param \Elastica\Query\BoolQuery $subqueries
     */
    protected function addACLSubqueries($subqueries)
    {
        global $USERINFO;
        global $conf;

        $groups = array_merge(['ALL'], $USERINFO['grps'] ?: []);

        // no ACL filters for superusers
        if (in_array(ltrim($conf['superuser'], '@'), $groups)) return;

        // include if group OR user have read permissions, allows for ACLs such as "block @group except user"
        $includeSubquery = new \Elastica\Query\BoolQuery();
        foreach($groups as $group) {
            $term = new \Elastica\Query\Term();
            $term->setTerm('groups_include', $group);
            $includeSubquery->addShould($term);
        }
        if (isset($_SERVER['REMOTE_USER'])) {
            $userIncludeSubquery = new \Elastica\Query\BoolQuery();
            $term = new \Elastica\Query\Term();
            $term->setTerm('users_include', $_SERVER['REMOTE_USER']);
            $userIncludeSubquery->addMust($term);
            $includeSubquery->addShould($userIncludeSubquery);
        }
        $subqueries->addMust($includeSubquery);

        // groups exclusion SHOULD be respected, not MUST, since that would not allow for exceptions
        $groupExcludeSubquery = new \Elastica\Query\BoolQuery();
        foreach($groups as $group) {
            $term = new \Elastica\Query\Term();
            $term->setTerm('groups_exclude', $group);
            $groupExcludeSubquery->addShould($term);
        }
        $excludeSubquery = new \Elastica\Query\BoolQuery();
        $excludeSubquery->addMustNot($groupExcludeSubquery);
        $subqueries->addShould($excludeSubquery);

        // user specific excludes must always be respected
        if (isset($_SERVER['REMOTE_USER'])) {
            $term = new \Elastica\Query\Term();
            $term->setTerm('users_exclude', $_SERVER['REMOTE_USER']);
            $subqueries->addMustNot($term);
        }
    }

    /**
     * Prints the introduction text
     */
    protected function print_intro() {
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
        $intro          = str_replace(
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
     * @param \Elastica\ResultSet $results
     * @return bool true when results where shown
     */
    protected function print_results($results) {
        global $lang;

        // output results
        $found = $results->getTotalHits();

        if(!$found) {
            echo '<h2>' . $lang['nothingfound'] . '</h2>';
            return (bool)$found;
        }

        echo '<dl class="search_results">';
        echo '<h2>' . sprintf($this->getLang('totalfound'), $found) . '</h2>';
        foreach($results as $row) {

            /** @var Elastica\Result $row */
            $page = $row->getSource()['uri'];
            if(!page_exists($page) || auth_quickaclcheck($page) < AUTH_READ) continue;

            // get highlighted title
            $title = str_replace(
                ['ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'],
                ['<strong class="search_hit">', '</strong>'],
                hsc(join(' … ', (array) $row->getHighlights()['title']))
            );
            if(!$title) $title = hsc($row->getSource()['title']);
            if(!$title) $title = hsc(p_get_first_heading($page));
            if(!$title) $title = hsc($page);

            // get highlighted snippet
            $snippet = str_replace(
                ['ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'],
                ['<strong class="search_hit">', '</strong>'],
                hsc(join(' … ', (array) $row->getHighlights()[$this->getConf('snippets')]))
            );
            if(!$snippet) $snippet = hsc($row->getSource()['abstract']); // always fall back to abstract

            echo '<dt>';
            echo '<a href="'.wl($page).'" class="wikilink1" title="'.hsc($page).'">';
            echo $title;
            echo '</a>';
            echo '</dt>';

            // meta
            echo '<dd class="meta elastic-resultmeta">';
            if($row->getSource()['namespace']) {
                echo '<span class="ns">' . $this->getLang('ns') . ' ' . hsc($row->getSource()['namespace']) . '</span>';
            }
            if($row->getSource()['user']) {
                echo ' <span class="author">' . $this->getLang('author') . ' ' . userlink($row->getSource()['user']) . '</span>';
            }
            if($row->getSource()['modified']) {
                $lastmod = strtotime($row->getSource()['modified']);
                echo ' <span class="">' . $lang['lastmod'] . ' ' . dformat($lastmod) . '</span>';
            }
            echo '</dd>';

            // snippets
            echo '<dd class="snippet">';
            echo $snippet;
            echo '</dd>';

        }
        echo '</dl>';

        return (bool) $found;
    }

    /**
     * @param \Elastica\ResultSet $result
     */
    protected function print_pagination($result) {
        global $INPUT;
        global $QUERY;

        $all   = $result->getTotalHits();
        $pages = ceil($all / $this->getConf('perpage'));
        $cur   = $INPUT->int('p', 1, true);

        if($pages < 2) return;

        // which pages to show
        $toshow = [1, 2, $cur, $pages, $pages - 1];
        if($cur - 1 > 1) $toshow[] = $cur - 1;
        if($cur + 1 < $pages) $toshow[] = $cur + 1;
        $toshow = array_unique($toshow);
        // fill up to seven, if possible
        if(count($toshow) < 7) {
            if($cur < 4) {
                if($cur + 2 < $pages && count($toshow) < 7) $toshow[] = $cur + 2;
                if($cur + 3 < $pages && count($toshow) < 7) $toshow[] = $cur + 3;
                if($cur + 4 < $pages && count($toshow) < 7) $toshow[] = $cur + 4;
            } else {
                if($cur - 2 > 1 && count($toshow) < 7) $toshow[] = $cur - 2;
                if($cur - 3 > 1 && count($toshow) < 7) $toshow[] = $cur - 3;
                if($cur - 4 > 1 && count($toshow) < 7) $toshow[] = $cur - 4;
            }
        }
        sort($toshow);
        $showlen = count($toshow);

        echo '<ul class="elastic_pagination">';
        if($cur > 1) {
            echo '<li class="prev">';
            echo '<a href="' . wl('', http_build_query(['q' => $QUERY, 'do' => 'search', 'ns' => $INPUT->arr('ns'), 'min' => $INPUT->arr('min'), 'p' => ($cur-1)])) . '">';
            echo '«';
            echo '</a>';
            echo '</li>';
        }

        for($i = 0; $i < $showlen; $i++) {
            if($toshow[$i] == $cur) {
                echo '<li class="cur">' . $toshow[$i] . '</li>';
            } else {
                echo '<li>';
                echo '<a href="' . wl('', http_build_query(['q' => $QUERY, 'do' => 'search', 'ns' => $INPUT->arr('ns'), 'min' => $INPUT->arr('min'), 'p' => $toshow[$i]])) . '">';
                echo $toshow[$i];
                echo '</a>';
                echo '</li>';
            }

            // show seperator when a jump follows
            if(isset($toshow[$i + 1]) && $toshow[$i + 1] - $toshow[$i] > 1) {
                echo '<li class="sep">…</li>';
            }
        }

        if($cur < $pages) {
            echo '<li class="next">';
            echo '<a href="' . wl('', http_build_query(['q' => $QUERY, 'do' => 'search', 'ns' => $INPUT->arr('ns'), 'min' => $INPUT->arr('min'), 'p' => ($cur+1)])) . '">';
            echo '»';
            echo '</a>';
            echo '</li>';
        }

        echo '</ul>';
    }

}
