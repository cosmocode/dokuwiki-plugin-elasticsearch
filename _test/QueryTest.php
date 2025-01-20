<?php

namespace dokuwiki\plugin\elasticsearch\test;

use dokuwiki\plugin\elasticsearch\Filter;
use dokuwiki\plugin\elasticsearch\Query;
use DokuWikiTest;

/**
 * Query tests for the elasticsearch plugin
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
                'max_analyzed_offset' => 1000000,
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

    public function testSetSuggest()
    {
        $expected = [
            'suggest' => [
                'phrase' => [
                    'text' => 'test query',
                    'phrase' => [
                        'field' => 'content',
                        'size' => 1,
                        'gram_size' => 3,
                        'direct_generator' => [
                            [
                                'field' => 'content',
                                'suggest_mode' => 'popular',
                                'min_word_length' => 3,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $query = new Query();
        $query->setSuggest('test query', 'content');

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

    public function testAddAndFilterTerms()
    {
        $expect = [
            'query' => [
                'bool' => [
                    'must' => [
                        0 => [
                            'bool' => [
                                'should' => [
                                    0 => [
                                        'term' => [
                                            'tagging' => [
                                                'value' => 'tag1',
                                                'boost' => 1.0,
                                            ],
                                        ],
                                    ],
                                    1 => [
                                        'term' => [
                                            'tagging' => [
                                                'value' => 'tag2',
                                                'boost' => 1.0,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ],
            ],
            'aggs' => [
                'tagging' => [
                    'terms' => [
                        'field' => 'tagging.keyword',
                        'size' => 20,
                    ],
                ],
            ],
        ];

        $filter = new Filter('tagging', ['limit' => 20]);
        $filter->addValues(['tag1', 'tag2']);

        $query = new Query();
        $query->addFilter($filter);

        $this->assertEquals($expect, $query->query);
    }

    public function testAddOrFilterTerms()
    {
        $expected = [
            'post_filter' => [
                'bool' => [
                    'must' => [
                        0 => [
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
                            ]
                        ],
                    ]
                ]
            ],
            'aggs' => [
                'namespace' => [
                    'terms' => [
                        'field' => 'namespace.keyword',
                        'size' => 20,
                    ],
                ],
            ],
        ];

        $filter = new Filter('namespace', ['limit' => 20, 'isAndQuery' => false]);
        $filter->addValues(['wiki', 'playground']);

        $query = new Query();
        $query->addFilter($filter);

        $this->assertEquals($expected, $query->query);
    }
}
