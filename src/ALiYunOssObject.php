<?php

namespace Nece\Hound\Cloud\Storage;

use OSS\OssClient;

class ALiYunOssObject implements IObject
{
    /**
     * OSS客户端
     *
     * @var OssClient
     */
    private $client;

    /**
     * 对象元数据
     *
     * @var array
     */
    private $info = array();

    public static function createObject(OssClient $client, string $bucket, string $key, int $mtime, int $atime, int $size, string $mime_type, string $url, bool $is_dir)
    {
        $info = array(
            'bucket' => $bucket,
            'key' => $key,
            'mtime' => $mtime,
            'atime' => $atime,
            'size' => $size,
            'mime_type' => $mime_type,
            'url' => $url,
            'is_dir' => $is_dir,
        );
        return new self($client, $info);
    }

    public function __construct(OssClient $client, array $info)
    {
        $this->client = $client;
        $this->info = $info;
    }

    /**
     * @inheritdoc
     */
    public function getAccessTime(): int
    {
        return $this->info['atime'];
    }

    /**
     * @inheritdoc
     */
    public function getCreateTime(): int
    {
        return $this->getModifyTime();
    }

    /**
     * @inheritdoc
     */
    public function getModifyTime(): int
    {
        return $this->info['mtime'];
    }

    /**
     * @inheritdoc
     */
    public function getBasename(string $suffix = ""): string
    {
        return basename($this->getKey(), $suffix);
    }

    /**
     * @inheritdoc
     */
    public function getExtension(): string
    {
        return pathinfo($this->getKey(), PATHINFO_EXTENSION);
    }

    /**
     * @inheritdoc
     */
    public function getFilename(): string
    {
        return pathinfo($this->getKey(), PATHINFO_FILENAME);
    }

    /**
     * @inheritdoc
     */
    public function getPath(): string
    {
        return dirname($this->getKey());
    }

    /**
     * @inheritdoc
     */
    public function getRealname(): string
    {
        return $this->getKey();
    }

    /**
     * @inheritdoc
     */
    public function getKey(): string
    {
        return $this->info['key'];
    }

    /**
     * @inheritdoc
     */
    public function getSize(): int
    {
        return $this->info['size'];
    }

    /**
     * @inheritdoc
     */
    public function getMimeType(): string
    {
        return $this->info['mime_type'];
    }

    /**
     * @inheritdoc
     */
    public function isDir(): bool
    {
        return $this->info['is_dir'];
    }

    /**
     * @inheritdoc
     */
    public function isFile(): bool
    {
        return !$this->isDir();
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->client->getObject($this->info['bucket'], $this->getKey());
    }

