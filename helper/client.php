<?php

/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Kieback&Peter IT <it-support@kieback-peter.de>
 */

use dokuwiki\Extension\Event;
use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\elasticsearch\Client;
use dokuwiki\plugin\elasticsearch\Exception;
use dokuwiki\plugin\elasticsearch\Query;

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

    protected ?Client $client = null;

    /**
     * Connect to the ElasticSearch server
     *
     * @return Client
     * @throws Exception
     */
    public function client(): Client
    {
        if (!$this->client instanceof Client) {
            $this->client = new Client(
                $this->getConf('servers'),
                $this->getConf('indexname'),
                $this->getConf('username'),
                $this->getConf('password')
            );
        }
        return $this->client;
    }

    /**
     * Create the index
     *
     * @param bool $clear rebuild index
     * @throws Exception
     */
    public function createIndex($clear = false)
    {
        $client = $this->client();

        if ($clear) {
            try {
                $client->call('', null, 'DELETE');
            } catch (Exception) {
                // ignore if index does not exist
            }
        }

        $client->call(
            '',
            [
                'mappings' => [
                    'properties' => $this->createMappings()
                ],
                'settings' => [
                    'index.highlight.max_analyzed_offset' => Query::MAX_ANALYZED_OFFSET,
                    'analysis' => $this->createAnalysis(),
                ]
            ],
            'PUT'
        ); // create index or throw exception
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
     * @return array The mapping properties
     */
    protected function createMappings(): array
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
            'namespace' => [
                'type' => 'text',
                'analyzer' => 'namespace_path', // to also match the namespaces below the searched one
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword', // the unsplit path, used for aggregating
                    ],
                ],
            ],
        ];

        $suggestProps = [
            'suggest' => [
                'type' => 'completion',
            ],
        ];

        // plugins can supply their own mappings: ['plugin' => ['type' => 'keyword'] ]
        $pluginProps = [];
        Event::createAndTrigger('PLUGIN_ELASTICSEARCH_CREATEMAPPING', $pluginProps);

        $props = array_merge($langProps, $aclProps, $mediaProps, $additionalProps, $suggestProps);
        foreach ($pluginProps as $fields) {
            $props = array_merge($props, $fields);
        }

        return $props;
    }

    /**
     * Define custom analyzers
     *
     * Namespaces are paths whose segments are separated by colons. The path_hierarchy
     * tokenizer indexes every parent path of a namespace as a token of its own, so that
     * a term query for a namespace also matches all the namespaces below it.
     *
     * @return array The analysis settings
     */
    protected function createAnalysis(): array
    {
        return [
            'tokenizer' => [
                'namespace_path' => [
                    'type' => 'path_hierarchy',
                    'delimiter' => ':',
                ],
            ],
            'analyzer' => [
                'namespace_path' => [
                    'tokenizer' => 'namespace_path',
                ],
            ],
        ];
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
                'type' => 'text',
                'fields' => [
                    $conf['lang'] => [
                        'type' => 'text',
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
