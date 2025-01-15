<?php

namespace dokuwiki\plugin\elasticsearch\gui;

class Results extends AbstractGui
{
    /**
     * @inheritdoc
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
            $doc = $this->parseHit($row);

            if (
                (!page_exists($doc['uri']) && !is_file(mediaFN($doc['uri']))) ||
                isHiddenPage($doc['uri']) ||
                auth_quickaclcheck($doc['uri']) < AUTH_READ
            ) {
                continue;
            }

            $html .= $this->formatHit($doc);
        }
        $html .= '</dl>';

        return $html;
    }

    /**
     * Parse the hit into a more usable format
     *
     * Resolves highlighting, adds URLs, etc.
     *
     * @param array $hit
     * @return array
     */
    protected function parseHit(array $hit): array
    {
        $doc = $hit['_source'];
        $page = $doc['uri'];

        // get highlighted title
        $highlightsTitle = $hit['highlight']['title'] ?? '';
        $title = str_replace(
            ['ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'],
            ['<strong class="search_hit">', '</strong>'],
            hsc(implode(' … ', (array)$highlightsTitle))
        );
        if (!$title) $title = hsc($doc['title']);
        if (!$title) $title = hsc(p_get_first_heading($page));
        if (!$title) $title = hsc($page);

        // get highlighted snippet
        $highlightedSnippets = $hit['highlight'][$this->getConf('snippets')] ?? [];
        $snippet = str_replace(
            ['ELASTICSEARCH_MARKER_IN', 'ELASTICSEARCH_MARKER_OUT'],
            ['<strong class="search_hit">', '</strong>'],
            hsc(implode(' … ', $highlightedSnippets))
        );
        if (!$snippet) $snippet = hsc($doc['abstract']); // always fall back to abstract

        // assume page if no doctype is set, because old index won't have doctypes
        $isPage = empty($doc['doctype']) || $doc['doctype'] === \action_plugin_elasticsearch_indexing::DOCTYPE_PAGE;
        $href = $isPage ? wl($page) : ml($page);

        $link = [
            'href' => $href,
            'title' => $title,
        ];
        if ($isPage) {
            $link['class'] = 'wikilink1';
        } else {
            $link['class'] = 'media mediafile mf_' . $doc['ext'];
        }

        $doc['linkAttributes'] = $link;
        $doc['title'] = $title;
        $doc['snippet'] = $snippet;
        $doc['isPage'] = $isPage;
        $doc['href'] = $href;
        $doc['namespace'] ??= '';
        $doc['modified'] ??= '';
        $doc['user'] ??= '';

        return $doc;
    }

    /**
     * Format a single row in the results
     *
     * @param array $doc
     * @return string
     */
    protected function formatHit(array $doc): string
    {
        global $lang;

        $html = '<dt>';

        if (!$doc['isPage'] && str_starts_with($doc['mime'], 'image/')) {
            $img = [
                'src' => ml($doc['uri'], ['w' => 200, 'h' => 100, 'cache' => 1], true, '&'),
                'width' => 100,
                'height' => 50,
                'alt' => '',
                'loading' => 'lazy',
                'class' => 'media mediaright',
            ];
            $html .= '<a href="' . ml($doc['uri']) . '">';
            $html .= '<img ' . buildAttributes($img) . '>';
            $html .= '</a>';
        }

        $html .= '<a ' . buildAttributes($doc['linkAttributes']) . '>';
        $html .= $doc['title'];
        $html .= '</a>';

        if ($doc['namespace'] !== '') {
            $url = $this->linkBuilder()
                ->addFilterValue($this->query->getFilter('namespace'), $doc['namespace'])
                ->getUrl('&');
            $link = [
                'href' => $url,
                'class' => 'ns',
                'title' => sprintf(
                    $this->getLang('filter_add'),
                    $doc['namespace'],
                    $this->query->getFilter('namespace')->getLabel()
                ),
            ];

            $html .= ' <a ' . buildAttributes($link) . '>';
            $html .= $this->query->getFilter('namespace')->getPrefix() . hsc($doc['namespace']);
            $html .= '</a>';
        }

        $html .= '</dt>';

        // meta
        $html .= '<dd class="meta elastic-resultmeta">';
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
        $html .= $doc['snippet'];
        $html .= '</dd>';

        return $html;
    }
}
