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

    protected $acl = [
        '*	@user	8',
        '*	@ALL	0',
        'macro:*	@user	0',
        'macro:*	@mino	8',
        'mini:*	@user	0',
        'mini:micro:*	mirco	0',
        'mini:micro:*	@user	8',
        'mini:micro:nano:*	@micra	8',
        'mini:micro:nano:*	@nana	8',
        'mini:micro:nano:*	@ALL	0',
        'mini:micro:nano:stop	spots	8',
        'mini:micro:nano:stop	@ALL	0',
        'super:*	@ALL	8',
        'super:mini:*	@ALL	0',
        'super:mini:stop	@nana	8',
    ];

    /**
     * Expected permissions
     *
     * @return array
     */
    public function dataSanity()
    {
        return [
            ['mini:micro:nano:stop', 'user', 'group', 0],
            ['mini:micro:nano:stop', 'spots', 'group', 8],
            ['mini:micro:nano:start', 'user', 'micra', 8],
            ['mini:micro:nano:start', 'user', 'nana', 8],
            ['mini:micro:start', 'user', 'user', 8],
            ['mini:micro:start', 'mirco', 'user', 8],
            ['mini:micro:start', 'mirco', 'group', 0],
            ['super:start', 'user', 'ALL', 8],
            ['super:start', 'user', 'group', 8],
            ['super:mini:start', 'user', 'nana', 0],
            ['super:mini:stop', 'user', 'nana', 8],
        ];
    }

    /**
     * Provide the test data for the ACL check
     *
     * @return array
     */
    public function dataACL()
    {
        return [
            [
                'mini:micro:nano:stop',
                [
                    'groups_include' => [],
                    'groups_exclude' => [],
                    'users_include' => ['spots'],
                    'users_exclude' => [],
                ]
            ],
            [
                'mini:micro:nano:start',
                [
                    'groups_include' => ['micra', 'nana'],
                    'groups_exclude' => [],
                    'users_include' => [],
                    'users_exclude' => [],
                ]
            ],
            [
                'mini:micro:start',
                [
                    'groups_include' => ['user'],
                    'groups_exclude' => [],
                    'users_include' => [],
                    'users_exclude' => ['mirco'],
                ]
            ],
            [
                'super:start',
                [
                    'groups_include' => ['ALL'],
                    'groups_exclude' => [],
                    'users_include' => [],
                    'users_exclude' => [],
                ]
            ],
            [
                'super:mini:start',
                [
                    'groups_include' => [],
                    'groups_exclude' => [],
                    'users_include' => [],
                    'users_exclude' => [],
                ]
            ],
            [
                'super:mini:stop',
                [
                    'groups_include' => ['nana'],
                    'groups_exclude' => [],
                    'users_include' => [],
                    'users_exclude' => [],
                ]
            ],
        ];
    }

    /**
     * Run "normal" ACL checks to make sure we do expect the correct thing to happen
     *
     * This is mainly to sanity check the expectations we have for the results in the ACL checks
     *
     * @dataProvider dataSanity
     * @param string $page
     * @param string $user
     * @param string $group
     * @param int $expected
     */
    public function testSanity($page, $user, $group, $expected)
    {
        global $AUTH_ACL;
        $AUTH_ACL = $this->acl;

        $this->assertSame($expected, auth_aclcheck($page, $user, [$group]));
    }

    /**
     * Check parsed ACLs for a given page
     *
     * @dataProvider dataACL
     * @param string $page
     * @param array $expected
     */
    public function testACL($page, $expected)
    {
        global $AUTH_ACL;
        $AUTH_ACL = $this->acl;

        /** @var helper_plugin_elasticsearch_acl $helper */
        $helper = plugin_load('helper', 'elasticsearch_acl');

        $raw = $helper->getPageACL($page);
        $actual = $helper->splitRules($raw);

        $this->assertEquals($expected, $actual);
    }
}
