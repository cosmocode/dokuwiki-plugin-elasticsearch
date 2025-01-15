<?php

namespace dokuwiki\plugin\elasticsearch\gui;

class Results extends AbstractGui
{
    /**
     * @inheritdoc
     * @todo split into smaller methods
     */
    public function render(): string
    {
        global $lang;

        // output results
        $found = $this->results['hits']['total']['value'];

        if (!$found) {
            return '<h2>' . $lang['nothingfound'] . '</h2>';
        }

        $html = '<dl class="search_results">';
        $html .= '<h2>' . sprintf($this->getLang('totalfound'), $found) . '</h2>';
        foreach ($this->results['hits']['hits'] as $row) {
            $doc = $row['_source'];
            $page = $doc['uri'];
            if (
                (!page_exists($page) && !is_file(mediaFN($page))) ||
                isHiddenPage($page) ||
                auth_quickaclcheck($page) < AUTH_READ
            ) {
                continue;
            }

            // get highlighted title
            $highlightsTitle = $row['highlight']['title'] ?? '';
            $title = str_replace(
                ['ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'],
                ['<strong class="search_hit">', '</strong>'],
                hsc(implode(' … ', (array)$highlightsTitle))
            );
            if (!$title) $title = hsc($doc['title']);
            if (!$title) $title = hsc(p_get_first_heading($page));
            if (!$title) $title = hsc($page);

            // get highlighted snippet
            $highlightedSnippets = $row['highlight'][$this->getConf('snippets')] ?? [];
            $snippet = str_replace(
                ['ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'],
                ['<strong class="search_hit">', '</strong>'],
                hsc(implode(' … ', $highlightedSnippets))
            );
            if (!$snippet) $snippet = hsc($doc['abstract']); // always fall back to abstract

            // assume page if no doctype is set, because old index won't have doctypes
            $isPage = empty($doc['doctype']) || $doc['doctype'] === \action_plugin_elasticsearch_indexing::DOCTYPE_PAGE;
            $href = $isPage ? wl($page) : ml($page);

            $html .= '<dt>';
            if (!$isPage && is_file(DOKU_INC . 'lib/images/fileicons/' . $doc['ext'] . '.png')) {
                $html .= sprintf(
                    '<img src="%s" alt="%s" /> ',
                    DOKU_BASE . 'lib/images/fileicons/' . $doc['ext'] . '.png',
                    $doc['ext']
                );
            }
            $html .= '<a href="' . $href . '" class="wikilink1" title="' . hsc($page) . '">';
            $html .= $title;
            $html .= '</a>';
            $html .= '</dt>';

            // meta
            $html .= '<dd class="meta elastic-resultmeta">';
            if (!empty($doc['namespace'])) {
                $html .= '<span class="ns">' . $this->getLang('ns') . ' ' . hsc($doc['namespace']) . '</span>';
            }
            if ($doc['modified']) {
                $lastmod = strtotime($doc['modified']);
                $html .= ' <span class="">' . $lang['lastmod'] . ' ' . dformat($lastmod) . '</span>';
            }
            if (!empty($doc['user'])) {
                $html .= ' <span class="author">' . $this->getLang('author') . ' ' . userlink($doc['user']) . '</span>';
            }
            $html .= '</dd>';

            // snippets
            $html .= '<dd class="snippet">';
            $html .= $snippet;
            $html .= '</dd>';
        }
        $html .= '</dl>';

        return $html;
    }
}
