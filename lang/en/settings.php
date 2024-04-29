<?php
/**
 * english language file for elasticsearch plugin
 *
 * @author Kieback&Peter IT <it-support@kieback-peter.de>
 */

$lang['servers']      = 'ElasticSearch servers: one per line, add port number after a colon, give optional proxy after a comma';
$lang['username']     = 'Elastic username is required if security is enabled in Elastic (default since version 8)';
$lang['password']     = 'Elastic password is required if security is enabled in Elastic (default since version 8)';
$lang['indexname']    = 'Index name to use, must exist or can be created with the cli.php tool.';
$lang['snippets']     = 'Text to show in search result snippets';
$lang['searchSyntax'] = 'Search in wiki syntax in addition to page content';
$lang['perpage']      = 'How many hits to show per page';
$lang['detectTranslation'] = 'Translation plugin support: search in current language namespace by default';
$lang['disableQuicksearch'] = 'Disable quick search (page id suggestions)';
$lang['maxAnalyzedOffset'] = 'Maximum amount of data per page/media file considered for search result highlighting. You have to recreate your index if you change this value.';
