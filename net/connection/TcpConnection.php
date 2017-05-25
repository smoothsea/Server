<?php
namespace net\connection;

use \net\Net;

class TcpConnection
{
	const READ_BUFFER_SIZE = 65535;
	private $socket = null;
	private $remoteIp = "";
	public $protocol = "";
	public $onMessage = null;
	public $recvBuff = "";

	public function __construct($socket, $remoteIp)
	{
		$this->socket = $socket;
		$this->remoteIp = $remoteIp;

		Net::$event->addReadStream($this->socket, [$this, "baseRead"]);
	}

	public function baseRead($connection)
	{
		$buffer = fread($connection, self::READ_BUFFER_SIZE);
		if ($buffer == "" || $buffer === false) {
			return false;
		} else {
			$this->recvBuff .= $buffer;
		}

		$protocol = $this->protocol;
		call_user_func($this->onMessage, $this, $protocol::decode($buffer, $this));
	}

	public function send($buff)
	{
		$protocol = $this->protocol;
		$sendContent = $protocol::encode($buff, $this);

		if (!$sendContent) return null;

		$ret = fwrite($this->socket, $sendContent);
		if ($ret) return true;
	}

	public function getRemoteIp()
	{
		return $this->remoteIp;
	}
}