    /**
     * @inheritdoc
     */
    public function putContent(string $content, bool $append = false): bool
    {
        if ($append) {
            // $this->client->appendObject($this->info['bucket'], $this->getKey(), $content, $this->getSize());

            $tmp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aliyun_oss_append_tmp_file_' . rand();
            $options = [
                OssClient::OSS_FILE_DOWNLOAD => $tmp_file,
            ];
            $this->client->getObject($this->info['bucket'], $this->getKey(), $options);

            file_put_contents($tmp_file, $content, FILE_APPEND);
            $content = file_get_contents($tmp_file);
            $this->client->putObject($this->info['bucket'], $this->getKey(), $content);
            unlink($tmp_file);
        } else {
            $this->client->putObject($this->info['bucket'], $this->getKey(), $content);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function delete(): bool
    {
        $this->client->deleteObject($this->info['bucket'], $this->getKey());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return $this->getKey();
    }
}

/*
目录数据：
Array
(
    [server] => AliyunOSS
    [date] => Tue, 31 Mar 2026 11:48:34 GMT
    [content-type] => application/octet-stream
    [content-length] => 0
    [connection] => keep-alive
    [x-oss-request-id] => 69CBB492AB4B8130384B7032
    [accept-ranges] => bytes
    [etag] => "D41D8CD98F00B204E9800998ECF8427E"
    [last-modified] => Tue, 31 Mar 2026 11:48:31 GMT
    [x-oss-object-type] => Normal
    [x-oss-hash-crc64ecma] => 0
    [x-oss-storage-class] => Standard
    [content-md5] => 1B2M2Y8AsgTpgAmY7PhCfg==
    [x-oss-server-time] => 2
    [info] => Array
        (
            [url] => http://meifubd-test-hk.oss-cn-hongkong.aliyuncs.com/b/
            [content_type] => application/octet-stream
            [http_code] => 200
            [header_size] => 456
            [request_size] => 581
            [filetime] => 1774957711
            [ssl_verify_result] => 0
            [redirect_count] => 0
            [total_time] => 1.423081
            [namelookup_time] => 0.005963
            [connect_time] => 0.026384
            [pretransfer_time] => 0.026769
            [size_upload] => 0
            [size_download] => 0
            [speed_download] => 0
            [speed_upload] => 0
            [download_content_length] => 0
            [upload_content_length] => -1
            [starttransfer_time] => 1.423028
            [redirect_time] => 0
            [redirect_url] =>
            [primary_ip] => 198.18.0.101
            [certinfo] => Array
                (
                )

            [primary_port] => 80
            [local_ip] => 198.18.0.1
            [local_port] => 54565
            [http_version] => 2
            [protocol] => 1
            [ssl_verifyresult] => 0
            [scheme] => HTTP
            [appconnect_time_us] => 0
            [connect_time_us] => 26384
            [namelookup_time_us] => 5963
            [pretransfer_time_us] => 26769
            [redirect_time_us] => 0
            [starttransfer_time_us] => 1423028
            [total_time_us] => 1423081
            [method] => HEAD
        )

    [oss-request-url] => http://meifubd-test-hk.oss-cn-hongkong.aliyuncs.com/b/
    [oss-redirects] => 0
    [oss-stringtosign] => OSS4-HMAC-SHA256
20260331T114833Z
20260331/cn-hongkong/oss/aliyun_v4_request
8ef79816fc8f5ad7e70487e7d9236bae9d8329690068c88be3a7ea52ff5151ae
    [oss-requestheaders] => Array
        (
            [Host] => meifubd-test-hk.oss-cn-hongkong.aliyuncs.com
            [Content-Type] => application/octet-stream
            [Date] => Tue, 31 Mar 2026 11:48:33 GMT
            [x-oss-date] => 20260331T114833Z
            [x-oss-content-sha256] => UNSIGNED-PAYLOAD
            [Authorization] => OSS4-HMAC-SHA256 Credential=LTAI5tFmhY77k59dwbgMxpmh/20260331/cn-hongkong/oss/aliyun_v4_request,Signature=f260d63862a370fab4a7f609504a1809da1ff0b12d5a552c2b19eed52c5599a0
        )

)


文件数据：
Array
(
    [server] => AliyunOSS
    [date] => Tue, 31 Mar 2026 11:54:15 GMT
    [content-type] => image/jpeg
    [content-length] => 49942
    [connection] => keep-alive
    [x-oss-request-id] => 69CBB5E74C8B37373594B1A0
    [accept-ranges] => bytes
    [etag] => "31C33CCB25E26ED25EFCFC5D90501461"
    [last-modified] => Tue, 31 Mar 2026 11:54:15 GMT
    [x-oss-object-type] => Normal
    [x-oss-hash-crc64ecma] => 9303451086292200529
    [x-oss-storage-class] => Standard
    [content-md5] => McM8yyXibtJe/PxdkFAUYQ==
    [x-oss-server-time] => 2
    [info] => Array
        (
            [url] => http://meifubd-test-hk.oss-cn-hongkong.aliyuncs.com/b/1.jpeg
            [content_type] => image/jpeg
            [http_code] => 200
            [header_size] => 464
            [request_size] => 593
            [filetime] => 1774958055
            [ssl_verify_result] => 0
            [redirect_count] => 0
            [total_time] => 0.297862
            [namelookup_time] => 0.000923
            [connect_time] => 0.021743
            [pretransfer_time] => 0.022013
            [size_upload] => 0
            [size_download] => 0
            [speed_download] => 0
            [speed_upload] => 0
            [download_content_length] => 49942
            [upload_content_length] => -1
            [starttransfer_time] => 0.297811
            [redirect_time] => 0
            [redirect_url] =>
            [primary_ip] => 198.18.0.101
            [certinfo] => Array
                (
                )

            [primary_port] => 80
            [local_ip] => 198.18.0.1
            [local_port] => 55656
            [http_version] => 2
            [protocol] => 1
            [ssl_verifyresult] => 0
            [scheme] => HTTP
            [appconnect_time_us] => 0
            [connect_time_us] => 21743
            [namelookup_time_us] => 923
            [pretransfer_time_us] => 22013
            [redirect_time_us] => 0
            [starttransfer_time_us] => 297811
            [total_time_us] => 297862
            [method] => HEAD
        )

    [oss-request-url] => http://meifubd-test-hk.oss-cn-hongkong.aliyuncs.com/b/1.jpeg
    [oss-redirects] => 0
    [oss-stringtosign] => OSS4-HMAC-SHA256
20260331T115415Z
20260331/cn-hongkong/oss/aliyun_v4_request
c27597f95310f0421735bcf62195b5e3ac998d21a571d33c527cc4c1131ed228
    [oss-requestheaders] => Array
        (
            [Host] => meifubd-test-hk.oss-cn-hongkong.aliyuncs.com
            [Content-Type] => application/octet-stream
            [Date] => Tue, 31 Mar 2026 11:54:15 GMT
            [x-oss-date] => 20260331T115415Z
            [x-oss-content-sha256] => UNSIGNED-PAYLOAD
            [Authorization] => OSS4-HMAC-SHA256 Credential=LTAI5tFmhY77k59dwbgMxpmh/20260331/cn-hongkong/oss/aliyun_v4_request,Signature=19d2cf34f8c677ee08334e75639b4dfa6424fe835b9bb5b7223aaabaa9f57310
        )

)
*/