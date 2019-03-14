<?php
namespace main\app;

interface AppInterface{
    public static function start();
    public static function stop();
    public static function restart();


}