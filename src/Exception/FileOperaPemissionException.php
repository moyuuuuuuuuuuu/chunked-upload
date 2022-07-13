<?php
#+------------------------------------------------------------------
#| 普通的。
#+------------------------------------------------------------------
#| Author:Janmas Cromwell <janmas-cromwell@outlook.com>
#+------------------------------------------------------------------
namespace Janmas\Upload\Exception;

class FileOperaPemissionException extends \Exception
{
    protected $opera      = '';
    protected $fileName   = '';
    protected $statusCode = 200;

    public function __construct($file = '', string $opera = '', $code = 40003, $statusCode = 200)
    {
        $opera = $this->setOpera($opera);
        $this->fileName = $file;
        $message = "No permission to {$opera} files";
        $message .= ":[{$file}]";
        $this->statusCode = $statusCode;
        parent::__construct($message, $code);
    }

    /**
     * @return int|mixed
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getOpera(): string
    {
        return $this->opera;
    }

    /**
     * @return mixed|string
     */
    public function getFileName()
    {
        return $this->fileName;
    }


    protected function setOpera($opera)
    {
        switch ($opera) {
            case 'w' || 'a':
                $this->opera = 'write';
                break;
            case 'r':
                $this->opera = 'read';
                break;
            case 'd':
                $this->opera = 'delete';
                break;
            case 's':
                $this->opera = 'share';
                break;
        }
    }
}
