<?php

namespace dokuwiki\plugin\elasticsearch\gui;

use dokuwiki\Form\Form as DokuForm;
use dokuwiki\plugin\elasticsearch\Filter;

class Form extends AbstractGui
{
    /** @var DokuForm */
    protected $searchForm;

    protected const KEEP_PREFIX = 1;
    protected const MOD_REMOVE = 'remove';
    protected const MOD_ADD = 'add';

    public function render(): string
    {
        global $lang;
        global $INPUT;


        $this->searchForm = (new DokuForm(['method' => 'get'], true))->addClass('elastic-form');
        $this->searchForm->setHiddenField('do', 'search');
        foreach ($this->query->getFilters() as $filter) {
            foreach ($filter->getValues() as $value) {
                $this->searchForm->setHiddenField($filter->getQueryParam() . '[]', $value);
            }
        }

        $this->searchForm->addTagOpen('section')->addClass('input');
        $this->searchForm->addTextInput('q')->val($this->query->getQuery())->useInput(false)->attr('type', 'search');
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
        $this->searchForm->addTagClose('section');

        $this->searchForm->addHTML($this->suggestionUi());


        $this->searchForm->addTagOpen('ul')->addClass('filters');
        foreach ($this->query->getFilters() as $filter) {
            $this->searchForm->addHTML($this->filterUi($filter));
        }
        $this->searchForm->addTagClose('ul');

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

        $html = '';

        foreach ($removals as $value => $info) {
            $html .= '<li>';
            $html .= $this->filterModLink(self::MOD_REMOVE, $filter, $value, $info['label'], $info['count']);
            $html .= '</li>';
        }

        if (!$additions) return $html;

        $html .= '<li class="add"><div class="li">';
        $html .= '<details>';
        $html .= '<summary title="' . $this->getLang('add_filter') . '">' . hsc($filter->getLabel()) . '</summary>';
        $html .= '<ul>';
        foreach ($additions as $value => $info) {
            $html .= '<li>';
            $html .= $this->filterModLink(self::MOD_ADD, $filter, $value, $info['label'], $info['count']);
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</details>';
        $html .= '</div></li>';

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
        if ($mod === self::MOD_REMOVE) {
            $url = $this->linkBuilder()->removeFilterValue($opFilter, $value)->getUrl('&');
        } else {
            $url = $this->linkBuilder()->addFilterValue($opFilter, $value)->getUrl('&');
        }

        $title = sprintf($this->getLang('filter_' . $mod), $value, $opFilter->getLabel());
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
        }

        return '<a ' . buildAttributes($linkattr) . '>' . $label . '</a>';
    }

    /**
     * Output the suggestion if any
     *
     * @return string
     */
    protected function suggestionUi()
    {
        if (!$this->results['suggest']['phrase'][0]['options']) return '';

        $suggestion = $this->results['suggest']['phrase'][0]['options'][0]['text'];

        $url = $this->linkBuilder()->setQueryString($suggestion)->getUrl();
        $link = '<a href="' . $url . '">' . $suggestion . '</a>';

        $html = '<p class="suggestion">';
        $html .= sprintf($this->getLang('suggest'), $link);
        $html .= '</p>';
        return $html;
    }
}
