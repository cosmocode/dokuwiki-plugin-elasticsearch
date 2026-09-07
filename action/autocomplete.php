<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\elasticsearch\Search;

/**
 * DokuWiki Plugin elasticsearch (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Andreas Gohr, Anna Dabrowska <dokuwiki@cosmocode.de>
 */
class action_plugin_elasticsearch_autocomplete extends ActionPlugin
{
    /** @inheritDoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjaxCallUnknownEvent');
    }

    /**
     * Event handler for AJAX_CALL_UNKNOWN
     *
     * @see https://www.dokuwiki.org/devel:events:AJAX_CALL_UNKNOWN
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handleAjaxCallUnknownEvent(Event $event, $param)
    {
        if ($event->data !== 'elasticsearch_autocomplete') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT;

        // execute search and output results
        try {
            $search = new Search($INPUT->str('q'));
            $result = $search->autocomplete();
            $result = array_map(fn($item) => $item['text'], (array)$result['suggest']['autocomplete'][0]['options']);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result);
        } catch (Exception $e) {
            http_status(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo $e->getMessage();
        }
    }
}
