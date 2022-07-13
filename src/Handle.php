<?php
#+------------------------------------------------------------------
#| 普通的。
#+------------------------------------------------------------------
#| Author:Janmas Cromwell <janmas-cromwell@outlook.com>
#+------------------------------------------------------------------
namespace Janmas\Upload;

use Janmas\Upload\Exception\{FileException, FileLockException, FileOperaPemissionException};

class Handle
{
    /**
     * 文件路径
     * @var string
     */
    protected $file;

    /**
     * 操作模式
     * @var string
     */
    protected $mode = '';

    /**
     * 文件锁
     * @var int
     */
    protected $lock = 0;

    /**
     * 句柄资源
     * @var
     */
    protected $stream;

    /**
     * 是否已经加锁
     * @var bool
     */
    protected $locked = false;

    /**
     * 加锁重试次数 每次睡500毫秒
     * @var int
     */
    protected $lockedRetryNumber = 0;

    /**
     * 文件内容
     * @var File
     */
    protected $splFileInfo;

    protected $needFileInfo = false;

    public function __construct(int $lock = LOCK_SH | LOCK_NB, int $lockedRetryNumber = 5)
    {
        $this->lock = $lock;
        $this->lockedRetryNumber = $lockedRetryNumber;
    }

    public function fopen(string $file, string $mode = 'r')
    {
        if (!is_file($file)) {
            throw new FileException($file);
        }
        $opera = strlen($mode) > 1 ? substr($mode, 0, 1) : $mode;
        if ($opera == 'r') {
            if (!is_readable($file)) {
                throw new FileOperaPemissionException($file, $opera);
            }
        } else {
            if (!is_writable($file)) {
                throw new FileOperaPemissionException($file, $opera);
            }
        }

        $this->file = $file;
        $this->mode = $mode;
        $this->stream = fopen($file, $mode);

        if ($this->lock > 0) {
            $this->lock();
        }
        return $this->stream;
    }

    public function lock()
    {
        $this->locked = flock($this->stream, $this->lock);
        $i = 0;
        while (!$this->locked) {
            if ($i >= $this->lockedRetryNumber) {
                throw new FileLockException($this->file);
            }
            $this->locked = flock($this->stream, $this->lock);
            ++$i;
            usleep(500);
        }
    }

    public function fclose()
    {
        if ($this->locked) {
            flock($this->stream, LOCK_UN);
        }

        fclose($this->stream);
    }

    public function getSplFileInfo()
    {
        if (is_null($this->splFileInfo)) {
            $this->splFileInfo = new File($this->file);
        }
        return $this->splFileInfo;
    }

    public function feof()
    {
        return feof($this->stream);
    }

    public function read()
    {
        function read(Handle $fileObj)
        {
            while (!$fileObj->feof()) {
                yield fgets($fileObj->getStream());
            }
        }

        $reader = read($this);
        $content = '';
        foreach ($reader as $c) {
            $content .= $c;
        }
        return $content;
    }

    public function getStream()
    {
        return $this->stream;
    }

    public function __destruct()
    {
        $this->fclose();
    }

}
