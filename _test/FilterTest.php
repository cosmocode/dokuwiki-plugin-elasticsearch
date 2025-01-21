<?php

namespace dokuwiki\plugin\elasticsearch\test;

use dokuwiki\plugin\elasticsearch\Filter;
use DokuWikiTest;

/**
 * Filter tests for the elasticsearch plugin
 *
 * @group plugin_elasticsearch
 * @group plugins
 */
class FilterTest extends DokuWikiTest
{
    public function testGetFilterDefaults()
    {
        $config = [
            'filter1' => [] // No initial values provided
        ];
        $filter = new Filter('filter1', $config);

        $this->assertEquals('filter1:', $filter->getPrefix());
        $this->assertEquals('filter1', $filter->getQueryParam());
        $this->assertEquals('filter1', $filter->getLabel());
        $this->assertEquals('filter1', $filter->getFieldName());
        $this->assertEquals('filter1.keyword', $filter->getFieldPath());
        $this->assertEquals(25, $filter->getLimit());
        $this->assertEquals([], $filter->getValues());
        $this->assertEquals([], $filter->getOptions());
        $this->assertTrue($filter->isAndQuery());
    }


}
