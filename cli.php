#!/usr/bin/php
<?php
if(!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__).'/../../../').'/');
define('NOSESSION', 1);
require_once(DOKU_INC.'inc/init.php');
require_once dirname(__FILE__) . '/vendor/autoload.php';

class elasticsearch_cli extends DokuCLI {

    /**
     * Register options and arguments on the given $options object
     *
     * @param DokuCLI_Options $options
     * @return void
     */
    protected function setup(DokuCLI_Options $options) {
        $options->setHelp('Manage the elastic search index');
        $options->registerCommand('index', 'Index all pages in the wiki');
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
            case 'createmapping':
                /** @var helper_plugin_elasticsearch_client $hlp */
                $hlp = plugin_load('helper', 'elasticsearch_client');
                $result = $hlp->createMapping();
                if($result->hasError()){
                    $this->error($result->getError());
                } else {
                    $this->success('Mapping created');
                }
                break;
            default:
                $this->error('No command provided');
                exit(1);
        }


    }


}

$cli = new elasticsearch_cli();
$cli->run();