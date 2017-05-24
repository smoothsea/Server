<?php
namespace net\connection;

use \net\Net;

class TcpConnection
{
	private $socket = null;
	private $remoteIp = "";

	public $onMessage = null;

	public function __construct ($socket, $remoteIp)
	{
		$this->socket = $socket;
		$this->remoteIp = $remoteIp;

		Net::$event->addReadStream($this->socket, [$this, "baseRead"]);
	}

	public function baseRead($connection)
	{
		$buffer = fread($connection, 8093);
		if ($buffer == "" || $buffer === false) {
			return false;
		}

		call_user_func($this->onMessage, $this);
		$httpHeader = "HTTP/1.1 200 OK\r\n" .
				"Server:self\r\n" .
				"Content-Type:text/html\r\n\r\n";
		fwrite($connection, $httpHeader."this is server ".getmypid(), 9999);
		fclose($connection);
	}

	public function send()
	{

	}
}