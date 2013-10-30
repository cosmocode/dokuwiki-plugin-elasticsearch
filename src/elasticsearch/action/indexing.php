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
     * @var \Elastica\Client $elasticaClient
     */
    private $elasticaClient;

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
        $logs[] = 'BEGIN page add';
        $logs[] = metaFN($ID,'.elasticsearch_indexed');
        $logs[] = wikiFN($ID);
        $logs[] = $this->needs_indexing($ID) ? 'needs indexing' : 'index still exists';
        $logs[] = 'END page add';
        $this->log($logs);
        if ($this->needs_indexing($ID)) {
            $this->index_page($ID);
        }
    }

    public function handle_tpl_content_display(Doku_Event &$event, $param) {
        global $ID;
        $logs = array();
        $logs[] = 'BEGIN content display';
        $logs[] = metaFN($ID,'.elasticsearch_indexed');
        $logs[] = wikiFN($ID);
        $logs[] = metaFN('lib_images_smileys_plus.gif','.elasticsearch_indexed');
        $logs[] = wikiFN('lib_images_smileys_plus.gif');
        $logs[] = $this->needs_indexing($ID) ? 'needs indexing' : 'index still exists';
        $logs[] = 'END content display';
        $this->log($logs);
        if ($this->getConf('elasticsearch_indexondisplay')) {
            if ($this->needs_indexing($ID)) {
                $this->index_page($ID);
            }
        }
    }


    /**
     * Check if the page $id has changed since the last indexing.
     *
     * @param $id
     * @return boolean
     */
    private function needs_indexing($id) {
        $indexStateFile = metaFN($id, '.elasticsearch_indexed');
        $dataFile = wikiFN($id);
        if (file_exists($indexStateFile)) {
            if (filemtime($indexStateFile) > filemtime($dataFile)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the object and creates if needed beforehand
     * @return \Elastica\Client
     */
    private function getElasticaClient() {
        if (is_null($this->elasticaClient)) {
            $dsn = $this->getConf('elasticsearch_dsn');
            $this->elasticaClient = new \Elastica\Client($dsn);
        }
        return $this->elasticaClient;
    }

    /**
     * Index a page
     *
     * @param $id
     * @return void
     */
    private function index_page($id) {
        $this->log('Indexing page ' . $id);
        $indexName = $this->getConf('elasticsearch_indexname');
        $documentType = $this->getConf('elasticsearch_documenttype');
        $client = $this->getElasticaClient();
        $index  = $client->getIndex($indexName);
        $type   = $index->getType($documentType);
        $documentId = $documentType . '_' . $id;

        // collect the date which should be indexed
        $meta = p_get_metadata($id, '', true);

        $data = array();
        $data['created'] = date('Y-m-d\TH:i:s\Z', $meta['date']['created']);
        $data['modified'] = date('Y-m-d\TH:i:s\Z', $meta['date']['modified']);
        $data['creator'] = $meta['creator'];
        $data['title'] = $meta['title'];
        $data['abstract'] = $meta['description']['abstract'];
        $data['content'] = rawWiki($id);

        // check if the document still exists to update it or add it as a new one
        try {
            $document = $type->getDocument($documentId);
            $client->updateDocument($documentId, array('doc' => $data), $index->getName(), $type->getName());
        } catch (\Elastica\Exception\NotFoundException $e) {
            $document = new \Elastica\Document($documentId, $data);
            $type->addDocument($document);
        }
        $index->refresh();
    }

    private function log($txt) {
        if (!$this->getConf('elasticsearch_debug')) {
            return;
        }
        if (!is_array($txt)) {
            $logs = array($txt);
        } else {
            $logs = $txt;
        }
        foreach($logs as $entry) {
            syslog(LOG_ERR, $entry);
        }
    }

}

