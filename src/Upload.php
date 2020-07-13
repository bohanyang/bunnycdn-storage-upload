<?php

namespace App;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use function Safe\fclose;
use function Safe\fopen;
use function Safe\sprintf;
use function substr_compare;

final class Upload
{
    /** @var HttpClientInterface */
    private $client;

    /** @var string */
    private $dir;

    /** @var LoggerInterface */
    private $logger;

    /** @var ResponseInterface[] */
    private $responses = [];

    /** @var int */
    private $counter = 0;

    /** @var int */
    private $transfers;

    private function __construct()
    {
    }

    public static function execute(
        string $dir,
        string $baseUri,
        string $accessKey,
        int $transfers = 4,
        HttpClientInterface $client = null,
        LoggerInterface $logger = null
    )
    {
        $instance = new self();

        $dirInfo = new SplFileInfo($dir);
        if (!$dirInfo->isDir()) throw new Exception(sprintf('Invalid dir "%s"', $dir));
        $instance->dir = $dirInfo->getRealPath() . '/';

        $instance->client = ScopingHttpClient::forBaseUri(
            $client ?? HttpClient::create(),
            substr_compare($baseUri, '/', -1) === 0 ? $baseUri : $baseUri . '/',
            [
                'headers' => ['AccessKey' => $accessKey],
                'timeout' => 31536000.0,
                'http_version' => '1.1'
            ]
        );

        $instance->transfers = $transfers < 1 ? 1 : $transfers;
        $instance->logger = $logger ?? new Logger('', [new StreamHandler('php://stderr')]);

        $instance->files();
        $instance->wait();
    }

    private function list(string $path = '') : array
    {
        $list = [];

        foreach ($this->client->request('GET', $path)->toArray() as $object) {
            if (!$object['IsDirectory']) $list[$path . $object['ObjectName']] = $object;
        }

        return $list;
    }

    private function files(string $path = '') : void
    {
        $this->logger->info(sprintf('Enter "%s"', $this->dir . $path));

        $finder = new Finder();
        $finder->in($this->dir . $path)->depth(0)->files();

        if ($finder->hasResults()) {
            $list = $this->list($path);

            foreach ($finder as $file) {
                $dest = $path . $file->getFilename();

                if (isset($list[$dest]) && $list[$dest]['Length'] === $file->getSize()) continue;

                $this->logger->info(sprintf('Upload "%s"', $dest));

                $this->responses[] = $this->client->request('PUT', $dest, [
                    'user_data' => $handle = fopen($file->getRealPath(), 'r'),
                    'body' => $handle
                ]);

                if (++$this->counter === $this->transfers) $this->wait();
            }
        }

        $this->dirs($path);
    }

    private function dirs(string $path) : void
    {
        $finder = new Finder();
        $finder->in($this->dir . $path)->depth(0)->directories();

        if ($finder->hasResults()) {
            foreach ($finder as $dir) {
                $this->files($path . $dir->getFilename() . '/');
            }
        }
    }

    private function wait() : void
    {
        if (!isset($this->responses) || $this->responses === []) return;

        $this->logger->info("Waiting for $this->counter responses");

        foreach ($this->client->stream($this->responses) as $response => $chunk) {
            if ($chunk->isLast()) {
                fclose($response->getInfo('user_data'));

                $info = $response->toArray();

                if ($info['HttpCode'] !== 201) {
                    $message = sprintf('Error %d "%s" for "%s"', $info['HttpCode'], $info['Message'], $response->getInfo('url'));
                    $this->logger->error($message);
                    throw new Exception($message);
                }
            }
        }

        $this->responses = [];
        $this->counter = 0;
    }
}
