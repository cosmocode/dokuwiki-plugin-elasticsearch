<?php
/**
 * Default settings for the elasticsearch plugin
 *
 * @author Kieback&Peter IT <it-support@kieback-peter.de>
 */

$conf['servers']      = 'localhost:9200';
$conf['indexname']    = 'wiki';
$conf['documenttype'] = 'wikipage';
$conf['snippets']     = 'content';
$conf['searchSyntax'] = 1;
$conf['perpage']      = 20;
$conf['detectTranslation'] = 0;
$conf['debug']        = 0;
$conf['disableQuicksearch'] = 0;
