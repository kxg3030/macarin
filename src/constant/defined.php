<?php
defined("DS")        || define("DS",DIRECTORY_SEPARATOR);
defined("ROOT_PATH") || define("ROOT_PATH",__DIR__."/../../");
defined("SRC_PATH")  || define("SRC_PATH",ROOT_PATH."/src/");
date_default_timezone_set("Asia/Shanghai");
set_error_handler(function ($errNo, $errMsg, $errFile, $errLine){
    $message = [
        "msg" => $errMsg,
        "line"=> $errLine,
        "code"=> $errNo,
        "file"=> $errFile,
        "type"=> "error"
    ];
    \main\common\Output::writeLog(json_encode($message,256));
});

set_exception_handler(function (Exception $exception){
    $message = [
        "msg" => $exception->getMessage(),
        "line"=> $exception->getLine(),
        "code"=> $exception->getCode(),
        "file"=> $exception->getFile(),
        "type"=> "exception"
    ];
    \main\common\Output::writeLog(json_encode($message,256));
});
