<?php

/**
 * Originally action_plugin_docsearch_confmanager
 *
 * @author Dominik Eckelmann
 */
class action_plugin_elasticsearch_confmanager extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('CONFMANAGER_CONFIGFILES_REGISTER', 'BEFORE', $this, 'addConfigFile', array());
    }

    public function addConfigFile(Doku_Event $event, $params) {
        if(class_exists('ConfigManagerTwoLine')) {
            $config = new ConfigManagerTwoLine(
                $this->getLang('confmanager title'),
                $this->getDescription(),
                DOKU_CONF . 'docparsers.php');
            $event->data[] = $config;
        }
    }

    private function getDescription() {
        $fn = $this->localFN('confmanager_description');
        if(!@file_exists($fn)) {
            return '';
        }
        $content = file_get_contents($fn);
        return $content;
    }

}
