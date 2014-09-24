<?php
/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_elasticsearch_form extends DokuWiki_Plugin {

    /**
     * Replacement for the standard search form
     *
     * @return bool always true
     */
    public function tpl() {
        global $lang;
        global $ACT;
        global $QUERY;

        print '<form action="' . wl() . '" accept-charset="utf-8" class="search" id="dw__search" method="get" role="search"><div class="no">';
        print '<input type="hidden" name="do" value="elasticsearch" />';
        print '<input type="text" ';
        if($ACT == 'elasticsearch') print 'value="' . htmlspecialchars($QUERY) . '" ';
        print 'accesskey="f" name="id" class="edit" title="[F]" />';
        print '<input type="submit" value="' . $lang['btn_search'] . '" class="button" title="' . $lang['btn_search'] . '" />';
        print '</div></form>';
        return true;
    }
}