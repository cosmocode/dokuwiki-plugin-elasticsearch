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
    public function register(Doku_Event_Handler &$controller) {

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

        /** @var helper_plugin_elasticsearch_client $hlp */
        $hlp = plugin_load('helper', 'elasticsearch_client');

        $client = $hlp->connect();
        $index  = $client->getIndex($this->getConf('indexname'));

        // define the query string
        $qstring = new \Elastica\Query\QueryString();
        $qstring->setQuery($QUERY);

        // create the actual search object
        $equery = new \Elastica\Query();
        $equery->setQuery($qstring);
        $equery->setHighlight(
            array(
                "pre_tags"  => array('ELASTICSEARCH_MARKER_IN'),
                "post_tags" => array('ELASTICSEARCH_MARKER_OUT'),
                "fields"    => array("content" => new \StdClass())
            )
        );

        try {
            $result = $index->search($equery);
            $this->print_results($result);
        } catch (Exception $e) {
            msg('Something went wrong on searching ('.$e->getMessage().') please try again later or ask an admin for help', -1);
        }
    }

    /**
     * Output the search results
     *
     * @fixme maybe add facets later?
     * @param \Elastica\Result[] $results
     */
    protected function print_results($results) {
        global $lang;
        global $QUERY;

        // just reuse the standard search page intro:
        $intro = p_locale_xhtml('searchpage');
        $intro = str_replace(
            array('@QUERY@','@SEARCH@'),
            array(hsc(rawurlencode($QUERY)),hsc($QUERY)),
            $intro);
        echo $intro;
        flush();

        // output results
        $found = 0;
        echo '<dl class="search_results">';
        foreach($results as $row) {
            /** @var Elastica\Result $row */

            $page = $row->getSource()['uri'];
            if(!page_exists($page) || auth_quickaclcheck($page) < AUTH_READ) continue;

            echo '<dt>';
            echo html_wikilink($page);
            echo ': ' . $row->getScore();
            echo '</dt>';

            // snippets
            echo '<dd>';
            echo str_replace(
                array('ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'),
                array('<strong class="search_hit">', '</strong>'),
                hsc(join(' … ', (array) $row->getHighlights()['content']))
            );
            echo '</dd>';
            $found++;
        }
        if(!$found) {
            echo '<dt>'.$lang['nothingfound'].'</dt>';
        }
        echo '</dl>';
    }

}