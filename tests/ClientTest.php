<?php

namespace Hotrush\StealerScrapoxy\Tests;

use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testClient()
    {
        $loop = \React\EventLoop\Factory::create();
        $logger = $this->getMockBuilder(\Monolog\Logger::class)->setConstructorArgs(['test'])->getMock();
        $client = new \Hotrush\StealerScrapoxy\Client($loop, $logger);

        $this->assertInstanceOf(\Hotrush\StealerScrapoxy\Client::class, $client);
        $this->assertAttributeEquals(false, 'scrapoxyScaled', $client);
        $this->assertAttributeEquals(false, 'waiting', $client);
        $this->assertAttributeEquals(120, 'scalingDelay', $client);
        $this->assertAttributeEquals(null, 'scrapoxyClient', $client);
        $this->assertFalse($client->isReady());
        $this->assertTrue($client->isStopped());
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $client->getClient());
    }
}