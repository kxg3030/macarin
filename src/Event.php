<?php
/**
 * Created by PhpStorm.
 * User: lance
 * Date: 2019/3/11
 * Time: 15:58
 */

namespace main;


use main\common\Singleton;
use main\server\Server;
use main\server\socket\Socket;

class Event{
    use Singleton;
    private $server = null;

    public function __construct(Socket $socket){
        $this->server = $socket;
        $this->server->callback["onConnect"] = [$this,"onConnect"];
        $this->server->callback["onReceive"] = [$this,"onReceive"];
        $this->server->callback["onClose"]   = [$this,"onClose"];
    }

    public function getSocket():Socket{
        return $this->server;
    }

    public function onConnect($connect,$server){
        echo "connect".PHP_EOL;

    }

    public function onReceive($connect,Socket $server,$data){
        print_r($data.PHP_EOL);
        $result = $server->send($connect,$data);
        if($result == false){
            $server->close($connect);
        }
    }

    public function onClose($connect,Socket $server){
        echo "close".PHP_EOL;
    }

}