<?php

namespace Nece\Hound\Cloud\Storage;

use OSS\Core\OssException;
use OSS\Credentials\StaticCredentialsProvider;
use OSS\OssClient;
use Nece\Hound\Cloud\Storage\Consts;

class ALiYunOss extends ObjectStorage implements IStorage
{
    /**
     * OSS客户端
     *
     * @var OssClient
     */
    private $client;
    private $bucket;
    private $region;
    private $base_uri;
    private $object_meta_data = array();

    /**
     * 构造函数
     *
     * @author nece001@163.com
     * @create 2026-03-31 18:59:48
     *
     * @param string $accessKeyId 访问密钥ID
     * @param string $accessKeySecret 访问密钥
     * @param string $endpoint 端点 例如：oss-cn-hongkong.aliyuncs.com
     * @param string $region 区域 例如：cn-hongkong
     * @param string $bucket 存储桶
     * @param string $base_uri 基础URI，设置后返回以此为前缀的URL，不设置返回OSS给出的URL
     * @param integer $timeout 超时时间
     * @param integer $connect_timeout 连接超时时间
     * @param string $proxy 代理地址
     */
    public function __construct($accessKeyId, $accessKeySecret, $endpoint, $region, $bucket, $base_uri = '', $timeout = 10, $connect_timeout = 3, $proxy = null)
    {
        $provider = new StaticCredentialsProvider($accessKeyId, $accessKeySecret);
        $config = array(
            "provider" => $provider,
            "endpoint" => $endpoint,
            "signatureVersion" => OssClient::OSS_SIGNATURE_VERSION_V4,
            "region" => $region,
        );

        if ($proxy) {
            $config['request_proxy'] = $proxy;
        }

        $this->bucket = $bucket;
        $this->region = $region;
        $this->base_uri = rtrim(str_replace('\\', '/', $base_uri), '/');
        $this->client = new OssClient($config);
        $this->client->setTimeout($timeout);
        $this->client->setConnectTimeout($connect_timeout);
        $this->client->setUseSSL(false);
    }

