# Upload folder to BunnyCDN Storage

First set `BUNNY` environment variable to your access key.

```shell script
php index.php dir/ https://storage.bunnycdn.com/bucket/path/ 4
```

The number of concurrent transfers is default to 4. It can be changed with the third argument.
