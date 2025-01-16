<?php

namespace dokuwiki\plugin\elasticsearch\gui;

use dokuwiki\plugin\elasticsearch\QueryParser;

class Pagination extends AbstractGui
{
    /** @var int current page */
    protected $current = 1;
    /** @var int total number of hits */
    protected $all;
    /** @var int number of pages needed to show all results */
    protected $pages;

    /** @inheritdoc */
    public function __construct(array $results, QueryParser $parsedQuery)
    {
        parent::__construct($results, $parsedQuery);

        global $INPUT;
        $this->current = $INPUT->int('p', 1, true);
        $this->all = $this->results['hits']['total']['value'];
        $this->pages = ceil($this->all / $this->getConf('perpage'));
    }

    /** @inheritdoc */
    public function render(): string
    {
        $toshow = $this->calculatePagesToShow();
        $showlen = count($toshow);


        $html = '<ul class="elastic_pagination">';
        if ($this->current > 1) {
            $p['p'] = ($this->current - 1);
            $html .= '<li class="prev">';
            $html .= $this->createLink($this->current - 1, '«');
            $html .= '</li>';
        }

        for ($i = 0; $i < $showlen; $i++) {
            if ($toshow[$i] == $this->current) {
                $html .= '<li class="cur">' . $toshow[$i] . '</li>';
            } else {
                $html .= '<li>';
                $html .= $this->createLink($toshow[$i]);
                $html .= '</li>';
            }

            // show seperator when a jump follows
            if (isset($toshow[$i + 1]) && $toshow[$i + 1] - $toshow[$i] > 1) {
                $html .= '<li class="sep">…</li>';
            }
        }

        if ($this->current < $this->pages) {
            $html .= '<li class="next">';
            $html .= $this->createLink($this->current + 1, '»');
            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * @param int $page The page number to link to
     * @param ?string $label The label to show for the link, defaults to the page number
     * @return string
     */
    protected function createLink(int $page, ?string $label = null)
    {
        if ($label === null) $label = $page;
        $url = $this->linkBuilder()->setPage($page)->getUrl();
        return '<a href="' . $url . '">' . hsc($label) . '</a>';
    }

    /**
     * Return a list of page numbers to show in the pagination
     *
     * The goal is to show 7 page numbers only for large result sets.
     *
     * @return int[]
     */
    protected function calculatePagesToShow()
    {

        if ($this->pages < 2) return []; // only one page, no need to show pagination

        // which pages to show
        $toshow = [1, 2, $this->current, $this->pages, $this->pages - 1];
        if ($this->current - 1 > 1) $toshow[] = $this->current - 1;
        if ($this->current + 1 < $this->pages) $toshow[] = $this->current + 1;
        $toshow = array_unique($toshow);

        // fill up to seven, if possible
        if (count($toshow) < 7) {
            if ($this->current < 4) {
                if ($this->current + 2 < $this->pages && count($toshow) < 7) $toshow[] = $this->current + 2;
                if ($this->current + 3 < $this->pages && count($toshow) < 7) $toshow[] = $this->current + 3;
                if ($this->current + 4 < $this->pages && count($toshow) < 7) $toshow[] = $this->current + 4;
            } else {
                if ($this->current - 2 > 1 && count($toshow) < 7) $toshow[] = $this->current - 2;
                if ($this->current - 3 > 1 && count($toshow) < 7) $toshow[] = $this->current - 3;
                if ($this->current - 4 > 1 && count($toshow) < 7) $toshow[] = $this->current - 4;
            }
        }
        sort($toshow);

        return $toshow;
    }
}
