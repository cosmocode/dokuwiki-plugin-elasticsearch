<?php

namespace dokuwiki\plugin\elasticsearch\test;

use dokuwiki\plugin\elasticsearch\QueryParser;
use DokuWikiTest;

/**
 * QueryParser tests for the elasticsearch plugin
 *
 * @group plugin_elasticsearch
 * @group plugins
 */
class QueryParserTest extends DokuWikiTest
{

    public function provideParseParams()
    {
        return [
            'no keywords' => ['foo bar', '@', 'foo bar', []],
            'one keyword' => ['foo @bar', '@', 'foo', ['bar']],
            'two keywords' => ['foo @bar inter @baz other', '@', 'foo inter other', ['bar', 'baz']],
            'quoted keyword' => ['foo @bar @"baz bang"', '@', 'foo', ['bar', 'baz bang']],
            'quoted keyword only' => ['@"baz bang"', '@', '', ['baz bang']],
            'empty' => ['', '@', '', []],
        ];
    }

    /**
     * @dataProvider provideParseParams
     */
    public function testParseParams($query, $prefix, $expected, $keywords)
    {
        $parser = new QueryParser($query, []);
        $result = $this->callInaccessibleMethod($parser, 'parseParams', [&$query, $prefix]);
        $this->assertEquals($expected, $query);
        $this->assertEquals($keywords, $result);
    }

    public function testGetFilterDefaults()
    {
        $config = [
            'filter1' => [] // No initial values provided
        ];
        $parser = new QueryParser('foo bar', $config);
        $filter = $parser->getFilter('filter1');

        $this->assertEquals('filter1:', $filter['prefix']);
        $this->assertEquals('filter1', $filter['queryParam']);
        $this->assertEquals('filter1', $filter['label']);
        $this->assertEquals('filter1', $filter['fieldPath']);
        $this->assertEquals(25, $filter['limit']);
        $this->assertEquals([], $filter['values']);
        $this->assertEquals([], $filter['options']);
    }

    public function testInitializationFromQueryAndRequest()
    {
        $_REQUEST = [
            'filter1' => ['value3'],
            'filter2' => ['value4']
        ];

        $config = [
            'filter1' => ['prefix' => 'f1:', 'queryParam' => 'filter1'],
            'filter2' => []
        ];
        $parser = new QueryParser('foo f1:value1 filter2:value2', $config);

        $this->assertEquals(['value1', 'value3'], $parser->getValues('filter1'));
        $this->assertEquals(['value2', 'value4'], $parser->getValues('filter2'));
    }

    public function testRemoveFilterValue()
    {
        $config = [
            'filter1' => []
        ];
        $parser = new QueryParser('foo bar filter1:value1 filter1:value2', $config);
        $parser->removeFilterValue('filter1', 'value1');
        $this->assertEquals(['value2'], $parser->getValues('filter1'));
    }

    public function testAddFilterValue()
    {
        $config = [
            'filter1' => []
        ];
        $parser = new QueryParser('foo bar filter1:value1', $config);
        $parser->addFilterValue('filter1', 'value2');
        $this->assertEquals(['value1', 'value2'], $parser->getValues('filter1'));
    }

    public function testGetOptions()
    {
        $config = [
            'filter1' => [
                'options' => [
                    'opt1' => 'Option 1',
                    'opt2' => 'Option 2'
                ],
            ]
        ];
        $parser = new QueryParser('foo bar filter1:value1 filter1:opt2', $config);
        $options = $parser->getOptions('filter1');
        $this->assertEquals(['opt1' => 'Option 1', 'opt2' => 'Option 2', 'value1' => 'value1'], $options);
    }

    public function testGetValues()
    {
        $config = [
            'filter1' => []
        ];
        $parser = new QueryParser('foo bar filter1:value1 filter1:value2', $config);
        $this->assertEquals(['value1', 'value2'], $parser->getValues('filter1'));
    }

    public function testGetQuery()
    {
        $parser = new QueryParser('foo bar', []);
        $this->assertEquals('foo bar', $parser->getQuery());

        $parser = new QueryParser('foo bar filter1:blah', ['filter1' => []]);
        $this->assertEquals('foo bar', $parser->getQuery());
    }

    public function testGetFilterNames()
    {
        $config = [
            'filter1' => [],
            'filter2' => [],
            'filter3' => []
        ];
        $parser = new QueryParser('foo bar', $config);
        $this->assertEquals(['filter1', 'filter2', 'filter3'], $parser->getFilterNames());
    }
}
