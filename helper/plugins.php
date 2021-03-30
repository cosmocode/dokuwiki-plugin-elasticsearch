<?php
/**
 * DokuWiki Plugin elasticsearch (Plugins Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 * @author  Anna Dabrowska <dabrowska@cosmocode.de>
 */

class helper_plugin_elasticsearch_plugins extends DokuWiki_Plugin
{
    /**
     * Update state of any changes for a page,
     * e.g. plugins' data related to the page
     *
     * @param string $id
     * @return bool
     */
    public function updateRefreshState($id) {
        $refreshStateFile = metaFN($id, '.elasticsearch_refresh');
        return io_saveFile($refreshStateFile, '');
    }
}
