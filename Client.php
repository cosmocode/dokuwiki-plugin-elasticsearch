<?php

namespace dokuwiki\plugin\elasticsearch;


use dokuwiki\HTTP\DokuHTTPClient;
use dokuwiki\Logger;

class Client
{
    protected ?DokuHTTPClient $http = null;
    protected string $index;
    protected string $server;


    /**
     * @param string $serverconf A newline separated list of elasticsearch servers (as given in the config)
     * @param string $index Name of the index to use
     * @param string|null $user User for basic auth
     * @param string|null $pass Password for basic auth
     * @throws Exception
     */
    public function __construct(string $serverconf, string $index, ?string $user = null, ?string $pass = null)
    {
        $this->index = $index;

        $servers = explode("\n", $serverconf);
        shuffle($servers); // randomize server order

        foreach ($servers as $server) {
            $server = trim($server);
            if ($server == '') continue;

            [$server] = explode(',', $server); // remove obsolete proxy

            if (!preg_match('/https?:\/\//', $server)) {
                $server = 'http://' . $server;
            }
            $server = rtrim($server, '/') . '/';

            // try to connect
            $this->http = new DokuHTTPClient();
            $this->http->user = $user;
            $this->http->pass = $pass;
            $this->http->timeout = 4; // we want a quick fail here
            $result = $this->http->get($server);
            if ($result === false) {
                $this->http = null;
                Logger::error('Failed to connect to elasticsearch server: ' . $server);
                continue;
            }

            // we got a connection
            Logger::debug('ElasticSearch connected', $result);
            $this->http->timeout = 15; // reset timeout
            $this->server = $server;
        }

        if ($this->http === null) {
            throw new Exception('Failed to connect to any elasticsearch server');
        }
    }

    /**
     * Send a request to the elasticsearch server
     *
     * @param string $endpoint
     * @param array|null $data
     * @param string $method
     * @return array
     * @throws Exception if the request fails
     */
    public function callRaw(string $endpoint, ?array $data = null, string $method = 'POST')
    {
        $endpoint = ltrim($endpoint, '/');
        $url = $this->server . $endpoint;

        if ($data === null || $method === 'DELETE') {
            $json = '';
        } else {
            $json = json_encode($data);
        }

        $this->http->headers['Content-Type'] = 'application/json';
        $this->http->sendRequest($url, $json, $method);
        if ($this->http->status > 299) {
            $body = $this->http->resp_body;
            if ($body && $body[0] === '{') {
                try {
                    $body = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                    $body = $body['error'];
                    if (is_array($body)) $body = $body['reason'];
                } catch (\Exception $ignored) {
                }
            }
            throw new Exception('ElasticSearch request failed: ' . $body, $this->http->status);
        }

        return json_decode($this->http->resp_body, true);
    }

    /**
     * Send a request to the elasticsearch server on the current index
     *
     * @param string $endpoint relative to the index
     * @param array|null $data
     * @param string $method
     * @return array
     * @throws Exception if the request fails
     */
    public function call(string $endpoint, ?array $data = null, string $method = 'POST')
    {
        $endpoint = $this->index . '/' . ltrim($endpoint, '/');
        return $this->callRaw($endpoint, $data, $method);
    }
}
