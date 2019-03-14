<?php
namespace main\common;

trait Singleton{
    private static $instance = null;
    private $queue;

    private function __construct($args){

    }

    /**
     * @param array $args
     * @return static
     */
    public static function instance($args = null){
        self::$instance || self::$instance = new self($args);
        return self::$instance;
    }

    private function __clone(){

    }

    private function __wakeup(){

    }
}