<?php

namespace App;

use Exception;
use Iterator;
use SplObjectStorage;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Safe\fclose;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\mkdir;
use function Safe\parse_url;

class Directory
{
    private ResponseInterface $response;
    private array $children;

    private function __construct(
        private HttpClientInterface $client,
        private string $host,
        private string $path,
        private string $relativePath,
        private ?Directory $parent
    )
    {
        $this->response = $this->client->request('GET', $this->url());
    }

    public static function browse(string $accessKey, string $baseUri): self
    {
        $baseUri = substr_compare($baseUri, '/', -1) === 0 ? $baseUri : $baseUri . '/';
        
        $client = HttpClient::create([
            'headers' => ['AccessKey' => $accessKey],
            'timeout' => 31536000.0,
            'http_version' => '1.1'
        ]);
        
        $host = parse_url($baseUri, PHP_URL_SCHEME) . '://' . parse_url($baseUri, PHP_URL_HOST);
        $path = parse_url($baseUri, PHP_URL_PATH);
        
        return new self($client, $host, $path, '', null);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function url(): string
    {
        return $this->host . $this->path;
    }

    public function parent(): ?Directory
    {
        return $this->parent;
    }

    private function loadChildren(): Iterator
    {
        foreach ($this->response->toArray() as $item) {
            if ($item['IsDirectory']) {
                yield new self(
                    $this->client, 
                    $this->host, 
                    $item['Path'] . $item['ObjectName'] . '/',
                    $this->relativePath . $item['ObjectName'] . '/', 
                    $this
                );
            } else {
                yield new File($item, $this, $this->client);
            }
        }
    }

    public function children(): array
    {
        if (!isset($this->children)) {
            $this->children = iterator_to_array($this->loadChildren());
        }

        return $this->children;
    }

    /**
     * @param ResponseInterface[] $responses
     */
    public function download(array $responses): void
    {
        $handles = new SplObjectStorage();

        try {
            foreach ($this->client->stream($responses) as $response => $chunk) {
                if ($chunk->isFirst()) {
                    if ($response->getStatusCode() !== 200) {
                        throw new Exception($response->getStatusCode());
                    }
                    $savePath = $response->getInfo('user_data');
                    if (!file_exists($saveDir = dirname($savePath))) {
                        mkdir($saveDir, 0777, true);
                    }
                    $handles[$response] = fopen($savePath, 'w');
                } elseif ($chunk->isLast()) {
                    fclose($handles[$response]);
                    echo $response->getInfo('user_data') . "\n";
                } else {
                    fwrite($handles[$response], $chunk->getContent());
                }
            }
        } catch (Exception $e) {
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
            throw $e;
        }
    }
}
