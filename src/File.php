<?php
#+------------------------------------------------------------------
#| 普通的。
#+------------------------------------------------------------------
#| Author:Janmas Cromwell <janmas-cromwell@outlook.com>
#+------------------------------------------------------------------
namespace Janmas\Upload;

use think\exception\FileException;
use think\facade\Cache;

class File extends \SplFileInfo
{
    const MD5  = 1;
    const SHA1 = 2;

    /**
     * 后缀对应mime数组
     * @var array|mixed
     */
    protected $extMimeMaps = [];

    public $rootPath = '';

    public function __construct(string $path, bool $checkPath = true)
    {
        if ($checkPath && !is_file($path)) {
            throw new FileException(sprintf('The file "%s" does not exist', $path));
        }

        try {
            if (!Cache::has('ext.mime')) {
                $jsonFilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'JsonMap' . DIRECTORY_SEPARATOR . 'ext.mime.json';
                $this->extMimeMaps = json_decode(file_get_contents($jsonFilePath), true);
                Cache::forever('ext.mime', $this->extMimeMaps);
            } else {
                $this->extMimeMaps = Cache::get('ext.mime');
            }
        } catch (\Exception $e) {
            $jsonFilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'JsonMap' . DIRECTORY_SEPARATOR . 'ext.mime.json';
            $this->extMimeMaps = json_decode(file_get_contents($jsonFilePath), true);
        }
        parent::__construct($path);
    }

    /**
     * 获取mime
     * @return string
     */
    public function getMimeType(): string
    {
        $mime = isset($this->extMimeMaps[$this->getExtension()]) ? $this->extMimeMaps[$this->getExtension()] : null;
        return is_null($mime) ? 'text/plain' : $mime;
    }

    /**
     * 获取自定义文件类型
     * @return string
     */
    public function getDiyType(): string
    {
        $mime = $this->getMimeType();
        return substr($mime, 0, strpos($mime, '/'));
    }

    public function hashName(int $type = self::MD5): string
    {
        return $type == self::MD5 ? md5_file($this->getRealPath()) : sha1_file($this->getRealPath());
    }

    /**
     * 获取相对地址
     * @return string
     */
    public function getRelativePath(): string
    {
        return str_replace($this->rootPath, '', $this->getRealPath());
    }

    /**
     * 格式化文件大小
     * @return string
     */
    public function getFormatSize()
    {
        $size = $this->getSize();
        $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB', 'BB', 'NB', 'DB'];
        $i = 0;
        if ($size < 1024) {
            return $size . $unit[$i];
        }

        while ($size > 1024) {
            $size /= 1024;
            ++$i;
        }

        return sprintf('%.2f%s', $size, $unit[$i]);
    }

    public function getName()
    {
        return str_replace('.' . $this->getExtension(), '', $this->getFilename());
    }

    /**
     * 获取文件类型信息
     * @access public
     * @return string
     */
    public function getMime(): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        return finfo_file($finfo, $this->getPathname());
    }


    /**
     * 移动文件
     * @access public
     * @param string $directory 保存路径
     * @param string|null $name 保存的文件名
     * @return File
     */
    public function move(string $directory, string $name = null): File
    {
        $target = $this->getTargetFile($directory, $name);

        set_error_handler(function ($type, $msg) use (&$error) {
            $error = $msg;
        });
        $renamed = rename($this->getPathname(), (string)$target);
        restore_error_handler();
        if (!$renamed) {
            throw new FileException(sprintf('Could not move the file "%s" to "%s" (%s)', $this->getPathname(), $target, strip_tags($error)));
        }

        @chmod((string)$target, 0666 & ~umask());

        return $target;
    }

    /**
     * 实例化一个新文件
     * @param string $directory
     * @param null|string $name
     * @return File
     */
    protected function getTargetFile(string $directory, string $name = null): File
    {
        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new FileException(sprintf('Unable to create the "%s" directory', $directory));
            }
        } elseif (!is_writable($directory)) {
            throw new FileException(sprintf('Unable to write in the "%s" directory', $directory));
        }

        $target = rtrim($directory, '/\\') . \DIRECTORY_SEPARATOR . (null === $name ? $this->getBasename() : $this->getName($name));

        return new self($target, false);
    }

}
