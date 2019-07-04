<?php
/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
use dokuwiki\Form\Form;

if(!defined('DOKU_INC')) die();

class helper_plugin_elasticsearch_form extends DokuWiki_Plugin
{

    /**
     * Replacement for the standard search form
     *
     * @param null $aggregations
     */
    public function tpl ($aggregations = null)
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

        $this->addNamespaceSelector($searchForm, $aggregations);
        $searchForm->addTagClose('div');
    }

    /**
     * Namespace filter
     *
     * @param Form $searchForm
     * @param array $aggregations Namespace aggregations
     */
    protected function addNamespaceSelector(Form $searchForm, array $aggregations)
    {
        if (!empty($aggregations)) {
            // popup toggler
            $searchForm->addTagOpen('div')->addClass('toggle')->attr('aria-haspopup', 'true');
            $searchForm->addTagOpen('div')->addClass('current');
            $searchForm->addHTML($this->getLang('nsp'));
            $searchForm->addTagClose('div');

            // options
            $i = 0;
            $searchForm->addTagOpen('ul')->attr('aria-expanded', 'false');
            foreach ($aggregations as $agg) {
                $searchForm->addTagOpen('li');
                $searchForm->addCheckbox('ns[]')->val($agg['key'])->id('__ns-' . $i);
                $searchForm->addLabel(shorten('', $agg['key'], 25) . ' (' . $agg['doc_count'] . ')', '__ns-' . $i)
                    ->attr('title', $agg['key']);
                $searchForm->addTagClose('li');
                $i++;
            }
            $searchForm->addTagClose('ul');
        }
    }
}