    /**
     * @inheritdoc
     */
    public function exists(string $path): bool
    {
        try {
            $key = $this->keyPath($path);
            $result = $this->client->doesObjectExist($this->bucket, $key);
            if (!$result) {
                return $this->isDir($key);
            }
            return $result ? true : false;
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function isDir(string $key): bool
    {
        $options = array(
            OssClient::OSS_PREFIX => $key,
            OssClient::OSS_MAX_KEYS => 1,
        );

        $result = $this->client->listObjectsV2($this->bucket, $options);
        return $result->getKeyCount() > 0;
    }

    /**
     * @inheritdoc
     */
    public function isFile(string $key): bool
    {
        $key = $this->keyPath($key);
        $meta = $this->getObjectMeta($key);
        if ($meta && isset($meta['oss-request-url'])) {
            $path = trim(parse_url($meta['oss-request-url'], PHP_URL_PATH));
            // 最后一个字符不是/，说明是文件
            return substr($path, -1) !== '/';
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function copy(string $from, string $to): bool
    {
        $src = $this->keyPath($from);
        $dst = $this->keyPath($to);

        if (!$this->exists($from)) {
            throw new StorageException('源文件或目录不存在: ' . $src, Consts::ERROR_CODE_NOT_FOUND);
        }

        if ($this->isFile($src)) {
            $this->client->copyObject($this->bucket, $src, $this->bucket, $dst);
        } else {
            $src = $this->dirPath($from);
            $dst = $this->dirPath($to);

            $next_marker = '';
            while (true) {
                $options = array(
                    OssClient::OSS_PREFIX => rtrim($src, '/'),
                    OssClient::OSS_DELIMITER => '',
                    OssClient::OSS_MARKER => $next_marker,
                    OssClient::OSS_MAX_KEYS => 1000,
                );

                $result = $this->client->listObjects($this->bucket, $options);

                $next_marker = $result->getNextMarker();
                $prefixList = $result->getPrefixList();
                $objectList = $result->getObjectList();

                if ($prefixList) {
                    foreach ($prefixList as $prefix) {
                        $src_key = $prefix->getPrefix();
                        $dst_key = $dst . substr($src_key, strlen($src));
                        $this->mkdir($dst_key);
                    }
                }

                if ($objectList) {
                    foreach ($objectList as $object) {
                        $src_key = $object->getKey();
                        $dst_key = $dst . substr($src_key, strlen($src));
                        $this->client->copyObject($this->bucket, $src_key, $this->bucket, $dst_key);
                    }
                }

                if (!$next_marker) {
                    break;
                }
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function move(string $from, string $to): bool
    {
        $to = $this->keyPath($to);

        $this->copy($this->keyPath($from), $to);
        $this->delete($from);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function delete(string $path): bool
    {
        if ($this->isFile($path)) {
            $key = $this->keyPath($path);
            $this->client->deleteObject($this->bucket, $key);
        } else {
            $this->rmdir($path);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function mkdir(string $path, int $mode = 0755, bool $recursive = true): bool
    {
        // 填写目录名称，目录需以正斜线结尾。
        $path = trim($this->dirPath($path), '/');
        // $this->client->putObject($this->bucket, $path, '');
        $this->client->createObjectDir($this->bucket, $path);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function rmdir(string $path): bool
    {
        $key = $this->dirPath($path);

        $option = array(
            OssClient::OSS_PREFIX => $key,
            OssClient::OSS_DELIMITER => '',
        );

        while (true) {
            $result = $this->client->listObjects($this->bucket, $option);
            $list = $result->getObjectList();
            if ($list) {
                $delete_list = array();
                foreach ($list as $object) {
                    $delete_list[] = $object->getKey();
                }

                $this->client->deleteObjects($this->bucket, $delete_list);
            } else {
                break; // 一直删除到没有文件为止
            }
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function list(string $path, int $order = Consts::SCANDIR_SORT_ASCENDING, string $next_marker = '', $max_keys = 1000): array
    {
        $directory = $this->dirPath($path);

        $options = array(
            OssClient::OSS_PREFIX => '/' == $directory ? '' : $directory,
            OssClient::OSS_DELIMITER => '/',
            OssClient::OSS_MARKER => $next_marker,
            OssClient::OSS_MAX_KEYS => $max_keys,
        );

        $result = $this->client->listObjects($this->bucket, $options);
        $prefixList = $result->getPrefixList();
        $objectList = $result->getObjectList();
        $list = array();

        if ($prefixList) {
            foreach ($prefixList as $prefix) {
                $prefix = $prefix->getPrefix();
                if ($directory && 0 === strpos($prefix, $directory)) {
                    $prefix = substr($prefix, strlen($directory));
                }
                if ($prefix) {
                    $name = trim($prefix, '/');
                    $list[] = $this->buildObjectListItem($name, 0, true, 0, 0, 0);
                }
            }
        }

        if ($objectList) {
            foreach ($objectList as $object) {
                $key = $object->getKey();
                $name = $key;
                if ($directory && 0 === strpos($key, $directory)) {
                    $name = substr($key, strlen($directory));
                }

                if ($name) {
                    $mtime = strtotime($object->getLastModified());
                    $list[] = $this->buildObjectListItem($name, $object->getSize(), false, $mtime, $mtime, $mtime);
                }
            }
        }
        return $list;
    }

    /**
     * @inheritdoc
     */
    public function upload(string $local_src, string $to): bool
    {
        if (!file_exists($local_src)) {
            throw new StorageException('源文件或目录不存在', Consts::ERROR_CODE_NOT_FOUND);
        }

        if (is_file($local_src)) {
            $this->client->uploadFile($this->bucket, $this->keyPath($to), $local_src);
        } else {
            $to = trim($this->dirPath($to), '/'); // 去掉目录末尾的/，否则上传的目录会多一级目录
            $this->client->uploadDir($this->bucket, $to, $local_src);
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function download(string $src, string $local_dst): bool
    {
        $from = $this->keyPath($src);
        if ($this->isFile($from)) {
            $this->downloadFile($from, $local_dst);
        } else {
            $src = $this->dirPath($from);

            $next_marker = '';
            while (true) {
                $options = array(
                    OssClient::OSS_PREFIX => rtrim($src, '/'),
                    OssClient::OSS_DELIMITER => '',
                    OssClient::OSS_MARKER => $next_marker,
                    OssClient::OSS_MAX_KEYS => 1000,
                );

                $result = $this->client->listObjects($this->bucket, $options);

                $next_marker = $result->getNextMarker();
                $prefixList = $result->getPrefixList();
                $objectList = $result->getObjectList();

                if ($prefixList) {
                    foreach ($prefixList as $prefix) {
                        $src_key = $prefix->getPrefix();
                        $dst_dir = $local_dst . DIRECTORY_SEPARATOR . substr($src_key, strlen($src));
                        if (!file_exists($dst_dir)) {
                            mkdir($dst_dir, 0755, true);
                        }
                    }
                }

                if ($objectList) {
                    foreach ($objectList as $object) {
                        $src_key = $object->getKey();
                        $dst_file = $local_dst . DIRECTORY_SEPARATOR . substr($src_key, strlen($src));
                        $dst_file = str_replace('/', DIRECTORY_SEPARATOR, $dst_file);

                        $dir = dirname($dst_file);
                        if (!file_exists($dir)) {
                            mkdir($dir, 0755, true);
                        }

                        $this->downloadFile($src_key, $dst_file);
                    }
                }

                if (!$next_marker) {
                    break;
                }
            }
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function file(string $path): IObject
    {
        $key = $this->keyPath($path);
        $meta = $this->getObjectMeta($key);
        if (!$meta) {
            return ALiYunOssObject::createObject($this->client, $this->bucket, $key, 0, 0, 0, '', '', false);
        }
        return ALiYunOssObject::createObject($this->client, $this->bucket, $key, strtotime($meta['last-modified']), strtotime($meta['date']),  $meta['content-length'], $meta['content-type'], $meta['info']['url'], false);
    }

    /**
     * @inheritdoc
     */
    public function uri(string $path): string
    {
        return $this->keyPath($path);
    }

    /**
     * @inheritdoc
     */
    public function url(string $path): string
    {
        if ($this->base_uri) {
            return $this->base_uri . '/' . $this->keyPath($path);
        }

        $meta = $this->getObjectMeta($this->keyPath($path));
        if (!$meta) {
            return '';
        }
        return $meta['info']['url'];
    }

    /**
     * 下载文件到本地目录
     *
     * @author nece001@163.com
     * @create 2026-04-02 09:41:22
     *
     * @param string $key
     * @param string $local_file
     * @return string|null
     */
    private function downloadFile(string $key, string $local_file)
    {
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $local_file
        );

        return $this->client->getObject($this->bucket, $key, $options);
    }

    /**
     * 获取对象元数据
     *
     * @author nece001@163.com
     * @create 2026-03-31 20:10:35
     *
     * @param string $key 对象键值
     * @return array
     */
    private function getObjectMeta($key)
    {
        if (!isset($this->object_meta_data[$key])) {
            try {
                $this->object_meta_data[$key] = $this->client->getObjectMeta($this->bucket, $key);
            } catch (OssException $e) {
                // 目录不存在，尝试添加/
                if ($e->getHTTPStatus() == 404) {
                    try {
                        $this->object_meta_data[$key] = $this->client->getObjectMeta($this->bucket, $key . '/');
                    } catch (OssException $e) {
                        if ($e->getHTTPStatus() == 404) {
                            return null;
                        }
                    }
                }
            }
        }
        return $this->object_meta_data[$key];
    }
}
