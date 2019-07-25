<?php
/**
 * DokuWiki Plugin elasticsearch (CLI Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

if (!defined('DOKU_INC')) die();

use splitbrain\phpcli\Options;

class cli_plugin_elasticsearch extends DokuWiki_CLI_Plugin {

    /** @var helper_plugin_elasticsearch_client */
    protected $hlp;

    /**
     * Initialize helper plugin
     */
    public function __construct(){
        parent::__construct();
        $this->hlp = plugin_load('helper', 'elasticsearch_client');
    }


    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     * @throws \splitbrain\phpcli\Exception
     */
    protected function setup(Options $options) {
        $options->setHelp('Manage the elastic search index');

        $options->registerCommand('index', 'Index all pages in the wiki');

        $options->registerCommand('createindex', 'Create a simple index named "'.$this->hlp->getConf('indexname').'".');
        $options->registerOption('clear', 'Remove existing index if any', 'c', false, 'createindex');

        $options->registerCommand('createlangmapping', 'Create the field mapping for multilanguage setup');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     * @return void
     */
    protected function main(Options $options) {
        // manually initialize auth system
        // see https://github.com/splitbrain/dokuwiki/issues/2823
        global $AUTH_ACL;
        if(!$AUTH_ACL) auth_setup();

        $cmd = $options->getCmd();
        switch ($cmd) {
            case 'createindex':
                $result = $this->hlp->createIndex($options->getOpt('clear'));
                if($result->hasError()){
                    $this->error($result->getError());
                } else {
                    $this->success('Index created');
                }
                break;
            case 'createlangmapping':
                $result = $this->hlp->createLanguageMapping();
                if($result->hasError()){
                    $this->error($result->getError());
                } else {
                    $this->success('Mapping created');
                }
                break;
            case 'index':
                $this->indexAllPages();
                break;
            default:
                $this->error('No command provided');
                exit(1);
        }


    }

    /**
     * Index all the pages
     */
    protected function indexAllPages() {
        global $conf, $ID;

        /** @var action_plugin_elasticsearch_indexing $act */
        $act = plugin_load('action', 'elasticsearch_indexing');

        $data = array();
        search($data, $conf['datadir'], 'search_allpages', array('skipacl' => true));
        $pages = count($data);
        $n     = 0;
        foreach($data as $val) {
            $ID = $val['id'];
            $n++;
            $this->info(sprintf("Indexing page %s (%d of %d)\n", $ID, $n, $pages));
            $act->index_page($ID);
        }
    }

}
