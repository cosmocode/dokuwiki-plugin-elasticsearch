<?php

namespace dokuwiki\plugin\elasticsearch\test;

use dokuwiki\plugin\elasticsearch\Query;
use DokuWikiTest;

/**
 * FIXME tests for the elasticsearch plugin
 *
 * @group plugin_elasticsearch
 * @group plugins
 */
class QueryTest extends DokuWikiTest
{

    public function testSetSimpleQuery()
    {
        $expected = [
            'query' => [
                'bool' => [
                    'must' => [
                        0 => [
                            'simple_query_string' => [
                                'query' => 'test',
                                'fields' => [
                                    0 => 'content',
                                    1 => 'title',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $query = new Query();
        $query->setSimpleQuery('test', ['content', 'title']);

        $this->assertEquals($expected, $query->query);
    }

    public function testSetAggregations()
    {
        $expected = [
            'aggs' => [
                'namespace' => [
                    'terms' => [
                        'field' => 'namespace.keyword',
                        'size' => 25,
                    ],
                ],
            ],
        ];

        $query = new Query();
        $query->setAggregations();

        $this->assertEquals($expected, $query->query);
    }

    public function testSetHighlights()
    {
        $expected = [
            'highlight' => [
                'pre_tags' => [
                    0 => 'ELASTICSEARCH_MARKER_IN',
                ],
                'post_tags' => [
                    0 => 'ELASTICSEARCH_MARKER_OUT',
                ],
                'fields' => [
                    'content' => (object)[],
                    'title' => (object)[],
                ],
            ]
        ];

        $query = new Query();
        $query->setHighlights('content');

        $this->assertEquals($expected, $query->query);
    }

    public function testSetPagination()
    {
        $expected = [
            'from' => 0,
            'size' => 10,
        ];

        $query = new Query();
        $query->setPagination(10);

        $this->assertEquals($expected, $query->query);

        $expected = [
            'from' => 10,
            'size' => 10,
        ];

        $query = new Query();
        $query->setPagination(10, 2);

        $this->assertEquals($expected, $query->query);
    }

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

        $query = new Query();
        $query->setACLs('andi', ['user']);

        $this->assertEquals($expected, $query->query);
    }

    public function setAddDateFilter()
    {
        $date = date('Y-m-d', strtotime('3 week ago'));

        $expected = [
            'query' => [
                'bool' => [
                    'must' => [
                        0 => [
                            'range' => [
                                'date' => [
                                    'gte' => $date,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $query = new Query();
        $query->addDateFilter('week', 3);

        $this->assertEquals($expected, $query->query);
    }

    public function testAddLanguageFilter()
    {
        $expected = [
            'query' => [
                'bool' => [
                    'must' => [
                        0 => [
                            'match' => [
                                'language' => 'de,en',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $query = new Query();
        $query->addLanguageFilter(['de', 'en']);

        $this->assertEquals($expected, $query->query);
    }

    public function testAddNamespaceFilter()
    {
        $expected = [
            'post_filter' => [
                'bool' => [
                    'should' => [
                        0 => [
                            'term' => [
                                'namespace' => [
                                    'value' => 'wiki',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                        1 => [
                            'term' => [
                                'namespace' => [
                                    'value' => 'playground',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        ];

        $query = new Query();
        $query->addNamespaceFilter(['wiki', 'playground']);

        $this->assertEquals($expected, $query->query);
    }
}
