#!/usr/bin/php
<?php
if(!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__).'/../../../').'/');
require_once(DOKU_INC.'inc/init.php');
require_once dirname(__FILE__) . '/vendor/autoload.php';

class elasticsearch_cli extends DokuCLI {

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
     * @param DokuCLI_Options $options
     * @return void
     */
    protected function setup(DokuCLI_Options $options) {
        $options->setHelp('Manage the elastic search index');

        $options->registerCommand('index', 'Index all pages in the wiki');

        $options->registerCommand('createindex', 'Create a simple index named "'.$this->hlp->getConf('indexname').'".');
        $options->registerOption('clear', 'Remove existing index if any', 'c', false, 'createindex');

        $options->registerCommand('createmapping', 'Create the needed field mapping at the configured servers');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param DokuCLI_Options $options
     * @return void
     */
    protected function main(DokuCLI_Options $options) {
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
            case 'createmapping':
                $result = $this->hlp->createMapping();
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

$cli = new elasticsearch_cli();
$cli->run();