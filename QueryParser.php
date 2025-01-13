<?php

namespace dokuwiki\plugin\elasticsearch;

class QueryParser
{
    /**
     * @var string The query string with out any filters
     */
    protected $querystring = '';

    /**
     * @var array The filters configuration
     *
     *  filter => [
     *     'prefix' => string,    // The prefix to look for in the query string, defaults to the filter name
     *     'queryParam' => string,// The query parameter to use for this filter, defaults to the filter name
     *     'label' => string,     // The label to display for this filter (in current language)
     *     'fieldPath' => string, // The field name to use in the ElasticSearch query, defaults to the filter name
     *     'limit' => int,        // The maximum number of values to aggregate, defaults to 25
     *     'options' => string[], // value => label pairs for the filter options, empty for aggregation only
     *     'values' => string[],  // The values set for this filter (will be auto-set from query string and parameters)
     *  ]
     */
    protected $filters = [];

    /**
     * @param string $querystring The query string to parse
     * @param array $config The filters configuration (see above)
     */
    public function __construct(string $querystring, array $config)
    {
        global $INPUT;

        $this->querystring = $querystring;
        $this->filters = $config;

        foreach ($this->filters as $filter => &$config) {
            if (isset($config['values'])) {
                throw new \RuntimeException("Filter $filter should not have a values key set on initialization");
            }

            $this->applyFilterDefaults($filter, $config);

            $config['values'] = array_merge(
                $this->parseParams($this->querystring, $config['prefix']),
                $INPUT->arr($config['queryParam'], [])
            );
        }
    }

    /**
     * Get the query string without any filters
     *
     * @return string
     */
    public function getQuery(): string
    {
        return $this->querystring;
    }

    /**
     * List the currently configured filters
     *
     * @return string[]
     */
    public function getFilterNames(): array
    {
        return array_keys($this->filters);
    }

    /**
     * Get a filter configuration by name
     *
     * @param string $name
     * @return array
     */
    public function getFilter($name): array
    {
        if (!isset($this->filters[$name])) {
            throw new \RuntimeException("Filter $name not found");
        }

        return $this->filters[$name];
    }

    /**
     * Remove a set filter value
     */
    public function removeFilterValue(string $filter, string $value): void
    {
        if (!isset($this->filters[$filter])) {
            throw new \RuntimeException("Filter $filter not found");
        }
        $key = array_search($value, $this->filters[$filter]['values']);
        if ($key !== false) {
            unset($this->filters[$filter]['values'][$key]);
            $this->filters[$filter]['values'] = array_values($this->filters[$filter]['values']); // reindex
        }
    }

    /**
     * Add a filter value
     */
    public function addFilterValue(string $filter, string $value): void
    {
        if (!isset($this->filters[$filter])) {
            throw new \RuntimeException("Filter $filter not found");
        }
        if ($value === '') return;

        $this->filters[$filter]['values'][] = $value;
        $this->filters[$filter]['values'] = array_unique($this->filters[$filter]['values']);
    }

    /**
     * Get the options for a given filter
     *
     * This will not only return the options that might have been configured in advance, but also
     * all currently set values as options.
     *
     * @param string $filter
     * @return string[] [value => label]
     */
    public function getOptions(string $filter)
    {
        $options = $this->getFilter($filter)['options'];
        foreach ($this->getFilter($filter)['values'] as $value) {
            if (!isset($options[$value])) {
                $options[$value] = $value;
            }
        }
        asort($options); // FIXME use our natural sort

        return $options;
    }

    /**
     * Get the values for a given filter
     *
     * These are the actually set values, not the options
     *
     * @param string $filter
     * @return string[] [value, value, ...]
     */
    public function getValues(string $filter): array
    {
        return $this->getFilter($filter)['values'];
    }

    /**
     * Ensure a filter has all defaults set
     *
     * @param string $name
     * @param string[] $filter
     */
    protected function applyFilterDefaults(string $name, array &$filter): void
    {
        // ensure defaults are set
        $filter['prefix'] ??= $name . ':';
        $filter['queryParam'] ??= $name;
        $filter['label'] ??= $name;
        $filter['fieldPath'] ??= $name;
        $filter['limit'] ??= 25;
        $filter['values'] ??= [];
        $filter['options'] ??= [];
    }

    /**
     * Parse the keywords out of the query string
     *
     * @param string $query The query string with keywords, keywords will be removed
     * @param string $prefix The prefix to look for to identify keywords
     * @return array
     */
    protected function parseParams(string &$query, string $prefix): array
    {
        $prefix = preg_quote_cb($prefix);

        $pattern = '/(?:^|\s)' . $prefix . '("[^"]*"|\S+)/';
        preg_match_all($pattern, $query, $matches);

        $filters = [];

        // Process each found tag
        foreach ($matches[1] as $tag) {
            $filters[] = trim($tag, '"'); // Add the tag to the array, removing quotes
            // Remove the matched tag from the original string
            $query = str_replace($matches[0], '', $query);
        }

        // Trim whitespace to clean up the string
        $query = trim(preg_replace('/ +/', ' ', $query)); // Remove extra spaces

        return $filters;
    }

}
