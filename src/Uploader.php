<?php
#+------------------------------------------------------------------
#| 普通的。
#+------------------------------------------------------------------
#| Author:Janmas Cromwell <janmas-cromwell@outlook.com>
#+------------------------------------------------------------------
namespace Janmas\Upload;
class Uploader
{
    /**
     * 需要存储的文件位置
     * @var string
     */
    protected $savePath = '';

    /**
     * 分片文件存储位置
     * @var string
     */
    protected $chunkedPath = '';

    private $file;

    public function __construct(string $savePath = '', string $chunkedPath = '')
    {

        if (empty($savePath)) {
            throw new \Exception('请传入文件存储路径');
        }
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }

        $this->savePath = rtrim($savePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->initChunkedDir($chunkedPath);
    }

    private function initChunkedDir(string $chunkedPath = '')
    {
        if (is_dir($this->chunkedPath)) {
            return;
        }
        if (empty($chunkedPath)) {
            $rootPath = $_SERVER['DOCUMENT_ROOT'];
            $this->chunkedPath = $rootPath . DIRECTORY_SEPARATOR . 'chuked' . DIRECTORY_SEPARATOR;
        } else {
            $this->chunkedPath = rtrim($this->chunkedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
        if (!is_dir($this->chunkedPath)) {
            mkdir($this->chunkedPath, 0777, true);
        }
    }

    public function initUploadFile(string $name = 'file')
    {
        if (!isset($_FILES[$name])) {
            throw new \Exception('请选择上传的文件');
        }
        $this->file = new UploadedFile($_FILES[$name]['tmp_name'], $_FILES[$name]['name'], $_FILES[$name]['type'], $_FILES[$name]['error']);
        return $this;
    }

    public function chunk(int $index = 0, int $allNumber = 0)
    {
        $file = $this->file;
        $fileOriginalName = $file->getOriginalName();
        $fileOriginalExtension = $file->extension();

        $savePath = $this->chunkedPath . $fileOriginalName;

        if (!is_uploaded_file($file->getRealPath())) {
            throw new \Exception('临时目录中未找到该文件', 40004);
        }

        #木马扫描
        $this->checkHex($file);

        if (!is_writable(dirname($savePath))) {
            throw new \Exception('文件夹没有写入权限', 40003);
        }
        #TODO:此处仅仅检查了文件系统权限,还需要接入墨盘的文件处理权限(要不再外部处理此处是往)
        #2021/12/13 15:21 edit By janmas <janmas-cromwell@outlook.com>
        #TODO:修正以上逻辑：此处是往分片文件夹存放文件 墨盘文件权限逻辑应该在控制器里处理 此类只负责分片文件存储\删除等事宜

        #无分片上传
        if ($allNumber <= 0) {
            $savePath = $this->savePath . $fileOriginalName;
            if (is_file($savePath)) return true;
            return move_uploaded_file($file->getRealPath(), $savePath) && chmod($savePath, '644');
        }
        //分片处理
        if ($index < $allNumber && $allNumber > 0) {
            return move_uploaded_file($file->getRealPath(), $savePath);
        } else {
            $filename = str_replace('.' . $fileOriginalExtension, '', $fileOriginalName);
            $filename = substr($filename, 0, -2);
            $saveFilePath = $this->savePath . $filename . '.' . $fileOriginalExtension;
            if (is_file($saveFilePath)) {
                return true;
            } else {
                touch($saveFilePath);
            }

            if (!is_file($savePath) && !move_uploaded_file($file->getRealPath(), $savePath)) {
                throw new \Exception('文件上传失败', 50002);
            }
            $allNumber > 50 && ini_set('max_execution_time', '0');
            //文件名格式 aaaa.1.txt aaa.2.txt
            $i = 0;
            $handle = new Handle(LOCK_EX);
            $stream = $handle->fopen($saveFilePath, 'a');
            //搬迁到savePath并合并文件同时删除分片文件的碎片
            while ($i <= $index) {
                $chunkedFilePath = $this->chunkedPath . $filename . '.' . $i . '.' . $fileOriginalExtension;
                $fileContent = file_get_contents($chunkedFilePath);
                if (!fwrite($stream, $fileContent)) {
                    $handle->fclose();
                    unlink($saveFilePath);//上传失败则解锁文件并删除
                    throw new \Exception('文件上传失败', 50002);
                }
                ++$i;
            }
            $handle->fclose();
            chmod($saveFilePath, '644');
            //清空分片
            if ($this->clearDebris($filename)) {
                return $saveFilePath;
            }
            return false;
        }
    }

    /**
     * 上传成功清空碎片文件
     * @param string $fileName
     * @return bool
     */
    private function clearDebris(string $fileName = '')
    {
        $debrisFiles = glob($this->chunkedPath . $fileName . '*');
        foreach ($debrisFiles as $key => $file) {
            unlink($file);
        }
        $glob = glob($this->chunkedPath . $fileName . '*');
        return (bool)$glob;

    }

    /**
     * 木马特征检验
     * @param UploadedFile $file
     * @return bool
     * @throws \Exception
     */
    private function checkHex(UploadedFile $file)
    {
        if (file_exists($file->getRealPath())) {
            $resource = fopen($file->getRealPath(), 'rb');
            $fileSize = filesize($file->getRealPath());
            fseek($resource, 0);
            //把文件指针移到文件的开头
            if ($fileSize > 512) { // 若文件大于521B文件取头和尾
                $hexCode = bin2hex(fread($resource, 512));
                fseek($resource, $fileSize - 512);
                //把文件指针移到文件尾部
                $hexCode .= bin2hex(fread($resource, 512));
            } else { // 取全部
                $hexCode = bin2hex(fread($resource, $fileSize));
            }
            fclose($resource);
            /* 匹配16进制中的 <% ( ) %> */
            /* 匹配16进制中的 <? ( ) ?> */
            /* 匹配16进制中的 <script | /script> 大小写亦可*/
            /* 核心  整个类检测木马脚本的核心在这里  通过匹配十六进制代码检测是否存在木马脚本*/

            if (preg_match('/(3c25.*?28.*?29.*?253e)|(3c3f.*?28.*?29.*?3f3e)|(3C534352495054)|(2F5343524950543E)|(3C736372697074)|(2F7363726970743E)/is', $hexCode)) {
                throw new \Exception('未能通过安全检查的文件', 40003);
            } else {
                return true;
            }
        } else {
            throw new \Exception('文件丢失', 40004);
        }
    }
}
