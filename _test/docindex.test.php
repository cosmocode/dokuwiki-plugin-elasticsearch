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

    /**
     * Configure the parser to use the Tika server and make sure it is reachable
     *
     * The Tika URL defaults to the service from the included docker-compose.yml
     * and can be overridden with the TIKA_URL environment variable.
     *
     * When Tika is unreachable, the test is skipped on dev machines but fails on GitHub Actions.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $tikaUrl = getenv('TIKA_URL') ?: 'http://localhost:9998/rmeta/text';
        if (!$this->isTikaAvailable($tikaUrl)) {
            $message = "Apache Tika is not reachable at $tikaUrl. " .
                'Start it with _test/pretest.sh or set TIKA_URL.';
            if (getenv('GITHUB_ACTIONS')) {
                $this->fail($message);
            }
            $this->markTestSkipped($message);
        }

        io_saveFile(DOKU_CONF . 'elasticsearch.conf', "docx $tikaUrl\n");
        $this->docparser = new \helper_plugin_elasticsearch_docparser();
    }

    /**
     * Check if the Tika server answers
     *
     * @param string $tikaUrl the configured Tika endpoint
     * @return bool
     */
    protected function isTikaAvailable($tikaUrl)
    {
        $http = new \dokuwiki\HTTP\DokuHTTPClient();
        $http->timeout = 5;
        return $http->get(preg_replace('/\/rmeta(\/\w+)?$/', '/tika', $tikaUrl)) !== false;
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
                    'language' => 'en',
                    'created' => '2020-01-27T12:40:04Z'
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
