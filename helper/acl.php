<?php
/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 * @author  Anna Dabrowska <dabrowska@cosmocode.de>
 */

// must be run within Dokuwiki
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
     * @return array
     */
    public function getPageACL($id) {
        $id    = cleanID($id);
        $rules = [];

        /** @var admin_plugin_acl $hlpACL */
        $hlpACL = plugin_load('admin', 'acl');
        if(method_exists($hlpACL, 'initAclConfig')) {
            $hlpACL->initAclConfig();
        } else {
            /** @deprecated Call for current stable release */
            $hlpACL->_init_acl_config();
        }

        // ACL lines as array
        $acl = $hlpACL->acl;
        ksort($acl);

        // check for exact id
        if (isset($acl[$id])) {
            // process matched rule
            $this->addRule($acl[$id], $rules);
            // stop traversing if we reached a total access block for @ALL
            if (isset($acl[$id]['@ALL'])) return $rules;
        }

        // walk namespace segments up
        $ns = $id;
        do {
            $ns = getNS($ns);
            // no namespace, check permissions for root
            if (!$ns && isset($acl['*'])) {
                $this->addRule($acl['*'], $rules);
                // stop traversing if we reached a total access block for @ALL
                if (isset($acl['*']['@ALL'])) {
                    $ns = false;
                    continue;
                }
            }
            // check namespace
            if (isset($acl[$ns . ':*'])) {
                $this->addRule($acl[$ns . ':*'], $rules);
                // stop traversing if we reached a total access block for @ALL
                if (isset($acl[$ns . ':*']['@ALL'])) $ns = false;
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
     * Omit access block for @ALL since it is assumed.
     *
     * @param array $rule Collection of access permissions for a certain location
     * @param array $rules Set of rules already
     */
    protected function addRule($rule, &$rules)
    {
        $localrules = [];

        foreach ($rule as $key => $perm) {
            // set read permissions for a given group or user
            // but skip if already defined for a more specific path
            if ($key !== '@ALL' && !array_key_exists($key, $rules)) {
                $localrules[$key] = $perm > AUTH_NONE;
            } elseif ($key === '@ALL' && $perm > AUTH_NONE) {
                $localrules[$key] = true;
            }
        }

        $rules = array_merge($rules, $localrules);
    }
}
