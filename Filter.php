<?php

namespace dokuwiki\plugin\elasticsearch;

class Filter
{

    private string $name;
    private string $prefix;
    private string $queryParam;
    private string $label;
    private string $fieldPath;
    private int $limit;
    private array $options;
    private bool $isAndQuery;
    private array $values;

    /**
     * Create a new filter
     *
     * @param string $name The name of the filter
     * @param array $config [
     *     'prefix' => string,    // The prefix to look for in the query string, defaults to the filter name
     *     'queryParam' => string,// The query parameter to use for this filter, defaults to the filter name
     *     'label' => string,     // The label to display for this filter (in current language)
     *     'fieldPath' => string, // The field name to use in the ElasticSearch query, defaults to the filter name
     *     'limit' => int,        // The maximum number of values to aggregate, defaults to 25
     *     'options' => string[], // value => label pairs for the filter options, empty for aggregation only
     *     'isAndQuery' => bool,  // If true, the filter values are combined with AND instead of OR, defaults to true
     *     'values' => string[],  // The values set for this filter (will be auto-set from query string and parameters)
     *  ]
     */
    public function __construct($name, $config)
    {
        $this->name = $name;
        $this->prefix = $config['prefix'] ?? $name . ':';
        $this->queryParam = $config['queryParam'] ?? $name;
        $this->label = $config['label'] ?? $name;
        $this->fieldPath = $config['fieldPath'] ?? $name;
        $this->limit = (int)($config['limit'] ?? 25);
        $this->options = $config['options'] ?? [];
        $this->isAndQuery = $config['isAndQuery'] ?? true;
        $this->values = [];
    }

    public function addValues(array $values)
    {
        foreach ($values as $value) {
            $this->addValue($value);
        }
        $this->values = array_unique($this->values);
    }

    public function addValue(string $value)
    {
        $this->values[] = $value;
    }

    public function removeValue(string $value)
    {
        $key = array_search($value, $this->values);
        if ($key !== false) {
            unset($this->values[$key]);
            $this->values = array_values($this->values); // reindex
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getQueryParam(): string
    {
        return $this->queryParam;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFieldPath(): string
    {
        return $this->fieldPath;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Get the options for this filter
     *
     * This will not only return the options that might have been configured in advance, but also
     * all currently set values as options.
     *
     * @return string[] [value => label]
     */
    public function getOptions()
    {
        $options = $this->options;
        foreach ($this->values as $value) {
            if (!isset($options[$value])) {
                $options[$value] = $value;
            }
        }
        asort($options); // FIXME use our natural sort

        return $options;
    }

    public function isAndQuery(): bool
    {
        return $this->isAndQuery;
    }

    public function getValues(): array
    {
        return $this->values;
    }


}
