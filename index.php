#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Upload;

require __DIR__ . '/vendor/autoload.php';

$key = getenv('BUNNY');

if ($argc < 3 || $key === false) {
    exit('Usage: "BUNNY=access_key php index.php dir/ https://storage.bunnycdn.com/bucket/path/ [concurrent_transfers]"');
}

Upload::execute($argv[1], $argv[2], $key, (int) ($argv[3] ?? 4));
