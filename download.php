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

$clearQueue = function () use (&$queue, $browser, &$queueCount) {
    if ($queue !== []) {
        $browser->download($queue);
    }
    
    $queue = [];
    $queueCount = 0;
};

$listUrls = function (array $items) use ($localRoot, &$listUrls, &$queue, $transfers, &$queueCount, $argv, $clearQueue) {
    foreach ($items as $item) {
        if ($item instanceof Directory) {
            $listUrls($item->children());
        } elseif ($item instanceof File) {
            $localPath = $localRoot . $item->relativePath();
            if (
                file_exists($localPath) 
                && filesize($localPath) === $item->size() 
                && strtoupper(hash_file('sha256', $localPath, false)) === $item->sha256()
            ) {
                continue;
            }

            if (empty($argv[3])) {
                $queue[] = $item->downloadOrigin($localPath);
            } else {
                $queue[] = $item->requestDownload($localPath, $argv[3]);
            }
            
            if (++$queueCount === $transfers) {
                $clearQueue();
            }
        }
    }

    $clearQueue();
};

$listUrls($browser->children());
