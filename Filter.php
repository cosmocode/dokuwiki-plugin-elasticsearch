<?php

namespace dokuwiki\plugin\elasticsearch;

use dokuwiki\Utf8\Sort;

class Filter
{
    private string $name;
    private string $prefix;
    private string $queryParam;
    private string $label;
    private string $fieldName;
    private string $fieldPath;
    private int $limit;
    private array $options;
    private bool $isAndQuery;
    private array $values = [];

    /**
     * Create a new filter
     *
     * @param string $name The name of the filter
     * @param array $config [
     *     'prefix' => string,    // The prefix to look for in the query string, defaults to the filter name
     *     'queryParam' => string,// The query parameter to use for this filter, defaults to the filter name
     *     'label' => string,     // The label to display for this filter (in current language)
     *     'fieldName' => string, // The field name to use in the ElasticSearch query, defaults to the filter name
     *     'fieldPath' => string, // The field name to use when aggregating, defaults to the filterName.keyword
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
        $this->fieldName = $config['fieldName'] ?? $name;
        $this->fieldPath = $config['fieldPath'] ?? $name . '.keyword';
        $this->limit = (int)($config['limit'] ?? 25);
        $this->options = $config['options'] ?? [];
        $this->isAndQuery = $config['isAndQuery'] ?? true;
        Sort::asort($this->options);
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

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getFieldPath(): string
    {
        return $this->fieldPath;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Tries to find the given values in the filter's options and uses that as a label
     *
     * @param string[] $values
     * @param int $keepPrefix If the prefix is shorter or equal to this, it will be prefixed to the label
     * @return array [value => label]
     */
    public function getOptionLabels(array $values, int $keepPrefix = 0): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[$value] = $this->getOptionLabel($value, $keepPrefix);
        }
        Sort::asort($result);
        return $result;
    }

    /**
     * Get the label for the given value from the options if available
     *
     * @param string $value The value to label
     * @param int $keepPrefix If the prefix is shorter or equal to this, it will be prefixed to the label
     * @return string
     */
    public function getOptionLabel(string $value, int $keepPrefix = 0): string
    {
        if (isset($this->options[$value])) {
            $label = $this->options[$value];
        } elseif (strlen($this->getPrefix()) <= $keepPrefix) {
            $label = $this->getPrefix() . $value; // keep the cool prefix
        } else {
            $label = $value;
        }
        return $label;
    }

    /**
     * Get all values with their labels (taken from the options if available)
     *
     * @param int $keepPrefix If the prefix is shorter or equal to this, it will be prefixed to the label
     * @return array
     */
    public function getValueLabels(int $keepPrefix = 0): array
    {
        return $this->getOptionLabels($this->values, $keepPrefix);
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
