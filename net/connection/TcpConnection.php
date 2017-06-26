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
	public $onClose = null;
	public $recvBuff = "";
	public $buffSize = 0;

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
			$this->destory();
			return false;
		} else {
			$this->recvBuff .= $buffer;
		}

		$protocol = $this->protocol;
		if ($this->protocol) {
		    while ($this->recvBuff !== "") {
			    $this->buffSize = $this->buffSize ? $this->buffSize : $protocol::input($buffer, $this);
			    if ($this->buffSize == 0) {
				   break;
			    }
			    if ($this->buffSize > strlen($this->recvBuff)) return;

			    if (strlen($this->buffSize) == $this->buffSize) {
				    $oneBuff = $this->recvBuff;
			    } else {
				    $oneBuff = substr($this->recvBuff, 0, $this->buffSize);
			    }
			    $this->recvBuff = "";
			    $this->buffSize = 0;

			    if (!$this->onMessage) return;

			    call_user_func($this->onMessage, $this, $protocol::decode($oneBuff, $this));
            }
		}

	}

	public function send($buff, $raw=false)
	{
		$protocol = $this->protocol;
		$sendContent = $raw ? $buff : $protocol::encode($buff, $this);

		if (!$sendContent) return null;

		$ret = fwrite($this->socket, $sendContent);
		if ($ret) return true;
	}

	public function getRemoteIp()
	{
		return $this->remoteIp;
	}

	public function destory()
	{
		Net::$event->removeReadStream($this->socket);
	}
}