<?php
#+------------------------------------------------------------------
#| 普通的。
#+------------------------------------------------------------------
#| Author:Janmas Cromwell <janmas-cromwell@outlook.com>
#+------------------------------------------------------------------

include '../vendor/autoload.php';

$name = $_GET['name'];
$index = $_GET['index'];
$all = $_GET['all'];
(new \Janmas\Upload\Uploader())->initUploadFile($name)->chunk($index, $all);
