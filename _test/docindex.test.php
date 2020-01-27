<?php

/**
 * Tests for document indexing by elasticsearch plugin
 *
 * @group plugin_elasticsearch_docindex
 * @group plugin_elasticsearch
 * @group plugins
 */
class docindex_elasticsearch_test extends DokuWikiTest
{
    protected $pluginsEnabled = ['elasticsearch'];

    /**
     * @var \helper_plugin_elasticsearch_docparser
     */
    protected $docparser;

    public function setUp()
    {
        parent::setUp();
        $this->docparser = new \helper_plugin_elasticsearch_docparser();

    }

    /**
     * Provides test data
     *
     * @return array
     */
    public function filesData()
    {
        return [
            [
                __DIR__ . '/documents/test.docx',
                [
                    'title' => 'test.docx',
                    'content' => 'Elastic test .docx

Test file in .docx format',
                    'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'ext' => 'docx',
                    'lang' => 'en',
                ]
            ],
        ];
    }

    /**
     * Tika parsing of .docx files
     *
     * @dataProvider filesData
     * @param $file
     * @param $expected
     */
    public function testDocx($file, $expected)
    {
        $this->assertEquals($expected, $this->docparser->parse($file));
    }

}
