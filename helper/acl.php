<?php
/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
use dokuwiki\Form\Form;

if(!defined('DOKU_INC')) die();

/**
 * User-independent ACL methods
 */
class helper_plugin_elasticsearch_acl extends DokuWiki_Plugin
{
    /**
     * Lists all groups that have at least read permissions on the given page
     *
     * @param $id
     * @param array $aclArray
     * @return array
     */
    public function getPageACL($id, $aclArray = []) {

        if (!empty($aclArray)) {
            $AUTH_ACL = $aclArray;
        } else {
            // FIXME why is global $AUTH_ACL null???
            auth_setup();
            global $AUTH_ACL;
        }

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
                // FIXME debug log
//                $this->log(sprintf("%s = %s", $group, $permission));
            }
        }
        return $groups;
    }

}
