<?php
/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 * @author  Anna Dabrowska <dabrowska@cosmocode.de>
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
     * Returns a full list of (read) permissions for users and groups whose access to a given page
     * is defined in the ACLs.
     * Traverses the whole rule set and resolves overrides and exclusions.
     *
     * @param string $id Page id
     * @param array $aclArray Only provided by tests
     * @return array
     */
    public function getPageACL($id, $aclArray = []) {
        global $conf;
        global $AUTH_ACL;
        // FIXME global $AUTH_ACL is missing in CLI context
        if (empty($AUTH_ACL)) {
            auth_setup();
        }
        $AUTH_ACL = array_merge($AUTH_ACL, $aclArray);
        $id    = cleanID($id);

        $rules = [];

        /** @var admin_plugin_acl $hlpACL */
        $hlpACL = plugin_load('admin', 'acl');
        $hlpACL->_init_acl_config();

        // ACL lines as array
        $acl = $hlpACL->acl;
        ksort($acl);

        $aclKeys = array_keys($acl);

        // check for exact id
        if (in_array($id, $aclKeys)) {
            // process matched rule
            $this->addRule($acl[$id], $rules);
        }

        // walk namespace segments up
        $ns = $id;
        do {
            $ns = getNS($ns);
            // no namespace, check permissions for root
            if (!$ns && in_array('*', $aclKeys)) {
                $this->addRule($acl['*'], $rules);
                continue;
            }
            // check namespace
            if (in_array($ns . ':*', $aclKeys)) {
                $this->addRule($acl[$ns . ':*'], $rules);
            }
        } while ($ns);

        return $rules;
    }

    /**
     * Splits a rule set into query-digestible chunks
     *
     * @param array $rules
     * @return array
     */
    public function splitRules($rules)
    {
        $splitACL = [
            'groups_include' => [],
            'groups_exclude' => [],
            'users_include' => [],
            'users_exclude' => [],
        ];

        foreach ($rules as $key => $perm) {
            if (strpos($key, '@') === 0) {
                $type = $perm ? 'groups_include' : 'groups_exclude';
            } else {
                $type = $perm ? 'users_include' : 'users_exclude';
            }
            $splitACL[$type][] = ltrim($key, '@');
        }

        return $splitACL;
    }

    /**
     * Adds specific access rules to a rule set covering a full namespace path.
     *
     * @param array $rule Collection of access permissions for a certain location
     * @param array $rules Set of rules already
     */
    protected function addRule($rule, &$rules)
    {
        $localrules = [];

        foreach ($rule as $key => $perm) {
            // read permissions for a given group or user
            // set only if not yet defined and there are no total access blocks on lower levels
            if (!array_key_exists($key, $rules) && (!isset($rules['@ALL']) || $rules['@ALL'] !== false)) {
                $localrules[$key] = $perm > AUTH_NONE;
            }
        }

        $rules = array_merge($rules, $localrules);
    }
}
