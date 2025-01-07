<?php

namespace dokuwiki\plugin\elasticsearch\test;

use DokuWikiTest;

/**
 * FIXME tests for the elasticsearch plugin
 *
 * @group plugin_elasticsearch
 * @group plugins
 */
class QueryTest extends DokuWikiTest
{

    /**
     * @todo this is the query the old ruflin based setup generated, however it seems to be more complicated than needed?
     */
    public function testSetACLs()
    {
        $expected = [
            'query' => [
                'bool' => [
                    'must' => [
                        0 => [
                            'bool' => [
                                'should' => [
                                    0 => [
                                        'term' => [
                                            'groups_include' => [
                                                'value' => 'ALL',
                                                'boost' => 1.0,
                                            ],
                                        ],
                                    ],
                                    1 => [
                                        'term' => [
                                            'groups_include' => [
                                                'value' => 'user',
                                                'boost' => 1.0,
                                            ],
                                        ],
                                    ],
                                    2 => [
                                        'bool' => [
                                            'must' => [
                                                0 => [
                                                    'term' => [
                                                        'users_include' =>
                                                            [
                                                                'value' => 'andi',
                                                                'boost' => 1.0,
                                                            ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'should' => [
                        0 => [
                            'bool' => [
                                'must_not' => [
                                    0 => [
                                        'bool' => [
                                            'should' => [
                                                0 => [
                                                    'term' => [
                                                        'groups_exclude' => [
                                                            'value' => 'ALL',
                                                            'boost' => 1.0,
                                                        ],
                                                    ],
                                                ],
                                                1 => [
                                                    'term' => [
                                                        'groups_exclude' => [
                                                            'value' => 'user',
                                                            'boost' => 1.0,
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'must_not' => [
                        0 => [
                            'term' => [
                                'users_exclude' => [
                                    'value' => 'andi',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $query = new \dokuwiki\plugin\elasticsearch\Query();
        $query->setACLs('andi', ['user']);

        $this->assertEquals($expected, $query->query);
    }
}
