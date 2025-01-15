<?php

namespace dokuwiki\plugin\elasticsearch\gui;


use dokuwiki\Form\Form as DokuForm;
use dokuwiki\plugin\elasticsearch\Filter;

class Form extends AbstractGui
{
    /** @var DokuForm */
    protected $searchForm;

    const KEEP_PREFIX = 1;
    const MOD_REMOVE = 'remove';
    const MOD_ADD = 'add';

    public function render(): string
    {
        global $lang;
        global $INPUT;


        $this->searchForm = (new DokuForm(['method' => 'get'], true))->addClass('search-results-form');
        $this->searchForm->setHiddenField('do', 'search');

        $this->searchForm->addFieldsetOpen()->addClass('search-form');
        $this->searchForm->addTextInput('q')->val($this->query->getQuery())->useInput(false);
        $this->searchForm->addDropdown(
            'min',
            [
                '' => $lang['search_any_time'],
                'week' => $lang['search_past_7_days'],
                'month' => $lang['search_past_month'],
                'year' => $lang['search_past_year'],
            ]
        )
            ->val($INPUT->str('min', ''))
            ->attr('title', trim($this->getLang('lastmod'), ':'));

        $this->searchForm->addButton('', $lang['btn_search'])->attr('type', 'submit');

        foreach ($this->query->getFilters() as $filter) {
            $this->searchForm->addHTML($this->filterUi($filter));
        }

        $this->searchForm->addFieldsetClose();

        return $this->searchForm->toHTML();
    }

    /**
     * Returns the lables for the filters to add and remove
     *
     * Uses the options and aggregation results to determine the labels
     *
     * @param Filter $filter
     * @return array [removals, additions]
     */
    protected function getFilterLabels(Filter $filter): array
    {
        // currently set filters
        $removals = array_map(
            fn($label) => ['label' => $label, 'count' => 0],
            $filter->getValueLabels(self::KEEP_PREFIX)
        );
        foreach ($this->results['aggregations'][$filter->getName()]['buckets'] as $bucket) {
            if (isset($removals[$bucket['key']])) {
                $removals[$bucket['key']]['count'] = $bucket['doc_count'];
            }
        }

        // filters available to set (from options and aggregations)
        $additions = array_map(
            fn($label) => ['label' => $label, 'count' => 0],
            $filter->getOptions()
        );
        foreach ($this->results['aggregations'][$filter->getName()]['buckets'] as $bucket) {
            $additions[$bucket['key']] = [
                'label' => $filter->getOptionLabel($bucket['key'], self::KEEP_PREFIX),
                'count' => $bucket['doc_count'],
            ];
        }
        $additions = array_diff_key($additions, $removals); // skip the ones already set

        return [$removals, $additions];
    }

    /**
     * Create the list of links to add or remove the filter values
     *
     * @param Filter $filter
     * @return string
     */
    protected function filterUi(Filter $filter)
    {
        [$removals, $additions] = $this->getFilterLabels($filter);


        $html = '<ul class="filter">';
        foreach ($removals as $value => $info) {
            $html .= '<li>';
            $html .= $this->filterModLink(self::MOD_REMOVE, $filter, $value, $info['label'], $info['count']);
            $html .= '</li>';
        }

        foreach ($additions as $value => $info) {
            $html .= '<li>';
            $html .= $this->filterModLink(self::MOD_ADD, $filter, $value, $info['label'], $info['count']);
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Get an a link to the current search but with the given filter value removed
     *
     * @param string $mod The type of link, use MOD_* constants
     * @param Filter $opFilter The filter to remove
     * @param string $value The value to remove
     * @param string $label The label to show for the link
     * @param int $count The count of results with this value
     * @return string
     */
    protected function filterModLink(string $mod, Filter $opFilter, string $value, string $label, int $count = 0)
    {
        global $INPUT;

        // fixme this setup is somewhat duplicated in Pagination
        $p = [
            'q' => $this->query->getQuery(),
            'do' => 'search',
            'min' => $INPUT->str('min', null), // date filter
        ];

        // add the filters to the URL, but modify the given one
        foreach ($this->query->getFilters() as $name => $filter) {
            $values = $filter->getValues();
            if ($name == $opFilter->getName()) {
                if ($mod === self::MOD_ADD) {
                    $values[] = $value;
                } else {
                    $values = array_diff($values, [$value]);
                }
            }
            $p[$filter->getQueryParam()] = $values;
        }

        // FIXME: once dokuwiki/dokuwiki#4389 is in stable, we can pass $p directly to wl()
        $url = wl('', http_build_query($p, '', ','), false, '&');

        // FIXME localize
        $title = sprintf($mod . ' filter "%s" from search (%s)', $value, $opFilter->getLabel());

        $linkattr = [
            'href' => $url,
            'title' => $title,
            'class' => 'filter-' . $mod,
        ];

        $label = '<bdi>' . hsc($label) . '</bdi>';
        if ($count) {
            $label .= ' <span class="count">(' . $count . ')</span>';
        }
        if ($mod === self::MOD_REMOVE) {
            $label .= ' ✗';
        } else {
            $label .= ' +';
        }

        return '<a ' . buildAttributes($linkattr) . '>' . $label . '</a>';
    }

}
