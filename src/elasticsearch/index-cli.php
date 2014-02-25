#!/usr/bin/env php
<?php

$constants = array( 'DOKU_INC', 'DOKU_PLUGIN', 'DOKU_CONF', 'DOKU_E_LEVEL',
    'DOKU_REL', 'DOKU_URL', 'DOKU_BASE', 'DOKU_BASE', 'DOKU_LF', 'DOKU_TAB',
    'DOKU_COOKIE', 'DOKU_SCRIPT', 'DOKU_TPL', 'DOKU_TPLINC'
);
foreach($constants as $const) {
    if(!defined($const)) {
        $env_var = getenv($const);
        if($env_var !== false) {
            define($const, $env_var);
        }
    }
}
$ini_path = defined('DOKU_INC') ? DOKU_INC : realpath(dirname(__FILE__).'/../../../').'/';

require_once($ini_path.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once DOKU_INC.'inc/cliopts.php';
require_once(dirname(__FILE__).'/action/indexing.php');

function index_all_pages(){
    global $conf, $ID;
    $data = array();
    search($data,$conf['datadir'], 'search_allpages', array('skipacl'));

    foreach($data as $val) {
        $ID = $val['id'];
        trigger_event(TPL_CONTENT_DISPLAY, $val['id']);
        sleep(1);
    }
}

index_all_pages();
