<?php
/**
 * DokuWiki Plugin elasticsearch (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Kieback&Peter IT <it-support@kieback-peter.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once dirname(__FILE__) . '/../vendor/autoload.php';

class action_plugin_elasticsearch_indexing extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler &$controller) {

       $controller->register_hook('INDEXER_PAGE_ADD', 'BEFORE', $this, 'handle_indexer_page_add');
       $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_tpl_content_display');
   
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_indexer_page_add(Doku_Event &$event, $param) {
        global $ID;

        $logs = array();
        $logs[] = 'BEGIN';
        $logs[] = metaFN($ID,'.elasticsearch_indexed');
        $logs[] = wikiFN($ID);
        $logs[] = 'END';
        foreach($logs as $entry) {
            syslog(LOG_ERR, $entry);
        }
    }

    public function handle_tpl_content_display(Doku_Event &$event, $param) {
    }


    private function needs_indexing($id) {
        $indexStateFile = metaFN($id, '.elasticsearch_indexed');
        //TODO check filemtime of indexStateFile against filemtime(wikiFN($id))
    }

}

