<?php
/**
 * Created by PhpStorm.
 * User: lance
 * Date: 2019/3/12
 * Time: 9:22
 */

namespace main\server\socket;

use main\App;

class Socket{
    public  $host   = "0.0.0.0";
    public  $port   = 9502;
    public  $server = null;
    private $isRun  = false;
    private $backlog= 1<<8;
    public  $connect= [];
    public  $callback;
    public  $worker = [];

    /**
     * @throws \Exception
     */
    public function start(){
        try{
            $this->create();
            $this->bind();
            $this->setOption();
            $this->listen();
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function create(){
        # AF_INET:IPV4的套接字;SOCK_STREAM：基于流的数据格式;SOL_TCP:Tcp连接
        $this->server = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        if($this->server == false){
            throw new \Exception($this->getError());
        }
    }

    /**
     * @throws \Exception
     */
    public function bind(){
        if($this->server == false){
            $this->create();
        }
        # 设置IP复用
        socket_setopt($this->server,SOL_SOCKET,SO_REUSEADDR,1);
        # 设置PORT复用
        socket_setopt($this->server,SOL_SOCKET,SO_REUSEPORT,1);
        # 绑定到地址和端口
        socket_bind($this->server,$this->host,$this->port);
    }

    /**
     *set option
     */
    public function setOption(){
        if($this->server){
            # 设置接受消息超时时间
            socket_set_option($this->server,SOL_SOCKET,SO_RCVTIMEO,array("sec"=>60, "usec"=>0 ) );
            # 设置发送超时时间
            socket_set_option($this->server,SOL_SOCKET,SO_SNDTIMEO,array("sec"=>3, "usec"=>0 ) );
        }
    }

    public function getError(){
        return socket_strerror(socket_last_error($this->server));
    }

    /**
     * @throws \Exception
     */
    public function listen(){
        if($this->server == null){
            $this->bind();
        }
        socket_listen($this->server,$this->backlog);
        $this->isRun = true;
    }

    /**
     * @param $connect
     * @return $this
     */
    public function addConnect($connect){
        $this->connect[(string)$connect]["fd"] = $connect;
        return $this;
    }

    /**
     * @param $connect
     */
    public function callback($connect,$callback,$data = []){
        if(isset($this->callback[$callback])){
            call_user_func($this->callback[$callback],$connect,$this,$data);
        }
    }

    public function addConnectProcess($connect,$pid){
        $this->worker[(string)$connect]["pid"] = $pid;
    }

    /**
     * @param $connect
     */
    public function readData($connect){
        // 创建子进程
        $pid = pcntl_fork();
        if($pid == 0){
            cli_set_process_title("socket-worker(index.php)");
            $this->addConnectProcess($connect,posix_getpid());
            App::$isManager = false;
            App::$isMaster  = false;
            while(isset($this->connect[(string)$connect]) && $this->isRun){
                @socket_recv($connect,$data,1024,MSG_WAITALL);
                // 客户端断开
                if($data === false){
                    $this->close($connect);
                }else{
                    $this->callback($connect,"onReceive",$data);
                }
            }
        }
        App::$childPro[$pid] = [
            "pid"      => $pid,
            "tsk"      => "null",
            "workerId" => 0,
            "role"     => "manager-socket-worker"
        ];
    }

    public function close($connect){
        $this->callback($connect,"onClose");
        socket_close($connect);
        unset($this->connect[(string)$connect]);
    }

    public function closeAll(){
        if($this->connect){
            foreach ($this->connect as $key => $sock){
                socket_close($sock["fd"]);
                unset($this->connect[$key]);
            }
        }
    }

    public function send($connect,$data){
        if($connect){
            $result = socket_send($connect,$data,strlen($data),0);
            return $result;
        }
    }

    public static function init(){

    }
}