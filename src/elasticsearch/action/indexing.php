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
        $logs[] = $this->needs_indexing($ID) ? 'needs indexing' : 'no indexing needed';
        $logs[] = 'END page add';
        $this->log($logs);
        if ($this->needs_indexing($ID)) {
            $this->index_page($ID);
        }
    }

    public function handle_tpl_content_display(Doku_Event &$event, $param) {
        global $ID, $INFO;
        $logs = array();
        $logs[] = 'BEGIN content display';
        $logs[] = metaFN($ID,'.elasticsearch_indexed');
        $logs[] = wikiFN($ID);
        $logs[] = wikiFN($INFO['id']);
        $logs[] = $this->needs_indexing($ID) ? 'needs indexing' : 'no indexing needed';
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

        // no data file -> no indexing
        if (!file_exists($dataFile)) {
            return false;
        }
        // check if latest indexing attempt is done after page update
        if (file_exists($indexStateFile)) {
            if (filemtime($indexStateFile) > filemtime($dataFile)) {
                return false;
            }
        }
        return true;
    }

    private function update_indexstate($id) {
        $indexStateFile = metaFN($id, '.elasticsearch_indexed');
        return file_put_contents($indexStateFile, '');
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

        // @TODO check if content is empty, that means the page is deleted and
        //       has to be removed from the index.

        // collect the date which should be indexed
        $meta = p_get_metadata($id, '', true);

        $data = array();
        $data['uri']       = $id;
        $data['created']   = date('Y-m-d\TH:i:s\Z', $meta['date']['created']);
        $data['modified']  = date('Y-m-d\TH:i:s\Z', $meta['date']['modified']);
        $data['creator']   = $meta['creator'];
        $data['title']     = $meta['title'];
        $data['abstract']  = $meta['description']['abstract'];
        $data['content']   = rawWiki($id);
        $data['language']  = substr(getNS($id), 0, 3) == 'en:' ? 'en' : 'de';
        $metadata_ns = p_get_metadata(noNS($id), '', true);
        if (!isset($metadata_ns['title'])) {
            $metadata_ns = p_get_metadata(noNS($id).':start', '', true);
        }
        $metadata_ns['title'] = p_get_first_heading($id, METADATA_DONT_RENDER);
        $data['namespace'] = $metadata_ns['title'];
        $data['namespace'] = str_replace('*', '', $data['namespace']);

        $data['groups']    = $this->getPageACL($id);

        $this->getPageACL($id);

        // check if the document still exists to update it or add it as a new one
        try {
            $document = $type->getDocument($documentId);
            $client->updateDocument($documentId, array('doc' => $data), $index->getName(), $type->getName());
        } catch (\Elastica\Exception\NotFoundException $e) {
            $document = new \Elastica\Document($documentId, $data);
            $type->addDocument($document);
        }
        $index->refresh();
        $this->update_indexstate($id);
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

    private function getPageACL($id) {
        global $AUTH_ACL;
        global $conf;

        $id = cleanID($id);
        $ns = getNS($id);
        $perms = array();

        $matches = preg_grep('/^'.preg_quote($id,'/').'\s+/',$AUTH_ACL);
        if(count($matches)){
            foreach($matches as $match){
                $match = preg_replace('/#.*$/','',$match); //ignore comments
                $acl   = preg_split('/\s+/',$match);
                if($acl[2] > AUTH_DELETE) $acl[2] = AUTH_DELETE; //no admins in the ACL!
                if(!isset($perms[$acl[1]])) $perms[$acl[1]] = $acl[2];
            }
        }
        //still here? do the namespace checks
        if($ns){
            $path = $ns.':\*';
        }else{
            $path = '\*'; //root document
        }
        do{
            $matches = preg_grep('/^'.$path.'\s+/',$AUTH_ACL);
            if(count($matches)){
                foreach($matches as $match){
                    $match = preg_replace('/#.*$/','',$match); //ignore comments
                    $acl   = preg_split('/\s+/',$match);
                    if($acl[2] > AUTH_DELETE) $acl[2] = AUTH_DELETE; //no admins in the ACL!
                    if(!isset($perms[$acl[1]])) $perms[$acl[1]] = $acl[2];
                }
            }

            //get next higher namespace
            $ns   = getNS($ns);
            //get next higher namespace
            $ns   = getNS($ns);

            if($path != '\*'){
                $path = $ns.':\*';
                if($path == ':\*') $path = '\*';
            }else{
                //we did this already
                //break here
                break;
            }
        } while(1); //this should never loop endless
        $groups = array();
        foreach($perms as $group => $permission) {
            if ($permission > AUTH_NONE) {
                $groups[] = str_replace('-', '', str_replace('@', '', strtolower(urldecode($group))));
                $this->log(sprintf("%s = %s", $group, $permission));
            }
        }
        return $groups;
    }
}