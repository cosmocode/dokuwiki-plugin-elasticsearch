<?php
/**
 * Default settings for the elasticsearch plugin
 *
 * @author Kieback&Peter IT <it-support@kieback-peter.de>
 */

//$conf['fixme']    = 'FIXME';

$conf['elasticsearch_dsn'] = array(
    'servers' => array(
        array('host' => 'elasticsearch-1.kiebackpeter.kup', 'port' => 80, 'proxy' => ''),
        array('host' => 'elasticsearch-2.kiebackpeter.kup', 'port' => 80, 'proxy' => '')
    )
);

$conf['elasticsearch_indexname']      = 'test';
$conf['elasticsearch_documenttype']   = 'wikipage';
$conf['elasticsearch_indexondisplay'] = true;
$conf['elasticsearch_debug']          = true;

