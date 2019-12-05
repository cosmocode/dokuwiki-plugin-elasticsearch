<?php
/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Kieback&Peter IT <it-support@kieback-peter.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once dirname(__FILE__) . '/../vendor/autoload.php';

/**
 * Access to the Elastica client
 */
class helper_plugin_elasticsearch_client extends DokuWiki_Plugin {

    /** @var array Map of ISO codes to Elasticsearch analyzer names */
    const ANALYZERS = [
        'ar' => 'arabic',
        'bg' => 'bulgarian',
        'bn' => 'bengali',
        'ca' => 'catalan',
        'cs' => 'czech',
        'da' => 'danish',
        'de' => 'german',
        'el' => 'greek',
        'en' => 'english',
        'es' => 'spanish',
        'eu' => 'basque',
        'fa' => 'persian',
        'fi' => 'finnish',
        'fr' => 'french',
        'ga' => 'irish',
        'gl' => 'galician',
        'hi' => 'hindi',
        'hu' => 'hungarian',
        'hy' => 'armenian',
        'id' => 'indonesian',
        'it' => 'italian',
        'lt' => 'lithuanian',
        'lv' => 'latvian',
        'nl' => 'dutch',
        'no' => 'norwegian',
        'pt' => 'portuguese',
        'ro' => 'romanian',
        'ru' => 'russian',
        'sv' => 'swedish',
        'th' => 'thai',
        'tr' => 'turkish',
        ];
    /**
     * @var \Elastica\Client $elasticaClient
     */
    protected $elasticaClient = null;

    /**
     * Connects to the elastica servers and returns the client object
     *
     * @return \Elastica\Client
     */
    public function connect() {
        if(!is_null($this->elasticaClient)) return $this->elasticaClient;

        // parse servers config into DSN array
        $dsn = ['servers' => []];
        $servers = $this->getConf('servers');
        $lines   = explode("\n", $servers);
        foreach($lines as $line) {
            list($host, $proxy) = explode(',', $line, 2);
            list($host, $port) = explode(':', $host, 2);
            $host = trim($host);
            $port = (int) trim($port);
            if(!$port) $port = 80;
            $proxy = trim($proxy);
            if(!$host) continue;
            $dsn['servers'][] = compact('host', 'port', 'proxy');
        }

        $this->elasticaClient = new \Elastica\Client($dsn);
        return $this->elasticaClient;
    }

    /**
     * Create the index
     *
     * @param bool $clear rebuild index
     * @return \Elastica\Response
     */
    public function createIndex($clear=false) {
        $client = $this->connect();
        $index = $client->getIndex($this->getConf('indexname'));

        $index->create([], $clear);

        return $this->mapAccessFields($index);
    }

    /**
     * Create the field mapping: language analyzers for the content field
     *
     * @return \Elastica\Response
     */
    public function createLanguageMapping() {
        global $conf;

        $client = $this->connect();
        $index = $client->getIndex($this->getConf('indexname'));
        $type = $index->getType($this->getConf('documenttype'));

        // default language
        $props = [
            'content' => [
                'type'  => 'text',
                'fields' => [
                    $conf['lang'] => [
                        'type'  => 'text',
                        'analyzer' => $this->getLanguageAnalyzer($conf['lang'])
                    ],
                ]
            ]
        ];

        // other languages as configured in the translation plugin
        /** @var helper_plugin_translation $transplugin */
        $transplugin = plugin_load('helper', 'translation');
        if ($transplugin) {
            $translations = array_diff(array_filter($transplugin->translations), [$conf['lang']]);
            if ($translations) foreach ($translations as $lang) {
                $props['content']['fields'][$lang] = [
                    'type' => 'text',
                    'analyzer' => $this->getLanguageAnalyzer($lang)
                ];
            }
        }

        $mapping = new \Elastica\Type\Mapping();
        $mapping->setType($type);
        $mapping->setProperties($props);
        $response = $mapping->send();
        return $response;
    }

    /**
     * Get the correct analyzer for the given language code
     *
     * Returns the standard analalyzer for unknown languages
     *
     * @param string $lang
     * @return string
     */
    protected function getLanguageAnalyzer($lang)
    {
        if (isset(self::ANALYZERS[$lang])) return self::ANALYZERS[$lang];
        return 'standard';
    }

    /**
     * Define special mappings for ACL fields
     *
     * Standard mapping could break the search because ACL fields
     * might contain word-split tokens such as underscores and so must not
     * be indexed using the standard text analyzer.
     *
     * @param \Elastica\Index $index
     * @return \Elastica\Response
     */
    protected function mapAccessFields(\Elastica\Index $index): \Elastica\Response
    {
        $type = $index->getType($this->getConf('documenttype'));
        $props = [
            'groups_include' => [
                'type' => 'keyword',
            ],
            'groups_exclude' => [
                'type' => 'keyword',
            ],
            'users_include' => [
                'type' => 'keyword',
            ],
            'users_exclude' => [
                'type' => 'keyword',
            ],
        ];

        $mapping = new \Elastica\Type\Mapping();
        $mapping->setType($type);
        $mapping->setProperties($props);
        return $mapping->send();
    }
}

// vim:ts=4:sw=4:et:
