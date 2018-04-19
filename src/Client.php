<?php

namespace Hotrush\StealerScrapoxy;

use GuzzleHttp\HandlerStack;
use Hotrush\Stealer\Middleware;
use React\Dns\Resolver\Factory;
use Hotrush\Stealer\AbstractClient;
use GuzzleHttp\Client as GuzzleClient;
use WyriHaximus\React\GuzzlePsr7\HttpClientAdapter;
use Hotrush\ScrapoxyClient\Client as ScrapoxyClient;

class Client extends AbstractClient
{
    /**
     * @var bool
     */
    private $scrapoxyScaled = false;

    /**
     * @var int
     */
    private $scalingDelay = 120;

    /**
     * @var bool
     */
    private $waiting = false;

    /**
     * @var \Hotrush\ScrapoxyClient\Client
     */
    protected $scrapoxyClient;

    /**
     * @return GuzzleClient
     */
    protected function createClient()
    {
        $dnsResolverFactory = new Factory();
        $dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);

        $handler = new HttpClientAdapter($this->loop, null, $dnsResolver);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::proxy(getenv('SCRAPOXY_PROXY')));
        $stack->push(Middleware::userAgent());

        return new GuzzleClient([
            'handler' => $stack,
        ]);
    }

    /**
     * @return ScrapoxyClient
     */
    protected function getScrapoxyClient()
    {
        if (!$this->scrapoxyClient) {
            $this->scrapoxyClient = new ScrapoxyClient(
                getenv('API_SCRAPOXY'),
                getenv('API_SCRAPOXY_PASSWORD'),
                $this->loop
            );
        }

        return $this->scrapoxyClient;
    }

    public function start(): void
    {
        parent::start();

        if ($this->waiting) {
            return;
        }

        if (!$this->scrapoxyScaled) {
            $this->logger->info('Scaling scrapoxy');
            $this->waiting = true;
            $this->getScrapoxyClient()->upScale()
                ->then(
                    function () {
                        $this->logger->info(sprintf('Waiting for scaling: %d sec', $this->scalingDelay));
                        $this->loop->addTimer($this->scalingDelay, function () {
                            $this->scrapoxyScaled = true;
                            $this->waiting = false;
                        });
                    },
                    function ($reason) {
                        $this->logger->error(sprintf('Error while scaling: %s', $reason));
                        $this->scrapoxyScaled = false;
                        $this->waiting = false;
                    }
                );
        }
    }

    public function stop(): void
    {
        parent::stop();

        if ($this->waiting) {
            return;
        }

        if ($this->scrapoxyScaled) {
            $this->logger->info('Downscaling scrapoxy');
            $this->waiting = true;
            $this->getScrapoxyClient()->downScale()
                ->then(
                    function () {
                        $this->logger->info(sprintf('Waiting for downscaling: %d sec', $this->scalingDelay));
                        $this->loop->addTimer($this->scalingDelay, function () {
                            $this->scrapoxyScaled = false;
                            $this->waiting = false;
                        });
                    },
                    function ($reason) {
                        $this->logger->error(sprintf('Error while scaling down: %s', $reason));
                        $this->scrapoxyScaled = false;
                        $this->waiting = false;
                    }
                );
        }
    }

    /**
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->scrapoxyScaled;
    }

    /**
     * @return bool
     */
    public function isStopped(): bool
    {
        return !$this->waiting && !$this->scrapoxyScaled;
    }
}