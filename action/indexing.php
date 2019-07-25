<?php
/**
 * DokuWiki Plugin elasticsearch (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Kieback&Peter IT <it-support@kieback-peter.de>
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_elasticsearch_indexing extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handle_tpl_content_display');
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'handle_delete');
    }

    /**
     * Add pages to index
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_tpl_content_display(Doku_Event &$event, $param) {
        global $ID, $INFO;
        $logs   = array();
        $logs[] = 'BEGIN content display';
        $logs[] = metaFN($ID, '.elasticsearch_indexed');
        $logs[] = wikiFN($ID);
        $logs[] = wikiFN($INFO['id']);
        $logs[] = $this->needs_indexing($ID) ? 'needs indexing' : 'no indexing needed';
        $logs[] = 'END content display';
        $this->log($logs);
        if($this->needs_indexing($ID)) {
            $this->index_page($ID);
        }
    }

    /**
     * Remove pages from index
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_delete(Doku_Event &$event, $param) {
        if($event->data[3]) return; // is old revision stuff
        if(!empty($event->data[0][1])) return; // page still exists
        // still here? delete from index
        $this->delete_page($event->data[2]);
    }

    /**
     * Check if the page $id has changed since the last indexing.
     *
     * @param string $id
     * @return boolean
     */
    protected function needs_indexing($id) {
        $indexStateFile = metaFN($id, '.elasticsearch_indexed');
        $dataFile       = wikiFN($id);

        // no data file -> no indexing
        if(!file_exists($dataFile)) {
            // page does not exist but has a state file, try to remove from index
            if(file_exists($indexStateFile)) {
                $this->delete_page($id);
            }
            return false;
        }

        // force indexing if we're called via cli (e.g. cron)
        if(php_sapi_name() == 'cli') {
            return true;
        }
        // check if latest indexing attempt is done after page update
        if(file_exists($indexStateFile)) {
            if(filemtime($indexStateFile) > filemtime($dataFile)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Save indexed state for a page
     *
     * @param string $id
     * @return int
     */
    protected function update_indexstate($id) {
        $indexStateFile = metaFN($id, '.elasticsearch_indexed');
        return io_saveFile($indexStateFile, '');
    }

    /**
     * Remove the given document from the index
     *
     * @param $id
     */
    public function delete_page($id) {
        /** @var helper_plugin_elasticsearch_client $hlp */
        $hlp          = plugin_load('helper', 'elasticsearch_client');
        $indexName    = $this->getConf('indexname');
        $documentType = $this->getConf('documenttype');
        $client       = $hlp->connect();
        $index        = $client->getIndex($indexName);
        $type         = $index->getType($documentType);
        $documentId   = $documentType . '_' . $id;

        try {
            $type->deleteById($documentId);
            $index->refresh();
            $this->log($documentId.' deleted ');
        } catch(Exception $e) {
            // we ignore this
            $this->log($documentId.' not deleted '.$e->getMessage());
        }

        // delete state file
        @unlink(metaFN($id, '.elasticsearch_indexed'));
    }

    /**
     * Index a page
     *
     * @param $id
     * @return void
     */
    public function index_page($id) {
        global $conf;

        /** @var helper_plugin_elasticsearch_client $hlp */
        $hlp = plugin_load('helper', 'elasticsearch_client');
        /** @var helper_plugin_elasticsearch_acl $hlpAcl */
        $hlpAcl = plugin_load('helper', 'elasticsearch_acl');

        $this->log('Indexing page ' . $id);
        $indexName    = $this->getConf('indexname');
        $documentType = $this->getConf('documenttype');
        $client       = $hlp->connect();
        $index        = $client->getIndex($indexName);
        $type         = $index->getType($documentType);
        $documentId   = $documentType . '_' . $id;

        // collect the date which should be indexed
        $meta = p_get_metadata($id, '', METADATA_RENDER_UNLIMITED);

        $data             = array();
        $data['uri']      = $id;
        $data['created']  = date('Y-m-d\TH:i:s\Z', $meta['date']['created']);
        $data['modified'] = date('Y-m-d\TH:i:s\Z', $meta['date']['modified']);
        $data['user']     = $meta['user'];
        $data['title']    = $meta['title'];
        $data['abstract'] = $meta['description']['abstract'];
        $data['content']  = rawWiki($id);

        /** @var helper_plugin_translation $trans */
        $trans = plugin_load('helper', 'translation');
        if($trans) {
            // translation plugin available
            $lc               = $trans->getLangPart($id);
            $data['language'] = $trans->realLC($lc);
        } else {
            // no translation plugin
            $lc               = '';
            $data['language'] = $conf['lang'];
        }

        $data['namespace'] = getNS($id);
        if(trim($data['namespace']) == '') {
            unset($data['namespace']);
        }

        $fullACL = $hlpAcl->getPageACL($id);
        $queryACL = $hlpAcl->splitRules($fullACL);
        $data = array_merge($data, $queryACL);

        // check if the document still exists to update it or add it as a new one
        try {
            $client->updateDocument($documentId, array('doc' => $data), $index->getName(), $type->getName());
        } catch(\Elastica\Exception\NotFoundException $e) {
            $document = new \Elastica\Document($documentId, $data);
            $type->addDocument($document);
        } catch(\Elastica\Exception\ResponseException $e) {
            if($e->getResponse()->getStatus() == 404) {
                $document = new \Elastica\Document($documentId, $data);
                $type->addDocument($document);
            } else {
                throw $e;
            }
        } catch(Exception $e) {
            msg(
                'Something went wrong on indexing please try again later or ask an admin for help.<br /><pre>' .
                hsc(get_class($e).' '.$e->getMessage()) . '</pre>',
                -1
            );
            return;
        }
        $index->refresh();
        $this->update_indexstate($id);

    }

    /**
     * Log something to the debug log
     *
     * @param string|string[] $txt
     */
    protected function log($txt) {
        if (!$this->getConf('debug')) {
            return;
        }
        $logs = (array)$txt;

        foreach ($logs as $entry) {
            dbglog($entry);
        }
    }
}
