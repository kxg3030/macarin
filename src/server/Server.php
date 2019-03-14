<?php
/**
 * Created by PhpStorm.
 * User: lance
 * Date: 2019/3/7
 * Time: 16:02
 */

namespace main\server;
use main\common\Singleton;
use main\server\socket\Socket;

class Server{
    use Singleton;
    public $socket = null;

    public function __construct(Socket  $socket = null){
        $this->socket = $socket;
    }

    public function setSocket(Socket  $socket){
        $this->socket = $socket;
    }

    public function getSocket():Socket{
        return $this->socket;
    }
}