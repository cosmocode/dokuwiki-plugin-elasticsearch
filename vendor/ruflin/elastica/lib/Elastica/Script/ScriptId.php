<?php

namespace Elastica\Script;

/**
 * Stored script referenced by ID.
 *
 * @author Tobias Schultze <http://tobion.de>
 * @author Martin Janser <martin.janser@liip.ch>
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/modules-scripting.html
 */
class ScriptId extends AbstractScript
{
    /**
     * @var string
     */
    private $_scriptId;

    /**
     * @param string      $scriptId   Script ID
     * @param array|null  $params
     * @param string|null $lang
     * @param string|null $documentId Document ID the script action should be performed on (only relevant in update context)
     */
    public function __construct(string $scriptId, array $params = null, string $lang = null, string $documentId = null)
    {
        parent::__construct($params, $lang, $documentId);

        $this->setScriptId($scriptId);
    }

    /**
     * @param string $scriptId
     *
     * @return $this
     */
    public function setScriptId(string $scriptId): ScriptId
    {
        $this->_scriptId = $scriptId;

        return $this;
    }

    /**
     * @return string
     */
    public function getScriptId(): string
    {
        return $this->_scriptId;
    }

    /**
     * {@inheritdoc}
     */
    protected function getScriptTypeArray(): array
    {
        return ['id' => $this->_scriptId];
    }
}
