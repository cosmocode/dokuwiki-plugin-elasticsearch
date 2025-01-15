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
use dokuwiki\plugin\elasticsearch\gui\Gui;
use dokuwiki\plugin\elasticsearch\Query;
use dokuwiki\plugin\elasticsearch\QueryParser;

/**
 * Main search helper
 */
class action_plugin_elasticsearch_search extends ActionPlugin
{
    /**
     * See Filter for information on the structure of the filterconfigs
     *
     * @var Array
     */
    protected $filterconfigs = [];

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
        global $INFO;
        global $QUERY;
        global $INPUT;
        global $ID;


        // get extended search configurations from plugins
        Event::createAndTrigger('PLUGIN_ELASTICSEARCH_FILTERS', $this->filterconfigs);
        // add namespace filter
        $this->filterconfigs['namespace'] = [
            'label' => 'Namespace', // FIXME localize
            'queryParam' => 'ns',
            'prefix' => '@',
            'isAndQuery' => false,
        ];
        // add language filter
        $langfilter = $this->createLanguageFilter();
        if ($langfilter) {
            $this->filterconfigs['language'] = $langfilter;
        }

        // parse the query
        if (empty($QUERY)) $QUERY = $INPUT->str('q');
        if (empty($QUERY)) $QUERY = $ID;
        $queryParser = new QueryParser($QUERY, $this->filterconfigs);
        $QUERY = $queryParser->getQuery();

        // get fields to use in query
        $fields = [];
        Event::createAndTrigger('PLUGIN_ELASTICSEARCH_SEARCHFIELDS', $fields);
        if ($this->getConf('searchSyntax')) {
            $this->searchFields[] = 'syntax*';
        }

        // initialize the Query
        $queryBuilder = new Query();
        $queryBuilder->setSimpleQuery($QUERY, array_merge($this->searchFields, $fields));
        $queryBuilder->setHighlights($this->getConf('snippets'));
        $queryBuilder->setPagination($this->getConf('perpage'), $INPUT->int('p', 1, true));
        if (!$INFO['isadmin']) {
            $queryBuilder->setACLs($_SERVER['REMOTE_USER'] ?? '', $INFO['userinfo']['grps'] ?? []);
        }
        $queryBuilder->addDateFilter($INPUT->str('min'));

        // add filters
        foreach ($queryParser->getFilters() as $filter) {
            $queryBuilder->addFilter($filter);
        }


        try {
            /** @var helper_plugin_elasticsearch_client $hlp */
            $hlp = plugin_load('helper', 'elasticsearch_client');
            $client = $hlp->client();
            $result = $client->call('_search', $queryBuilder->query);

            $gui = new Gui($result, $queryParser);
            echo $gui->render();

            /*
            $this->printIntro();

            $hlpform = plugin_load('helper', 'elasticsearch_form');
            $hlpform->tpl($result['aggregations'], $this->filterconfigs);
            if ($this->printResults($result)) {
                $this->printPagination($result);
            }
            */
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
            if ($topNs && in_array($topNs, $transplugin->translations)) {
                $langFilter = [$topNs];
                $INPUT->set('lang', $langFilter);
            } elseif ($transplugin->defaultlang === '') {
                // for empty default language, use the real language code
                $langFilter = $transplugin->realLC('');
                $INPUT->set('lang', $langFilter);
            }
        }

        return $langFilter;
    }

    protected function createLanguageFilter()
    {
        /** @var helper_plugin_translation $transplugin */
        $transplugin = plugin_load('helper', 'translation');
        if ($transplugin === null) return null;

        $options = [];
        foreach ($transplugin->translations as $lang) {
            $lang = $transplugin->realLC($lang);
            $label = $transplugin->getLocalName($lang);
            $options[$lang] = $label;
        }

        return [
            'label' => 'Language', // FIXME localize
            'queryParam' => 'lang',
            'isAndQuery' => false,
            'options' => $options,
        ];
    }


}
