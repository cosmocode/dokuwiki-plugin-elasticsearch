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
use dokuwiki\plugin\elasticsearch\Search;

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
        global $QUERY;
        global $INPUT;

        if (empty($QUERY)) $QUERY = $INPUT->str('q');

        // execute search and output results
        try {
            $search = new Search($QUERY);
            $result = $search->search();
            $gui = new Gui($result, $search->getParsedQuery());
            echo $gui->render();
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
}
