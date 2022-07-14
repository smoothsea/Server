<?php
namespace Server\Connection;

use Server\Net;

class TcpConnection
{
	const READ_BUFFER_SIZE = 65535;
	private $socket = null;
	private $remoteSocket = "";

	public $protocol = "";
	public $onMessage = null;
	public $onClose = null;
	public $recvBuff = "";
	public $buffSize = 0;
	public $id = 0;
    public $net = null;

    public $websocketCurrentFrameLength;
    public $websocketDataBuff;
    public $websocketType;

	static private $idSum = 1;

	public function __construct($socket, $remoteSocket)
	{
	    self::$idSum++;
	    $this->id = self::$idSum;

		$this->socket = $socket;
        stream_set_blocking($this->socket, 0);
		$this->remoteSocket = $remoteSocket;

        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->socket, 0);
        }

		Net::$event->addReadStream($this->socket, [$this, "baseRead"]);
	}

	public function baseRead($connection)
	{
		$buffer = fread($connection, self::READ_BUFFER_SIZE);
		if ($buffer === "" || $buffer === false) {
            if (feof($connection) || $buffer === false && !is_resource($connection)) {
                $this->destory();
                return false;
            }
		} else {
			$this->recvBuff .= $buffer;
		}

		$protocol = $this->protocol;
		if ($this->protocol) {
		    while ($this->recvBuff !== "") {
                if ($this->buffSize && $this->buffSize > strlen($this->recvBuff)) {
                    break;
                } else {
                    $this->buffSize = $this->buffSize ? $this->buffSize : $protocol::input($this->recvBuff, $this);
                    if ($this->buffSize == 0) {
                       break;
                    }
                    if ($this->buffSize > strlen($this->recvBuff)) return;
                }

                if (strlen($this->recvBuff) == $this->buffSize) {
                    $oneBuff = $this->recvBuff;
                    $this->recvBuff = "";
                } else {
                    $oneBuff = substr($this->recvBuff, 0, $this->buffSize);
                    $this->recvBuff = substr($this->recvBuff, $this->buffSize);
                }
                $this->buffSize = 0;

                if (!$this->onMessage) return;

                call_user_func($this->onMessage, $this, $protocol::decode($oneBuff, $this));
            }
		}
	}

    public function consumeRecvBuffer($length)
    {
        $this->recvBuff = substr($this->recvBuff, $length);
    }

	public function send($buff, $raw=false)
	{
		$protocol = $this->protocol;
		$sendContent = $raw ? $buff : $protocol::encode($buff, $this);

		if (!$sendContent) return null;

        try {
            $ret = fwrite($this->socket, $sendContent);
            if ($ret) return true;
        } catch (\Exception $e) {}
	}

	public function getRemoteIp()
	{
		return explode(":", $this->remoteSocket)[0];
	}

	public function getRemoteSocket()
    {
        return $this->remoteSocket;
    }

	public function close()
    {
        return $this->destory();
    }

	public function destory()
	{
		Net::$event->removeReadStream($this->socket);
		try {
		    @fclose($this->socket);
        } catch (\Exception $e) {} catch (\Error $e) {}

        if ($this->onClose) {
            call_user_func($this->onClose, $this);
        }

        unset($this->net->connections[$this->id]);
	}
}
