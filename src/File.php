<?php

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Safe\substr;

class File
{
    public function __construct(
        private array $info,
        private Directory $parent,
        private HttpClientInterface $client
    )
    {
    }

    public function dateCreated(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.v', $this->info['DateCreated'], new DateTimeZone('UTC'));
    }

    public function lastChanged(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.v', $this->info['LastChanged'], new DateTimeZone('UTC'));
    }

    public function host(): string
    {
        return $this->parent->host();
    }

    public function size(): int
    {
        return $this->info['Length'];
    }

    public function path(): string
    {
        return $this->info['Path'] . $this->info['ObjectName'];
    }

    public function relativePath(): string
    {
        return $this->parent->relativePath() . $this->info['ObjectName'];
    }

    public function objectPath(): string
    {
        return substr($this->info['Path'], strlen($this->info['StorageZoneName']) + 1) . $this->info['ObjectName'];
    }

    public function url(): string
    {
        return $this->host() . $this->path();
    }

    public function sha256(): string
    {
        return $this->info['Checksum'];
    }

    public function parent(): Directory
    {
        return $this->parent;
    }

    public function requestDownload(string $savePath, string $baseUrl): ResponseInterface
    {
        return $this->client->request('GET', $baseUrl . $this->objectPath(), ['buffer' => false, 'user_data' => $savePath]);
    }

    public function downloadOrigin(string $savePath)
    {
        return $this->requestDownload($savePath, $this->host() . '/' . $this->info['StorageZoneName'] . '/');
    }
}
