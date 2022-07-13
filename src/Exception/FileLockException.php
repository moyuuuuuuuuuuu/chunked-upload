<?php
#+------------------------------------------------------------------
#| 普通的。
#+------------------------------------------------------------------
#| Author:Janmas Cromwell <janmas-cromwell@outlook.com>
#+------------------------------------------------------------------
namespace Janmas\Upload\Exception;

class FileLockException extends \Exception
{
    public function __construct(string $file = '', string $message = 'File locked, please wait', int $code = 30002)
    {
        $message .= ':' . $file;
        parent::__construct($message, $code);
    }
}
