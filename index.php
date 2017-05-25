<?php
include_once "./net/autoload.php";

use net\Net;

$net = new Net("192.168.9.60", "8001", "http");
$net->count = 1;        //TODO 多进程报错

$net->onMessage = function ($connection) {
	$html = file_get_contents("application/time.html");
	$connection->send($html);
};

$net->run();