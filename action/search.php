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
        global $INPUT;

        /** @var helper_plugin_elasticsearch_client $hlp */
        $hlp = plugin_load('helper', 'elasticsearch_client');

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
                "fields"    => array("content" => new \StdClass())
            )
        );
        $equery->setSize(1000); #FIXME do pagination

        // add namespace filter
        if($INPUT->has('ns')) {
            $facetFilter = new \Elastica\Filter\BoolOr();
            foreach($INPUT->arr('ns') as $term) {
                $filter = new \Elastica\Filter\Term();
                $filter->setTerm('namespace', \Elastica\Util::replaceBooleanWordsAndEscapeTerm($term));
                $facetFilter->addFilter($filter);
            }
            $equery->setFilter($facetFilter);
        }

        // add Facets for namespaces
        $facet = new \Elastica\Facet\Terms('namespace');
        $facet->setField('namespace');
        $facet->setSize(25);
        $equery->addFacet($facet);

        try {
            $result = $index->search($equery);
            $facets = $result->getFacets();

            $this->print_intro();
            $this->print_facets($facets['namespace']['terms']);
            $this->print_results($result);
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
        $pagecreateinfo = (auth_quickaclcheck($ID) >= AUTH_CREATE) ? $lang['searchcreatepage'] : '';
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
     * @param \Elastica\Result[] $results
     */
    protected function print_results($results) {
        global $lang;

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
            echo '<dt>' . $lang['nothingfound'] . '</dt>';
        }
        echo '</dl>';
    }

    /**
     * Output the namespace facets
     *
     * @param array $facets Facet terms
     */
    protected function print_facets($facets) {
        global $INPUT;
        global $QUERY;
        global $lang;

        echo '<form action="' . wl() . '" class="elastic_facets">';
        echo '<legend>' . $this->getLang('ns') . '</legend>';
        echo '<input name="id" type="hidden" value="' . formText($QUERY) . '" />';
        echo '<input name="do" type="hidden" value="elasticsearch" />';
        echo '<ul>';
        foreach($facets as $facet) {

            echo '<li><div class="li">';
            if(in_array($facet['term'], $INPUT->arr('ns'))) {
                $on = ' checked="checked"';
            } else {
                $on = '';
            }

            echo '<label><input name="ns[]" type="checkbox"' . $on . ' value="' . formText($facet['term']) . '" /> ' . hsc($facet['term']) . '</label>';
            echo '</div></li>';
        }
        echo '</ul>';
        echo '<input type="submit" value="' . $lang['btn_search'] . '" class="button" />';
        echo '</form>';
    }

}