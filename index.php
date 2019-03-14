<?php
require_once __DIR__."/vendor/autoload.php";
require_once __DIR__."/src/constant/defined.php";
$app = new \main\App();
$app->onWorkStart = function (\main\App $app){

};
$app->onWorkStop = function (\main\App $app){

};
\main\App::run();
