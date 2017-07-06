<?php
include_once "./net/autoload.php";

use net\Net;

$net = new Net("192.168.2.233", "8001", "http");
$net->count = 1;        //TODO 多进程报错

$net->onMessage = function ($connection, $data) {
	$connection->send("hello world");
};

$net->onClose = function ($connection) {
    echo "close";
};

$net->run();