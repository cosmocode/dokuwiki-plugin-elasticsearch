<?php

namespace dokuwiki\plugin\elasticsearch\gui;

use dokuwiki\plugin\elasticsearch\Filter;
use dokuwiki\plugin\elasticsearch\QueryParser;

class LinkBuilder
{
    protected QueryParser $query;
    protected array $parameters = [];


    /**
     * Initialize the link with the current state of the search
     *
     * does not set the page, since that should reset on changing any search parameter
     *
     * @param QueryParser $parsedQuery
     */
    public function __construct(QueryParser $parsedQuery)
    {
        global $INPUT;

        $this->query = $parsedQuery;

        $this->parameters = [
            'q' => $parsedQuery->getQuery(),
            'do' => 'search',
            'min' => $INPUT->str('min', null), // date filter
        ];

        // add the filters to the URL
        foreach ($parsedQuery->getFilters() as $filter) {
            $values = $filter->getValues();
            $this->parameters[$filter->getQueryParam()] = $values;
        }
    }

    /**
     * Get a set of parameters suitable for http_build_query()
     *
     * @return array
     */
    public function getParameters()
    {
        return array_filter($this->parameters);
    }

    /**
     * Get the URL to the search page with the current parameters
     *
     * @param string $sep The separator to use for the query parameters
     * @return string
     */
    public function getUrl(string $sep = '&amp;'): string
    {
        global $ID;
        // FIXME: once dokuwiki/dokuwiki#4389 is in stable, we can pass $p directly to wl()
        return wl($ID, http_build_query($this->getParameters(), '', ','), false, $sep);
    }

    /**
     * Set the query string
     *
     * @param string $q
     * @return LinkBuilder
     */
    public function setQueryString($q): self
    {
        $this->parameters['q'] = $q;
        return $this;
    }

    /**
     * Set the page number
     *
     * @param int $page
     * @return LinkBuilder
     */
    public function setPage(int $page): self
    {
        if ($page < 1 && isset($this->parameters['p'])) {
            unset($this->parameters['p']);
        } else {
            $this->parameters['p'] = $page;
        }

        return $this;
    }

    /**
     * Remove the given value from the currently set filters
     *
     * @param Filter $filter
     * @param string $value
     * @return LinkBuilder
     */
    public function removeFilterValue(Filter $filter, string $value): self
    {
        $param = $filter->getQueryParam();
        if (!isset($this->parameters[$param])) return $this;
        $this->parameters[$param] = array_diff($this->parameters[$param], [$value]);
        $this->parameters[$param] = array_values($this->parameters[$param]); // reindex

        return $this;
    }

    /**
     * Add the given value to the currently set filters
     *
     * @param Filter $filter
     * @param string $value
     * @return LinkBuilder
     */
    public function addFilterValue(Filter $filter, string $value): self
    {
        $param = $filter->getQueryParam();
        if (!isset($this->parameters[$param])) {
            $this->parameters[$param] = [$value];
        } else {
            $this->parameters[$param][] = $value;
        }
        $this->parameters[$param] = array_unique($this->parameters[$param]);

        return $this;
    }
}
