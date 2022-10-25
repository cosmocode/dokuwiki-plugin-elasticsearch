<?php

/**
 * Originally written for docsearch
 *
 * @author Dominik Eckelmann
 */
class action_plugin_elasticsearch_confmanager extends DokuWiki_Action_Plugin
{
    /** @inheritDoc */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook(
            'CONFMANAGER_CONFIGFILES_REGISTER',
            'BEFORE',
            $this,
            'addConfigFile',
            []
        );
    }

    /** @inheritDoc */
    public function addConfigFile(Doku_Event $event, $params)
    {
        if (!class_exists('ConfigManagerTwoLine')) return;

        $config = new ConfigManagerTwoLine(
            $this->getLang('confmanager title'),
            $this->getDescription(),
            helper_plugin_elasticsearch_docparser::CONFFILE
        );
        $event->data[] = $config;
    }

    /**
     * Returns the description for the configuration
     *
     * @return string
     */
    protected function getDescription()
    {
        $fn = $this->localFN('confmanager_description');
        return file_get_contents($fn);
    }
}
