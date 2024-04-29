<?php
/**
 * Default settings for the elasticsearch plugin
 *
 * @author Kieback&Peter IT <it-support@kieback-peter.de>
 */

$conf['servers']      = 'localhost:9200';
$conf['username']    = '';
$conf['password']    = '';
$conf['indexname']    = 'wiki';
$conf['snippets']     = 'content';
$conf['searchSyntax'] = 1;
$conf['perpage']      = 20;
$conf['detectTranslation'] = 0;
$conf['disableQuicksearch'] = 0;
$conf['maxAnalyzedOffset'] = 1000000;
