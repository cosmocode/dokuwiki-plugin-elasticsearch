<?php
/**
 * Default settings for the elasticsearch plugin
 *
 * @author Kieback&Peter IT <it-support@kieback-peter.de>
 */

$conf['servers'] = '
    elasticsearch-1.kiebackpeter.kup:80
    elasticsearch-2.kiebackpeter.kup:80
';
$conf['indexname']      = 'test';
$conf['documenttype']   = 'wikipage';
$conf['indexondisplay'] = 1;
$conf['debug']          = 1;

