<?php
#+------------------------------------------------------------------
#| 普通的。
#+------------------------------------------------------------------
#| Author:Janmas Cromwell <janmas-cromwell@outlook.com>
#+------------------------------------------------------------------
namespace Janmas\Upload\Exception;

class FileException extends \Exception
{
    protected $fileName;

    public function __construct(string $file = '', $message = 'File does not exist', int $code = 40004)
    {
        $this->fileName = basename($file);
        $message .= ':' . $this->fileName;
        parent::__construct($message, $code);
    }

    public function getFileName()
    {
        return $this->fileName;
    }
}
