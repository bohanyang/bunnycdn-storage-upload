#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Directory;
use App\File;

require __DIR__ . '/vendor/autoload.php';

$browser = Directory::browse(getenv('BUNNY'), $argv[1]);

$localRoot = $argv[2];

$queue = [];
$queueCount = 0;
$transfers = 32;

$listUrls = function (array $items) use ($localRoot, &$listUrls, &$queue, $transfers, $browser, &$queueCount, $argv) {
    foreach ($items as $item) {
        if ($item instanceof Directory) {
            $listUrls($item->children());
        } elseif ($item instanceof File) {
            $localPath = $localRoot . $item->relativePath();
            if (
                file_exists($localPath) 
                && filesize($localPath) === $item->size() 
                //&& strtoupper(hash_file('sha256', $localPath, false)) === $item->sha256()
            ) {
                continue;
            }

            $queue[] = $item->requestDownload($localPath, $argv[3]);
            if (++$queueCount === $transfers) {
                $browser->download($queue);
                $queue = [];
                $queueCount = 0;
            }
        }
    }
};

$listUrls($browser->children());
