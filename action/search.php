<?php
/**
 * DokuWiki Plugin elasticsearch (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_elasticsearch_search extends DokuWiki_Action_Plugin {

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
    public function handle_preprocess(Doku_Event &$event, $param) {
        if($event->data != 'elasticsearch') return;
        $event->preventDefault();
        $event->stopPropagation();
    }

    /**
     * do the actual search
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handle_action(Doku_Event &$event, $param) {
        if($event->data != 'elasticsearch') return;
        $event->preventDefault();
        $event->stopPropagation();
        global $QUERY;
        global $INPUT;
        global $USERINFO;

        /** @var helper_plugin_elasticsearch_client $hlp */
        $hlp = plugin_load('helper', 'elasticsearch_client');

        /** @var helper_plugin_elasticsearch_form $hlpform */
        $hlpform = plugin_load('helper', 'elasticsearch_form');

        $client = $hlp->connect();
        $index  = $client->getIndex($this->getConf('indexname'));

        // define the query string
        $qstring = new \Elastica\Query\SimpleQueryString($QUERY);

        // create the actual search object
        $equery = new \Elastica\Query();
        $equery->setQuery($qstring);
        $equery->setHighlight(
            array(
                "pre_tags"  => array('ELASTICSEARCH_MARKER_IN'),
                "post_tags" => array('ELASTICSEARCH_MARKER_OUT'),
                "fields"    => array(
                    $this->getConf('snippets') => new \StdClass(),
                    'title' => new \StdClass())
            )
        );

        // paginate
        $equery->setSize($this->getConf('perpage'));
        $equery->setFrom($this->getConf('perpage') * ($INPUT->int('p', 1, true) - 1));

        $subqueries = new \Elastica\Query\BoolQuery();

        // add group subquery
        $groups = array('all');
        if(isset($USERINFO['grps'])) {
            $groups = array_merge($groups, $USERINFO['grps']);
        }
        $groupSubquery = new \Elastica\Query\BoolQuery();
        foreach($groups as $group) {
            $group  = str_replace('-', '', strtolower($group));
            $term = new \Elastica\Query\Term();
            $term->setTerm('groups', $group);
            $groupSubquery->addShould($term);
        }
        $subqueries->addMust($groupSubquery);

        // add namespace filter
        if($INPUT->has('ns')) {
            $nsSubquery = new \Elastica\Query\BoolQuery();
            foreach($INPUT->arr('ns') as $ns) {
                $term = new \Elastica\Query\Term();
                $term->setTerm('namespace', $ns);
                $nsSubquery->addShould($term);
            }
            $subqueries->addMust($nsSubquery);
        }

        // set all filters
        // FIXME is filtering the right thing here?
        $equery->setPostFilter($subqueries);

        // add aggregations for namespaces
        $agg = new \Elastica\Aggregation\Terms('namespace');
        $agg->setField('namespace.keyword');
        $agg->setSize(25);
        $equery->addAggregation($agg);

        try {
            $result = $index->search($equery);
            $aggs = $result->getAggregations();

            $this->print_intro();
            $hlpform->tpl($aggs['namespace']['buckets']);
            $this->print_results($result) && $this->print_pagination($result);
        } catch(Exception $e) {
            msg('Something went wrong on searching please try again later or ask an admin for help.<br /><pre>' . hsc($e->getMessage()) . '</pre>', -1);
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
            array('@QUERY@', '@SEARCH@', '@CREATEPAGEINFO@'),
            array(hsc(rawurlencode($QUERY)), hsc($QUERY), $pagecreateinfo),
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
                array('ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'),
                array('<strong class="search_hit">', '</strong>'),
                hsc(join(' … ', (array) $row->getHighlights()['title']))
            );
            if(!$title) $title = hsc($row->getSource()['title']);
            if(!$title) $title = hsc(p_get_first_heading($page));
            if(!$title) $title = hsc($page);

            // get highlighted snippet
            $snippet = str_replace(
                array('ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'),
                array('<strong class="search_hit">', '</strong>'),
                hsc(join(' … ', (array) $row->getHighlights()[$this->getConf('snippets')]))
            );
            if(!$snippet) $snippet = hsc($row->getSource()['abstract']); // always fall back to abstract

            echo '<dt>';
            echo '<a href="'.wl($page).'" class="wikilink1" title="'.hsc($page).'">';
            echo $title;
            echo '</a>';
            echo '</dt>';

            // meta
            echo '<dd class="meta">';
            if($row->getSource()['namespace']) {
                echo '<span class="ns">' . $this->getLang('ns') . ' ' . hsc($row->getSource()['namespace']) . '</span>';
            }
            if($row->getSource()['creator']) {
                echo ' <span class="author">' . $this->getLang('author') . ' ' . hsc($row->getSource()['creator']) . '</span>';
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
        $toshow = array(1, 2, $cur, $pages, $pages - 1);
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
            echo '<a href="' . wl('', http_build_query(array('q' => $QUERY, 'do' => 'elasticsearch', 'ns' => $INPUT->arr('ns'), 'p' => ($cur-1)))) . '">';
            echo '«';
            echo '</a>';
            echo '</li>';
        }

        for($i = 0; $i < $showlen; $i++) {
            if($toshow[$i] == $cur) {
                echo '<li class="cur">' . $toshow[$i] . '</li>';
            } else {
                echo '<li>';
                echo '<a href="' . wl('', http_build_query(array('q' => $QUERY, 'do' => 'elasticsearch', 'ns' => $INPUT->arr('ns'), 'p' => $toshow[$i]))) . '">';
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
            echo '<a href="' . wl('', http_build_query(array('q' => $QUERY, 'do' => 'elasticsearch', 'ns' => $INPUT->arr('ns'), 'p' => ($cur+1)))) . '">';
            echo '»';
            echo '</a>';
            echo '</li>';
        }

        echo '</ul>';
    }

}
