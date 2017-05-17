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

		$that = $this;
		Net::$event->addReadStream($this->socket, function ($connection) use ($that) {
			$that->baseRead($connection);
		});
	}

	public function baseRead($connection)
	{
		var_dump(2);
		$buffer = fread($connection, 8093);
		if ($buffer == "" || $buffer === false) {

		}
		$httpHeader = "HTTP/1.1 200 OK\r\n" .
				"Server:self\r\n" .
				"Content-Type:text/html\r\n\r\n";
		fwrite($connection, $httpHeader."this is server ".getmypid(), 9999);
		fclose($connection);
	}


}