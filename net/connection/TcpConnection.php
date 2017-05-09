<?php
namespace net\connection;

use \net\Net;

class TcpConnection
{
	private $socket = null;
	private $remoteIp = "";

	public function __construct ($socket, $remoteIp)
	{
		$this->socket = $socket;
		$this->remoteIp = $remoteIp;

		$c = stream_set_blocking($this->socket, 0);
		$this->socket = fopen("http://265g.com", "r");

		$event = event_new();
		event_set($event, $this->socket, EV_READ | EV_PERSIST, [$this, "baseRead"]);
		event_base_set($event, Net::$event);
		event_add($event);
	}

	public function baseRead($connection)
	{
		var_dump(4);
		$request = fread($connection, 8093);
		echo $request;
		$httpHeader = "HTTP/1.1 200 OK\r\n" .
				"Server:self\r\n" .
				"Content-Type:text/html\r\n\r\n";
		fwrite($connection, $httpHeader."this is server ".getmypid(), 9999);
		fclose($connection);
	}


}