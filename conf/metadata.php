<?php

/**
 * Options for the elasticsearch plugin
 *
 * @author Kieback&Peter IT <it-support@kieback-peter.de>
 */

$meta['servers']      = array();
$meta['username']    = array('string');
$meta['password']    = array('password');
$meta['indexname']    = array('string');
$meta['snippets']     = array('multichoice', '_choices' => array('content','abstract'));
$meta['searchSyntax'] = array('onoff');
$meta['perpage']      = array('numeric', '_min' => 1);
$meta['detectTranslation'] = array('onoff');
$meta['disableQuicksearch'] = array('onoff');
