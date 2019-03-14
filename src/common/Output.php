<?php
/**
 * Created by PhpStorm.
 * User: lance
 * Date: 2019/3/7
 * Time: 16:23
 */

namespace main\common;


class Output{
    public static $logFile = SRC_PATH."runtime/master.log";
    public static function writeException($message){
        throw new \RuntimeException($message);
    }

    public static function writeToStdout($message){
        global $stdout;
        fwrite($stdout,$message.PHP_EOL);
    }

    public static function writeLog($message){
        $message = sprintf("[record] ".date("Y-m-d H:i:s",time())." %s",$message);
        $message = $message.PHP_EOL;
        file_put_contents(self::$logFile,$message,FILE_APPEND | LOCK_EX);
    }

    public static function writeAndExit($message){
        self::writeLog($message);
        sleep(1);
        exit(250);
    }

    public static function writeStatus($statusFile,$message){
        file_put_contents($statusFile,$message.PHP_EOL,FILE_APPEND | LOCK_EX);
    }

    public static function writeLine($key,$value){
        global $stdout,$stderr;
        fwrite($stdout,PHP_EOL);
        fwrite($stdout,"\e[32m".str_pad($key, 43, ' ', STR_PAD_RIGHT)."\e[34m".$value."\e[0m\n") ;
    }

    public static function writeLogo(){
        global $stdout;
        $logo = <<< EOF
 __    __     ______     ______     ______     ______     __     __   __    
/\ "-./  \   /\  __ \   /\  ___\   /\  __ \   /\  == \   /\ \   /\ "-.\ \   
\ \ \-./\ \  \ \  __ \  \ \ \____  \ \  __ \  \ \  __<   \ \ \  \ \ \-.  \  
 \ \_\ \ \_\  \ \_\ \_\  \ \_____\  \ \_\ \_\  \ \_\ \_\  \ \_\  \ \_\\"\_ \ 
  \/_/  \/_/   \/_/\/_/   \/_____/   \/_/\/_/   \/_/ /_/   \/_/   \/_/ \/_/ 
EOF;
        fwrite($stdout,$logo.PHP_EOL);
    }

    public static function writeHelp(){
        global $argv;
        $detail = array_shift($argv);
        self::writeLogo();
        switch ($detail) {
            case 'start':
                echo <<<HELP_START
\e[33m操作:\e[0m
\e[31m  php index.php start\e[0m
\e[33m简介:\e[0m
\e[36m  执行本命令可以启动框架 可选的操作参数如下\e[0m
\e[33m参数:\e[0m
\e[32m  -d \e[0m            以守护模式启动框架

HELP_START;
                break;
            case 'stop':
                echo <<<HELP_STOP
\e[33m操作:\e[0m
\e[31m  php index.php stop\e[0m
\e[33m简介:\e[0m
\e[36m  执行本命令可以停止框架\e[0m


HELP_STOP;
                break;
            case 'reload':
                echo <<<HELP_STOP
\e[33m操作:\e[0m
\e[31m  php index.php reload\e[0m
\e[33m简介:\e[0m
\e[36m  执行本命令可以重启所有Worker进程\e[0m

HELP_STOP;
                break;
            case 'restart':
                echo <<<HELP_INSTALL
\e[33m操作:\e[0m
\e[31m  php index.php restart\e[0m
\e[33m简介:\e[0m
\e[36m  停止并重新启动服务 可选的操作参数如下\e[0m
\e[33m参数:\e[0m
\e[32m  -d \e[0m        以静默模式重启框架\n
HELP_INSTALL;
                break;

            default:
                self::writeWelcome();
        }
        exit(0);
    }

    public static function writeWelcome(){
        global $stdout;
        $welcome = <<< EOF
\n欢迎使用多进程\e[32m Macarin\e[0m 框架 当前版本: \e[34m1.x\e[0m
\e[33m使用:\e[0m
  php index.php [操作] [选项]
\e[33m操作:\e[0m
\e[32m  start \e[0m        启动服务
\e[32m  stop  \e[0m        停止服务
\e[32m  reload \e[0m       重载服务
\e[32m  restart \e[0m      重启服务
\e[32m  status  \e[0m      查看状态
\e[32m  help \e[0m         查看命令的帮助信息\n
EOF;
        fwrite($stdout,$welcome);
        exit(0);
    }

}