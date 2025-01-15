<?php

namespace dokuwiki\plugin\elasticsearch\gui;


/**
 * The main GUI class for the ElasticSearch plugin
 */
class Gui extends AbstractGui
{


    public function render(): string
    {
        return implode("\n", [
            $this->renderIntro(),
            (new Form($this->results, $this->query))->render(),
            (new Results($this->results, $this->query))->render(),
            (new Pagination($this->results, $this->query))->render(),
        ]);
    }

    /**
     * Prints the introduction text
     *
     * @todo for page creation, maybe we should use the uncleaned query?
     */
    protected function renderIntro()
    {
        global $ID;
        global $lang;

        // just reuse the standard search page intro:
        $intro = p_locale_xhtml('searchpage');
        // allow use of placeholder in search intro
        $pagecreateinfo = '';
        if (auth_quickaclcheck($ID) >= AUTH_CREATE) {
            $pagecreateinfo = sprintf($lang['searchcreatepage'], $this->query->getQuery());
        }
        $intro = str_replace(
            ['@QUERY@', '@SEARCH@', '@CREATEPAGEINFO@'],
            [hsc(rawurlencode($this->query->getQuery())), hsc($this->query->getQuery()), $pagecreateinfo],
            $intro
        );
        return $intro;
    }

}
