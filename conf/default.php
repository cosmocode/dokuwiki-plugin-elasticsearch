<?php
/**
 * Default settings for the elasticsearch plugin
 *
 * @author Kieback&Peter IT <it-support@kieback-peter.de>
 */

$conf['servers']      = 'localhost:9200';
$conf['indexname']    = 'wiki';
$conf['documenttype'] = 'wikipage';
$conf['mediaparsers'] = 'pdf;/usr/bin/pdftotext
doc;http://givemetext.okfnlabs.org/tika/rmeta
docx;http://givemetext.okfnlabs.org/tika/rmeta';
$conf['snippets']     = 'content';
$conf['perpage']      = 20;
$conf['debug']        = 0;

