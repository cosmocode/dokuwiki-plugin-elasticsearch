<?php

namespace Elastica\Test\Connection;

use Elastica\Connection;
use Elastica\Connection\ConnectionPool;
use Elastica\Connection\Strategy\StrategyFactory;
use Elastica\Test\Base as BaseTest;

/**
 * @author chabior
 */
class ConnectionPoolTest extends BaseTest
{
    /**
     * @group unit
     */
    public function testConstruct()
    {
        $pool = $this->createPool();

        $this->assertEquals($this->getConnections(), $pool->getConnections());
    }

    /**
     * @group unit
     */
    public function testSetConnections()
    {
        $pool = $this->createPool();

        $connections = $this->getConnections(5);

        $pool->setConnections($connections);

        $this->assertEquals($connections, $pool->getConnections());

        $this->assertInstanceOf(ConnectionPool::class, $pool->setConnections($connections));
    }

    /**
     * @group unit
     */
    public function testAddConnection()
    {
        $pool = $this->createPool();
        $pool->setConnections([]);

        $connections = $this->getConnections(5);

        foreach ($connections as $connection) {
            $pool->addConnection($connection);
        }

        $this->assertEquals($connections, $pool->getConnections());

        $this->assertInstanceOf(ConnectionPool::class, $pool->addConnection($connections[0]));
    }

    /**
     * @group unit
     */
    public function testHasConnection()
    {
        $pool = $this->createPool();

        $this->assertTrue($pool->hasConnection());
    }

    /**
     * @group unit
     */
    public function testFailHasConnections()
    {
        $pool = $this->createPool();

        $pool->setConnections([]);

        $this->assertFalse($pool->hasConnection());
    }

    protected function getConnections(int $quantity = 1): array
    {
        $params = [];
        $connections = [];

        for ($i = 0; $i < $quantity; ++$i) {
            $connections[] = new Connection($params);
        }

        return $connections;
    }

    protected function createPool(): ConnectionPool
    {
        $connections = $this->getConnections();
        $strategy = StrategyFactory::create('Simple');

        return new ConnectionPool($connections, $strategy);
    }
}
