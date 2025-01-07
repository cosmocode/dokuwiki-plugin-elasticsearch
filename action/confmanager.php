<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

/**
 * Originally written for docsearch
 *
 * @author Dominik Eckelmann
 */
class action_plugin_elasticsearch_confmanager extends ActionPlugin
{
    /** @inheritDoc */
    public function register(EventHandler $controller)
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
    public function addConfigFile(Event $event, $params)
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
