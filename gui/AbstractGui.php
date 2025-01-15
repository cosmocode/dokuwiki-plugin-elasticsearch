<?php

namespace dokuwiki\plugin\elasticsearch\gui;


use dokuwiki\plugin\elasticsearch\QueryParser;

abstract class AbstractGui
{
    /** @var array Search results */
    protected array $results;

    /** @var QueryParser The parsed query and filters */
    protected QueryParser $query;

    /** @var helper_plugin_elasticsearch_client for accessing plugin mechanisms */
    protected $helper;


    /**
     * @param array $results The raw search results from ElasticSearch
     * @param QueryParser $parsedQuery The parsed query and filters
     */
    public function __construct(array $results, QueryParser $parsedQuery)
    {
        $this->results = $results;
        $this->query = $parsedQuery;
        $this->helper = plugin_load('helper', 'elasticsearch_client');
    }


    /**
     * Return the GUI output
     */
    abstract public function render(): string;

    /**
     * @return LinkBuilder
     */
    public function linkBuilder(): LinkBuilder
    {
        return new LinkBuilder($this->query);
    }

    /**
     * Get a translated string
     *
     * @param string $msg
     * @return string
     */
    protected function getLang($msg) {
        return $this->helper->getLang($msg);
    }

    /**
     * Get a configuration value
     *
     * @param string $conf
     * @return mixed
     */
    protected function getConf($conf) {
        return $this->helper->getConf($conf);
    }
}
