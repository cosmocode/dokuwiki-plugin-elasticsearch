<?php

/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Kieback&Peter IT <it-support@kieback-peter.de>
 */

use dokuwiki\Extension\Plugin;
use Elastica\Client;
use splitbrain\phpcli\Exception;
use Elastica\Index;
use Elastica\Response;
use Elastica\Mapping;
use dokuwiki\Extension\Event;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Access to the Elastica client
 */
class helper_plugin_elasticsearch_client extends Plugin
{
    /** @var array Map of ISO codes to Elasticsearch analyzer names */
    protected const ANALYZERS = [
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
     * @var Client $elasticaClient
     */
    protected $elasticaClient;

    /**
     * Connects to the elastica servers and returns the client object
     *
     * @return Client
     */
    public function connect()
    {
        if (!is_null($this->elasticaClient)) return $this->elasticaClient;
        // security settings
        $username = $this->getConf('username');
        $password = $this->getConf('password');
        // parse servers config into DSN array
        $dsn = ['servers' => []];
        $servers = $this->getConf('servers');
        $lines   = explode("\n", $servers);
        foreach ($lines as $line) {
            [$host, $proxy] = array_pad(explode(',', $line, 2), 2, null);
            [$host, $port] = explode(':', $host, 2);
            $host = trim($host);
            $port = (int) trim($port);
            if (!$port) $port = 80;
            $proxy = trim($proxy);
            if (!$host) continue;
            $dsn['servers'][] = [
                'host' => $host,
                'port' => $port,
                'proxy' => $proxy,
                'username' => $username,
                'password' => $password
            ];
        }

        $this->elasticaClient = new Client($dsn);
        return $this->elasticaClient;
    }

    /**
     * Create the index
     *
     * @param bool $clear rebuild index
     * @throws Exception
     */
    public function createIndex($clear = false)
    {
        $client = $this->connect();
        $index = $client->getIndex($this->getConf('indexname'));

        if ($index->create([], $clear)->hasError()) {
            throw new Exception("Failed to create index!");
        }

        if ($this->createMappings($index)->hasError()) {
            throw new Exception("Failed to create field mappings!");
        }
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
        return self::ANALYZERS[$lang] ?? 'standard';
    }

    /**
     * Define mappings for custom fields
     *
     * All languages get their separate fields configured with appropriate linguistic analyzers.
     *
     * ACL fields require custom mappings as well, or else they could break the search. They
     * might contain word-split tokens such as underscores and so must not
     * be indexed using the standard text analyzer.
     *
     * Fields containing metadata are configured as sparsely as possible, no analyzers are necessary.
     *
     * Plugins may provide their own fields via PLUGIN_ELASTICSEARCH_CREATEMAPPING event.
     *
     * @param Index $index
     * @return Response
     */
    protected function createMappings(Index $index): Response
    {
        $langProps = $this->getLangProps();

        // document permissions
        $aclProps = [
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

        // differentiate media types
        $mediaProps = [
            'doctype' => [
                'type' => 'keyword',
            ],
            'mime' => [
                'type' => 'keyword',
            ],
            'ext' => [
                'type' => 'keyword',
            ],
        ];

        // additional fields which require something other than type text, standard analyzer
        $additionalProps = [
            'uri' => [
                'type' => 'text',
                'analyzer' => 'pattern', // because colons surrounded by letters are part of word in standard analyzer
            ],
        ];

        // plugins can supply their own mappings: ['plugin' => ['type' => 'keyword'] ]
        $pluginProps = [];
        Event::createAndTrigger('PLUGIN_ELASTICSEARCH_CREATEMAPPING', $pluginProps);

        $props = array_merge($langProps, $aclProps, $mediaProps, $additionalProps);
        foreach ($pluginProps as $fields) {
            $props = array_merge($props, $fields);
        }

        $mapping = new Mapping();
        $mapping->setProperties($props);
        return $mapping->send($index);
    }

    /**
     * Language mappings recognize languages defined by translation plugin
     *
     * @return array
     */
    protected function getLangProps()
    {
        global $conf;

        // default language
        $langprops = [
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
                $langprops['content']['fields'][$lang] = [
                    'type' => 'text',
                    'analyzer' => $this->getLanguageAnalyzer($lang)
                ];
            }
        }

        return $langprops;
    }
}

// vim:ts=4:sw=4:et:
