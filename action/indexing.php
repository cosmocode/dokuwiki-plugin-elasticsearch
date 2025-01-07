<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use Elastica\Exception\NotFoundException;
use Elastica\Document;
use Elastica\Exception\ResponseException;
use dokuwiki\Logger;
use dokuwiki\Extension\Event;

/**
 * DokuWiki Plugin elasticsearch (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Kieback&Peter IT <it-support@kieback-peter.de>
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

class action_plugin_elasticsearch_indexing extends ActionPlugin
{
    public const MIME_DOKUWIKI = 'text/dokuwiki';
    public const DOCTYPE_PAGE = 'page';
    public const DOCTYPE_MEDIA = 'media';

    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'handleTplContentDisplay');
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'handleDelete');
        $controller->register_hook('MEDIA_UPLOAD_FINISH', 'AFTER', $this, 'handleMediaUpload');
        $controller->register_hook('MEDIA_DELETE_FILE', 'AFTER', $this, 'handleMediaDelete');
    }

    /**
     * Add pages to index
     *
     * @param Event $event event object by reference
     * @return void
     */
    public function handleTplContentDisplay(Event $event)
    {
        global $ID, $INFO;
        $this->log(
            'content display',
            [
                metaFN($ID, '.elasticsearch_indexed'),
                wikiFN($ID),
                wikiFN($INFO['id']),
                $this->needsIndexing($ID) ? 'needs indexing' : 'no indexing needed',
            ]
        );
        if ($this->needsIndexing($ID)) {
            $this->indexPage($ID);
        }
    }

    /**
     * Update index on media upload
     *
     * @param Event $event
     * @throws Exception
     */
    public function handleMediaUpload(Event $event)
    {
        $this->indexFile($event->data[2]);
    }

    /**
     * Remove pages from index
     *
     * @param Event $event event object by reference
     * @return void
     */
    public function handleDelete(Event $event)
    {
        if ($event->data[3]) return; // is old revision stuff
        if (!empty($event->data[0][1])) return; // page still exists
        // still here? delete from index
        $this->deleteEntry($event->data[2], self::DOCTYPE_PAGE);
    }

    /**
     * Remove deleted media from index
     *
     * @param Event $event
     * @param $param
     */
    public function handleMediaDelete(Event $event, $param)
    {
        if ($event->data['unl']) $this->deleteEntry($event->data['id'], self::DOCTYPE_MEDIA);
    }

    /**
     * Check if the page $id has changed since the last indexing.
     *
     * @param string $id
     * @return boolean
     */
    protected function needsIndexing($id)
    {
        $indexStateFile = metaFN($id, '.elasticsearch_indexed');
        $refreshStateFile = metaFN($id, '.elasticsearch_refresh');
        $dataFile = wikiFN($id);

        // no data file or page is hidden ('hidepages' configuration option) -> no indexing
        if (!file_exists($dataFile) || isHiddenPage($id)) {
            // page should not be indexed but has a state file, try to remove from index
            if (file_exists($indexStateFile)) {
                $this->deleteEntry($id, self::DOCTYPE_PAGE);
            }
            return false;
        }

        // force indexing if we're called via cli (e.g. cron)
        if (PHP_SAPI == 'cli') {
            return true;
        }
        // check if latest indexing attempt is done after page update
        // and after other updates related to the page made by plugins
        if (file_exists($indexStateFile)) {
            if (
                (filemtime($indexStateFile) > filemtime($dataFile)) &&
                (!file_exists($refreshStateFile) || filemtime($indexStateFile) > filemtime($refreshStateFile))
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $data
     */
    protected function writeIndex($data)
    {
        /** @var helper_plugin_elasticsearch_client $hlp */
        $hlp = plugin_load('helper', 'elasticsearch_client');

        $indexName    = $this->getConf('indexname');
        $client       = $hlp->connect();
        $index        = $client->getIndex($indexName);
        $documentId   = $data['doctype'] . '_' . $data['uri'];

        // check if the document still exists to update it or add it as a new one
        try {
            $client->updateDocument($documentId, ['doc' => $data], $index->getName());
        } catch (NotFoundException $e) {
            $document = new Document($documentId, $data);
            $index->addDocument($document);
        } catch (ResponseException $e) {
            if ($e->getResponse()->getStatus() == 404) {
                $document = new Document($documentId, $data);
                $index->addDocument($document);
            } else {
                throw $e;
            }
        } catch (Exception $e) {
            msg(
                'Something went wrong on indexing please try again later or ask an admin for help.<br /><pre>' .
                hsc(get_class($e) . ' ' . $e->getMessage()) . '</pre>',
                -1
            );
            return;
        }
        $index->refresh();
        $this->updateIndexstate($data['uri']);
    }

    /**
     * Save indexed state for a page or a media file
     *
     * @param string $id
     * @param string $doctype
     * @return bool
     */
    protected function updateIndexstate($id, $doctype = self::DOCTYPE_PAGE)
    {
        $indexStateFile = ($doctype === self::DOCTYPE_MEDIA) ?
            mediaMetaFN($id, '.elasticsearch_indexed') :
            metaFN($id, '.elasticsearch_indexed');
        return io_saveFile($indexStateFile, '');
    }

    /**
     * Remove the given document from the index
     *
     * @param $id
     * @param $doctype
     */
    public function deleteEntry($id, $doctype)
    {
        /** @var helper_plugin_elasticsearch_client $hlp */
        $hlp          = plugin_load('helper', 'elasticsearch_client');
        $indexName    = $this->getConf('indexname');
        $client       = $hlp->connect();
        $index        = $client->getIndex($indexName);
        $documentId   = $doctype . '_' . $id;

        try {
            $index->deleteById($documentId);
            $index->refresh();
            $this->log($documentId . ' deleted ');
        } catch (Exception $e) {
            // we ignore this
            $this->log($documentId . ' not deleted ' . $e->getMessage());
        }

        // delete state file
        $stateFile = ($doctype === self::DOCTYPE_MEDIA) ?
            mediaMetaFN($id, '.elasticsearch_indexed') :
            metaFN($id, '.elasticsearch_indexed');
        @unlink($stateFile);
    }

    /**
     * Index a page
     *
     * @param $id
     * @return void
     */
    public function indexPage($id)
    {
        global $conf;

        $this->log('Indexing page ' . $id);

        // collect the date which should be indexed
        $meta = p_get_metadata($id, '', METADATA_RENDER_UNLIMITED);

        $data             = [];
        $data['uri']      = $id;
        $data['created']  = date('Y-m-d\TH:i:s\Z', $meta['date']['created']);
        $data['modified'] = date('Y-m-d\TH:i:s\Z', $meta['date']['modified']);
        $data['user']     = $meta['user'];
        $data['title']    = $meta['title'] ?? $id;
        $data['abstract'] = $meta['description']['abstract'];
        $data['syntax']   = rawWiki($id);
        $data['mime']     = self::MIME_DOKUWIKI;
        $data['doctype']  = self::DOCTYPE_PAGE;

        // prefer rendered plaintext over raw syntax output
        /** @var \renderer_plugin_text $textRenderer */
        $textRenderer = plugin_load('renderer', 'text');
        if ($textRenderer) {
            $data['content'] = p_cached_output(wikiFN($id), 'text');
        } else {
            $data['content']  = $data['syntax'];
        }

        /** @var helper_plugin_translation $trans */
        $trans = plugin_load('helper', 'translation');
        if ($trans) {
            // translation plugin available
            $lc               = $trans->getLangPart($id);
            $data['language'] = $trans->realLC($lc);
        } else {
            // no translation plugin
            $data['language'] = $conf['lang'];
        }

        $data['namespace'] = getNS($id);
        if (trim($data['namespace']) == '') {
            unset($data['namespace']);
        }

        /** @var helper_plugin_elasticsearch_acl $hlpAcl */
        $hlpAcl = plugin_load('helper', 'elasticsearch_acl');

        $fullACL = $hlpAcl->getPageACL($id);
        $queryACL = $hlpAcl->splitRules($fullACL);
        $data = array_merge($data, $queryACL);

        // let plugins add their own data to index
        $pluginData = $this->getPluginData($data['uri']);
        $data = array_merge($data, $pluginData);

        $this->writeIndex($data);
    }

    /**
     * Index a file
     *
     * @param string $fileId
     * @return void
     * @throws Exception
     */
    public function indexFile($fileId)
    {
        $this->log('Indexing file ' . $fileId);

        $docparser = new \helper_plugin_elasticsearch_docparser();

        $file = mediaFN($fileId);

        try {
            $data = $docparser->parse($file);
            $data['uri'] = $fileId;
            $data['doctype'] = self::DOCTYPE_MEDIA;
            $data['modified'] = date('Y-m-d\TH:i:s\Z', filemtime($file));
            $data['namespace'] = getNS($fileId);
            if (trim($data['namespace']) == '') {
                unset($data['namespace']);
            }

            /** @var helper_plugin_elasticsearch_acl $hlpAcl */
            $hlpAcl = plugin_load('helper', 'elasticsearch_acl');

            $fullACL = $hlpAcl->getPageACL($fileId);
            $queryACL = $hlpAcl->splitRules($fullACL);
            $data = array_merge($data, $queryACL);

            $this->writeIndex($data);
        } catch (RuntimeException $e) {
            $this->log('Skipping ' . $fileId . ': ' . $e->getMessage());
        }
    }


    /**
     * Get plugin data to feed into the index.
     * If data does not match previously defined mappings, it will be ignored.
     *
     * @param $id
     * @return array
     */
    protected function getPluginData($id): array
    {
        $pluginData = ['uri' => $id];
        Event::createAndTrigger('PLUGIN_ELASTICSEARCH_INDEXPAGE', $pluginData);
        return $pluginData;
    }

    /**
     * Log something to the debug log
     *
     * @param string $txt
     * @param mixed $info
     */
    protected function log($txt, $info = null)
    {
        $txt = 'ElasticSearch: ' . $txt;
        Logger::debug($txt, $info);
    }
}
