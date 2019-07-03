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
        'mini:micro:nano:stop	@ALL	0',
        'mini:*	@user	0',
        'mini:micro:*	mirco	0',
        'mini:micro:*	@user	8',
        'mini:micro:nano:*	@micra	8',
        'mini:micro:nano:*	@nana	8',
        'mini:micro:nano:stop	spots	8',
        'mini:micro:nano:*	@ALL	0',
    ];

    /**
     * Provide the test data
     *
     * @return array
     */
    public function data()
    {
        return [
            [
                'mini:micro:nano:stop',
                [
                    'groups_include' => [],
                    'groups_exclude' => ['ALL'],
                    'users_include' => ['spots'],
                    'users_exclude' => [],
                ]
            ],
            [
                'mini:micro:nano:start',
                [
                    'groups_include' => ['micra', 'nana'],
                    'groups_exclude' => ['ALL'],
                    'users_include' => [],
                    'users_exclude' => [],
                ]
            ],
            [
                'mini:micro:start',
                [
                    'groups_include' => ['user'],
                    'groups_exclude' => ['ALL'],
                    'users_include' => [],
                    'users_exclude' => ['mirco'],
                ]
            ],
        ];
    }


    /**
     * Check parsed ACLs for a given page
     *
     * @dataProvider data
     */
    public function test_acl($page, $expected)
    {
        /** @var helper_plugin_elasticsearch_acl $helper */
        $helper = plugin_load('helper', 'elasticsearch_acl');

        $raw = $helper->getPageACL($page, $this->acl);
        $actual = $helper->splitRules($raw);

        $this->assertEquals($expected, $actual);
    }
}
