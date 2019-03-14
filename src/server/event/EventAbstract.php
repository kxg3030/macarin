<?php
/**
 * Created by PhpStorm.
 * User: lance
 * Date: 2019/3/12
 * Time: 9:26
 */
namespace main\server\event;

use main\server\Server;

abstract class EventAbstract{
    protected $server = [];

    public function addServer(Server $server){
        $this->server[] = $server;
    }

    public function trigger(){

    }
}