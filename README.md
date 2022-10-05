# Upload folder to BunnyCDN Storage

The script requires [PHP](https://www.php.net/downloads.php) 7.2+ and [composer](https://getcomposer.org/download/)

Tested on PHP 7.4 (Windows & Linux)

Set `BUNNY` environment variable to your access key.

Example usage:

```shell script
git clone https://github.com/bohanyang/bunnycdn-storage-upload.git && cd bunnycdn-storage-upload
composer install --no-dev -o
read -s BUNNY
export BUNNY
php index.php dir/ https://storage.bunnycdn.com/bucket/path/ 4
```

The number of concurrent transfers is default to 4. It can be changed with the third argument.
