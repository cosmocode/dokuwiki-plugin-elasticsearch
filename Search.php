<?php

namespace dokuwiki\plugin\elasticsearch;

use dokuwiki\Extension\Event;

class Search
{
    /** @var \helper_plugin_elasticsearch_client for accessing plugin mechanisms */
    protected $client;

    /** @var QueryParser The parsed query and filter representation */
    protected QueryParser $query;

    /**
     * Search constructor.
     *
     * @param string $querystring the query string (usually $QUERY)
     */
    public function __construct(string $querystring)
    {
        $this->client = plugin_load('helper', 'elasticsearch_client');
        $this->query = new QueryParser($querystring, $this->initFilters());
        $this->initDefaultLanguage();
    }

    /**
     * Get the filter configurations
     *
     * Plugins can add their own filters or modify the default ones
     *
     * @triggers PLUGIN_ELASTICSEARCH_FILTERS
     * @return array
     */
    protected function initFilters(): array
    {
        $filters = [];
        $event = new Event('PLUGIN_ELASTICSEARCH_FILTERS', $filters);
        if ($event->advise_before()) {
            $filters['namespace'] = $this->createNamespaceFilterConfig();
            $filters['media'] = $this->createMediaFilterConfig();
            $filters['language'] = $this->createLanguageFilterConfig();
            $filters = array_filter($filters); // remove null values
        }
        $event->advise_after();
        return $filters;
    }

    /**
     * Get the fields to search in
     *
     * Plugins can add their own fields or modify the default ones
     *
     * @triggers PLUGIN_ELASTICSEARCH_SEARCHFIELDS
     * @return array
     */
    protected function initSearchFields(): array
    {
        $fields = [];

        $event = new Event('PLUGIN_ELASTICSEARCH_SEARCHFIELDS', $fields);
        if ($event->advise_before()) {
            // default fields
            $fields[] = 'title*';
            $fields[] = 'abstract*';
            $fields[] = 'content*';
            $fields[] = 'uri';
            // search in syntax?
            if ($this->getConf('searchSyntax')) {
                $fields[] = 'syntax*';
            }
        }
        $event->advise_after();

        return $fields;
    }

    /**
     * Add the current language as default for search, when configured
     * @return void
     */
    public function initDefaultLanguage()
    {
        global $ID;

        if (!$this->getConf('detectTranslation')) return;
        if (!$this->query->hasFilter('language')) return;
        $filter = $this->query->getFilter('language');
        if ($filter->getValues() !== []) return;

        /** @var \helper_plugin_translation $transplugin */
        $transplugin = plugin_load('helper', 'translation');

        $ns = getNS($ID);
        $topNs = strtok($ns, ':');
        if ($topNs && in_array($topNs, $transplugin->translations)) {
            $filter->addValue($topNs);
        } elseif ($transplugin->defaultlang === '') {
            // for empty default language, use the real language code
            $filter->addValue($transplugin->realLC(''));
        }
    }

    /**
     * Execute the search and return the results
     *
     * @throws Exception when the search fails
     */
    public function search(): array
    {
        global $INFO;
        global $INPUT;

        // initialize the Query
        $queryBuilder = new Query();
        $queryBuilder->setSimpleQuery($this->query->getQuery(), $this->initSearchFields());
        $queryBuilder->setHighlights($this->getConf('snippets'));
        $queryBuilder->setPagination($this->getConf('perpage'), $INPUT->int('p', 1, true));
        $queryBuilder->setSuggest($this->query->getQuery(), 'content');

        if (!$INFO['isadmin']) {
            $queryBuilder->setACLs($INPUT->server->str('REMOTE_USER'), $INFO['userinfo']['grps'] ?? []);
        }
        $queryBuilder->addDateFilter($INPUT->str('min'));

        // add filters
        foreach ($this->query->getFilters() as $filter) {
            $queryBuilder->addFilter($filter);
        }

        return $this->client->client()->call('_search', $queryBuilder->query);
    }

    /**
     * Do an autocomplete search
     *
     * This is similar to the search but only returns suggestions
     *
     * @return array
     * @throws Exception
     */
    public function autocomplete(): array
    {
        global $INFO;
        global $INPUT;

        // initialize the Query
        $queryBuilder = new Query();
        $queryBuilder->setAutocomplete($this->query->getQuery());
        $queryBuilder->setPagination(0); // we don't really want any results, just the suggestions

        if (!$INFO['isadmin']) {
            $queryBuilder->setACLs($INPUT->server->str('REMOTE_USER'), $INFO['userinfo']['grps'] ?? []);
        }
        $queryBuilder->addDateFilter($INPUT->str('min'));

        // add filters
        foreach ($this->query->getFilters() as $filter) {
            $queryBuilder->addFilter($filter, false);
        }

        return $this->client->client()->call('_search', $queryBuilder->query);
    }

    /**
     * Get the parsed query
     *
     * @return QueryParser
     */
    public function getParsedQuery(): QueryParser
    {
        return $this->query;
    }

    // region default filter configurations

    /**
     * Default namespace filter configuration
     * @return array
     */
    protected function createNamespaceFilterConfig(): array
    {
        return [
            'label' => trim($this->getLang('ns'), ':'),
            'queryParam' => 'ns',
            'fieldName' => 'namespace',
            'prefix' => '@',
            'isAndQuery' => false,
        ];
    }

    /**
     * Create the language filter if the translation plugin is available
     *
     * @return array|null returns null if translation plugin is not available
     * @todo this could maybe be moved to the tanslation plugin itself?
     */
    protected function createLanguageFilterConfig(): ?array
    {
        /** @var \helper_plugin_translation $transplugin */
        $transplugin = plugin_load('helper', 'translation');
        if ($transplugin === null) return null;

        $options = [];
        foreach ($transplugin->translations as $lang) {
            $lang = $transplugin->realLC($lang);
            $label = $transplugin->getLocalName($lang);
            $options[$lang] = $label;
        }

        return [
            'label' => $this->getLang('language'),
            'queryParam' => 'lang',
            'isAndQuery' => false,
            'options' => $options,
        ];
    }

    /**
     * Create the media filter
     *
     * @return array
     */
    protected function createMediaFilterConfig(): array
    {
        /** @var \helper_plugin_elasticsearch_docparser $docparser */
        $docparser = plugin_load('helper', 'elasticsearch_docparser');

        $extensions = array_combine($docparser->getExtensions(), $docparser->getExtensions());
        if (isset($extensions['jpeg'])) {
            unset($extensions['jpeg']);
            $extensions['jpg'] = 'jpg';
        }

        return [
            'label' => $this->getLang('filetype'),
            'isAndQuery' => false,
            'queryParam' => 'ext',
            'prefix' => 'ext:',
            'fieldName' => 'ext',
            'options' => array_merge(
                [
                    'wiki' => $this->getLang('wikipages'),
                ],
                $extensions
            )
        ];
    }

    // endregion

    // region plugin helpers

    /**
     * Get a translated string
     *
     * @param string $msg
     * @return string
     */
    protected function getLang($msg)
    {
        return $this->client->getLang($msg);
    }

    /**
     * Get a configuration value
     *
     * @param string $conf
     * @return mixed
     */
    protected function getConf($conf)
    {
        return $this->client->getConf($conf);
    }

    // endregion
}
