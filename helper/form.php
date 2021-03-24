<?php
/**
 * DokuWiki Plugin elasticsearch (Form Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 * @author  Anna Dabrowska <dabrowska@cosmocode.de>
 */

use dokuwiki\Form\Form;

class helper_plugin_elasticsearch_form extends DokuWiki_Plugin
{

    /**
     * Replacement for the standard search form
     *
     * @param array $aggregations
     */
    public function tpl($aggregations)
    {
        global $lang;
        global $QUERY;

        $searchForm = (new Form(['method' => 'get'], true))->addClass('search-results-form');
        $searchForm->setHiddenField('do', 'search');

        $searchForm->addFieldsetOpen()->addClass('search-form');
        $searchForm->addTextInput('q')->val($QUERY)->useInput(false);
        $searchForm->addButton('', $lang['btn_search'])->attr('type', 'submit');

        $this->addAdvancedSearch($searchForm, $aggregations);

        $searchForm->addFieldsetClose();

        echo $searchForm->toHTML();
    }

    /**
     * Advanced search
     *
     * @param Form $searchForm
     * @param array $aggregations
     */
    protected function addAdvancedSearch(Form $searchForm, array $aggregations)
    {
        $searchForm->addTagOpen('div')
            ->addClass('advancedOptions')
            ->attr('style', 'display: none;')
            ->attr('aria-hidden', 'true');

        foreach ($aggregations as $term => $aggregation) {
            // keep canonical 'ns' search parameter for namespaces
            $param = $term === 'namespace' ? 'ns' : $term;
            $this->addCheckboxSelector($searchForm, $aggregation['buckets'], $param);
        }
        $this->addDateSelector($searchForm);
        $searchForm->addTagClose('div');
    }

    /**
     * Filter with checkboxes
     *
     * @param Form $searchForm
     * @param array $aggregations Namespace aggregations
     * @param string $param Prefix to use in input names
     */
    protected function addCheckboxSelector(Form $searchForm, array $aggregations, $param)
    {
        if (!empty($aggregations)) {
            $searchForm->addTagOpen('div')->addClass('toggle')->attr('aria-haspopup', 'true');

            // popup toggler
            $searchForm->addTagOpen('div')->addClass('current');
            $pluginSearchConfigs = \action_plugin_elasticsearch_search::getRawPluginSearchConfigs();
            $label = $param === 'ns' ? $this->getLang('nsp') : $pluginSearchConfigs[$param]['label'];
            $searchForm->addHTML($label);
            $searchForm->addTagClose('div');

            // options
            $i = 0;
            $searchForm->addTagOpen('ul')->attr('aria-expanded', 'false');
            foreach ($aggregations as $agg) {
                $searchForm->addTagOpen('li');
                $searchForm->addCheckbox($param . '[]')->val($agg['key'])->id("__$param-" . $i);
                $searchForm->addLabel(shorten('', $agg['key'], 25) . ' (' . $agg['doc_count'] . ')', "__$param-" . $i)
                    ->attr('title', $agg['key']);
                $searchForm->addTagClose('li');
                $i++;
            }
            $searchForm->addTagClose('ul');

            $searchForm->addTagClose('div');
        }
    }

    /**
     * Date range filter
     *
     * @param Form $searchForm
     */
    protected function addDateSelector(Form $searchForm)
    {
        global $lang;

        $options = [
            'any' => $lang['search_any_time'],
            'week' =>  $lang['search_past_7_days'],
            'month' => $lang['search_past_month'],
            'year' => $lang['search_past_year'],
        ];

        $searchForm->addTagOpen('div')->addClass('toggle')->attr('aria-haspopup', 'true');

        // popup toggler
        $searchForm->addTagOpen('div')->addClass('current');
        $searchForm->addHTML($this->getLang('lastmod'));
        $searchForm->addTagClose('div');

        // options
        $i = 0;
        $searchForm->addTagOpen('ul')->attr('aria-expanded', 'false');
        foreach ($options as $opt => $label) {
            $searchForm->addTagOpen('li');
            $searchForm->addRadioButton('min')->val($opt)->id('__min-' . $i);
            $searchForm->addLabel($label, '__min-' . $i);
            $searchForm->addTagClose('li');
            $i++;
        }
        $searchForm->addTagClose('ul');

        $searchForm->addTagClose('div');
    }
}
