<?php

namespace dokuwiki\plugin\elasticsearch;

/**
 * Represents an elasticsearch query
 *
 * Should be initialized with the set* methods, then the add*Filter methods can be used to add filters,
 * and finally the jsonSerialize method can be used to get the query representation
 *
 * Utility methods are provided to simplify the creation of the query array
 *
 * @todo add the plugin configuration mechanism
 */
class Query
{
    /** @var array The query representation */
    public $query = [];

    // region initialization

    public function setSimpleQuery($simplequery, $fields)
    {
        $this->querySet(
            'query/bool/must//simple_query_string',
            [
                'query' => $simplequery,
                'fields' => $fields,
            ]
        );
    }

    /**
     * Setup up search snippet highlighting
     *
     * @param string $field The field to use in snippets
     * @return void
     */
    public function setHighlights(string $field)
    {
        $this->query['highlight'] = [
            'pre_tags' => ['ELASTICSEARCH_MARKER_IN'],
            'post_tags' => ['ELASTICSEARCH_MARKER_OUT'],
            'fields' => [
                'title' => new \stdClass(),
                $field => new \stdClass(),
            ],
        ];
    }

    /**
     * Setup the pagination data
     *
     * @param int $perPage results per page
     * @param int $current current page
     * @return void
     */
    public function setPagination(int $perPage, int $current = 1)
    {
        $this->query['from'] = ($current - 1) * $perPage;
        $this->query['size'] = $perPage;
    }

    /**
     * Setup ACLs for the search
     *
     * Should not be called for superusers
     *
     * @param string $user The current user, empty for anonymous
     * @param array $groups The groups the user is in
     * @return void
     */
    public function setACLs(string $user = '', array $groups = [])
    {
        $groups = array_merge(['ALL'], $groups);

        // include if group OR user have read permissions, allows for ACLs such as "block @group except user"
        $includes = $this->termList('groups_include', $groups);
        if ($user !== '') {
            $this->arraySet($includes, '/bool/must', $this->termList('users_include', [$user]));
        }
        $this->querySet('query/bool/must//bool/should', $includes);

        // groups exclusion SHOULD be respected, not MUST, since that would not allow for exceptions
        // FIXME the below satisfies the test, but is it correct? the should/must_not/should structure is a bit odd
        $this->querySet('query/bool/should//bool/must_not//bool/should', $this->termList('groups_exclude', $groups));

        // user specific excludes must always be respected
        if ($user !== '') {
            $this->querySet('query/bool/must_not', $this->termList('users_exclude', [$user]));
        }
    }

    // endregion

    // region filters

    /**
     * Add terms and aggregation for a filter
     *
     * @param Filter $filter
     * @return void
     */
    public function addFilter(Filter $filter)
    {
        // add aggregation
        $this->querySet(
            'aggs/' . $filter->getName() . '/terms',
            [
                'field' => $filter->getFieldPath() . '.keyword',
                'size' => $filter->getLimit(),
            ]
        );

        // add filter terms
        $terms = $filter->getValues();
        if ($terms === []) return;
        $termlist = $this->termList($filter->getFieldPath(), $terms);
        if ($filter->isAndQuery()) {
            $this->querySet('query/bool/must//bool/should', $termlist);
        } else {
            $this->querySet('post_filter/bool/should', $termlist);
        }
    }

    /**
     * Add a date filter
     *
     * Results must be newer than this
     *
     * @param string $unit year, month, week
     * @param int $amount
     * @return void
     */
    public function addDateFilter(string $unit, int $amount = 1)
    {
        if (!in_array($unit, ['year', 'month', 'week'])) return;

        $date = date('Y-m-d', strtotime($amount . ' ' . $unit . ' ago'));
        $this->querySet('query/bool/must//range/modified/gte', $date);
    }

    /**
     * Add a language filter
     *
     * Results must be in one of the given languages
     *
     * @param string[] $lang
     * @return void
     */
    public function addLanguageFilter(array $lang)
    {
        if ($lang === []) return;
        $this->querySet('query/bool/must//match/language', implode(',', $lang));
    }

    // endregion

    // region utils

    /**
     * Create a list of terms for a field
     *
     * @param string $field
     * @param string[] $terms
     * @param float $boost
     * @return array
     */
    protected function termList(string $field, array $terms, $boost = 1.0): array
    {
        $list = [];

        foreach ($terms as $term) {
            $list[] = [
                'term' => [
                    $field => [
                        'value' => $term,
                        'boost' => $boost,
                    ],
                ],
            ];
        }

        return $list;
    }


    /**
     * Set a value in the given array using a path
     *
     * An empty segment will append to the previous segment
     *
     * @param array $arr
     * @param string $path
     * @param mixed $value
     * @return void
     */
    protected function arraySet(&$arr, string $path, $value)
    {
        $keys = explode('/', $path);

        foreach ($keys as $key) {
            if ($key === '') {
                $arr = &$arr[];
            } else {
                $arr = &$arr[$key];
            }
        }
        $arr = $value;
    }

    /**
     * Set a value in the query array using a path
     *
     * An empty segment will append to the previous segment
     *
     * @param string $path
     * @param mixed $value
     * @return void
     */
    protected function querySet(string $path, $value)
    {
        $this->arraySet($this->query, $path, $value);
    }

    // endregion

}
