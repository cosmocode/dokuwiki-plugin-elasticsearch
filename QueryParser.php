<?php

namespace dokuwiki\plugin\elasticsearch;

use RuntimeException;

class QueryParser
{
    /**
     * @var string The query string with out any filters
     */
    protected $querystring = '';

    /**
     * @var Filter[] The filters configuration
     */
    protected $filters = [];

    /**
     * @param string $querystring The query string to parse
     * @param array $filterconfig The filters configuration
     */
    public function __construct(string $querystring, array $filterconfig)
    {
        global $INPUT;

        $this->querystring = $querystring;

        foreach ($filterconfig as $name => $config) {
            if (isset($config['values'])) {
                throw new RuntimeException("Filter $name should not have a values key set on initialization");
            }

            $filter = new Filter($name, $config);
            $filter->addValues($this->parseParams($this->querystring, $filter->getPrefix()));
            $filter->addValues($INPUT->arr($filter->getQueryParam(), []));
            $this->filters[$name] = $filter;
        }
    }

    /**
     * Get the query string without any filters
     *
     * @return string
     */
    public function getQuery(): string
    {
        if ($this->querystring === '') {
            return '*'; // search everything, when no query is given
        }
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
     * Get all filter configurations
     *
     * @return Filter[] [filter => Filter]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get a filter by name
     *
     * @param string $name
     * @return Filter
     */
    public function getFilter($name): Filter
    {
        if (!isset($this->filters[$name])) {
            throw new RuntimeException("Filter $name not found");
        }

        return $this->filters[$name];
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
