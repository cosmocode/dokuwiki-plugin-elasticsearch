<?php
/**
 * DokuWiki Plugin elasticsearch (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Kieback&Peter IT <it-support@kieback-peter.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_elasticsearch_client extends DokuWiki_Plugin {

    /**
     * @var \Elastica\Client $elasticaClient
     */
    protected $elasticaClient = null;

    /**
     * Connects to the elastica servers and returns the client object
     *
     * @return \Elastica\Client
     */
    public function connect() {
        if(!is_null($this->elasticaClient)) return $this->elasticaClient;

        // parse servers config into DSN array
        $dsn = array('servers' => array());
        $servers = $this->getConf('servers');
        $lines   = explode("\n", $servers);
        foreach($lines as $line) {
            list($host, $proxy) = explode(',', $line, 2);
            list($host, $port) = explode(':', $host, 2);
            $host = trim($host);
            $port = (int) trim($port);
            if(!$port) $port = 80;
            $proxy = trim($proxy);
            if(!$host) continue;
            $dsn['servers'][] = compact('host', 'port', 'proxy');
        }

        $this->elasticaClient = new \Elastica\Client($dsn);
        return $this->elasticaClient;
    }

    /**
     * Create the field mapping
     *
     * @return \Elastica\Response
     */
    public function createMapping() {
        $client = $this->connect();
        $index = $client->getIndex($this->getConf('indexname'));
        $type = $index->getType($this->getConf('documenttype'));

        $mapping = new \Elastica\Type\Mapping();
        $mapping->setType($type);
        $mapping->setProperties(
            array(
                'uri'       => array(
                    'type' => 'string'
                ),
                'namespace' => array(
                    'type'  => 'string',
                    'index' => 'not_analyzed',
                    'store' => 'yes'
                )
            )
        );
        $response = $mapping->send();
        return $response;
    }

}

// vim:ts=4:sw=4:et:
