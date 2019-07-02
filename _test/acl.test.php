<?php
/**
 * Helper tests for the elasticsearch plugin
 *
 * @group plugin_elasticsearch
 * @group plugins
 */
class helper_plugin_elasticsearch_test extends DokuWikiTest
{
    protected $pluginsEnabled = ['elasticsearch'];

    /**
     * Parsed ACLs for a given page
     */
    public function test_acl()
    {
        $acl = [
            '*	@user	8',
            '*	@ALL	0',
            'macro:*	@user	0',
            'macro:*	@mino	8',
            'mini:micro:nano:stop	@ALL	0',
            'mini:*	@user	0',
            'mini:micro:*	mirco	0',
            'mini:micro:*	@user	8',
            'mini:micro:nano:*	@micra	8',
            'mini:micro:nano:*	@nana	8',
            'mini:micro:nano:stop	spots	8',
            'mini:micro:nano:*	@ALL	0',
        ];

        /** @var helper_plugin_elasticsearch_acl $helper */
        $helper = plugin_load('helper', 'elasticsearch_acl');

        $expected = [
            'groups_include' => [],
            'groups_exclude' => ['ALL'],
            'users_include' => ['spots'],
            'users_exclude' => [],
        ];

        $raw = $helper->getPageACL('mini:micro:nano:stop', $acl);
        $actual = $helper->splitRules($raw);

        $this->assertEquals($expected, $actual);

        $expected = [
            'groups_include' => ['micra', 'nana'],
            'groups_exclude' => ['ALL'],
            'users_include' => [],
            'users_exclude' => [],
        ];

        $raw = $helper->getPageACL('mini:micro:nano:start', $acl);
        $actual = $helper->splitRules($raw);

        $this->assertEquals($expected, $actual);

        $expected = [
            'groups_include' => ['user'],
            'groups_exclude' => ['ALL'],
            'users_include' => [],
            'users_exclude' => ['mirco'],
        ];

        $raw = $helper->getPageACL('mini:micro:start', $acl);
        $actual = $helper->splitRules($raw);

        $this->assertEquals($expected, $actual);

    }
}
