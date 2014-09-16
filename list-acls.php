#!/usr/bin/env php
<?php

$constants = array(
    'DOKU_INC', 'DOKU_PLUGIN', 'DOKU_CONF', 'DOKU_E_LEVEL',
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
$ini_path = defined('DOKU_INC') ? DOKU_INC : realpath(dirname(__FILE__) . '/../../../') . '/';

require_once($ini_path . 'inc/init.php');
require_once(DOKU_INC . 'inc/common.php');
require_once(DOKU_INC . 'inc/search.php');
require_once(DOKU_INC . 'inc/pageutils.php');
require_once DOKU_INC . 'inc/cliopts.php';
require_once(dirname(__FILE__) . '/action/indexing.php');


$g = getPageACL('en:support:tools:si_tool:backup_error');
var_dump($g);

function getPageACL($id) {
    global $AUTH_ACL;
    global $conf;

    $id    = cleanID($id);
    $ns    = getNS($id);
    $perms = array();

    $matches = preg_grep('/^' . preg_quote($id, '/') . '\s+/', $AUTH_ACL);
    if(count($matches)) {
        foreach($matches as $match) {
            $match = preg_replace('/#.*$/', '', $match); //ignore comments
            $acl   = preg_split('/\s+/', $match);
            if($acl[2] > AUTH_DELETE) $acl[2] = AUTH_DELETE; //no admins in the ACL!
            if(!isset($perms[$acl[1]])) $perms[$acl[1]] = $acl[2];
        }
    }
    //still here? do the namespace checks
    if($ns) {
        $path = $ns . ':\*';
    } else {
        $path = '\*'; //root document
    }
    do {
        $matches = preg_grep('/^' . $path . '\s+/', $AUTH_ACL);
        if(count($matches)) {
            foreach($matches as $match) {
                $match = preg_replace('/#.*$/', '', $match); //ignore comments
                $acl   = preg_split('/\s+/', $match);
                if($acl[2] > AUTH_DELETE) $acl[2] = AUTH_DELETE; //no admins in the ACL!
                if(!isset($perms[$acl[1]])) $perms[$acl[1]] = $acl[2];
            }
        }

        //get next higher namespace
        $ns = getNS($ns);

        if($path != '\*') {
            $path = $ns . ':\*';
            if($path == ':\*') $path = '\*';
        } else {
            //we did this already
            //break here
            break;
        }
    } while(1); //this should never loop endless
    $groups = array(str_replace('-', '', str_replace('@', '', strtolower(urldecode($conf['superuser'])))));
    foreach($perms as $group => $permission) {
        if($permission > AUTH_NONE) {
            $groups[] = str_replace('-', '', str_replace('@', '', strtolower(urldecode($group))));
            printf("%s = %s\n", $group, $permission);
        }
    }
    return $groups;
}