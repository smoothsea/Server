<?php
namespace Server\Connection;

use Server\Net;
use function var_dump;

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
    public $transport = "tcp";
    public $id = 0;
    public $net = null;
    public $sslHandshakeCompleted = false;

    private static $idSum = 1;

    public function __construct($socket, $remoteSocket)
    {
        self::$idSum++;
        $this->id = self::$idSum;

        $this->socket = $socket;
        $this->remoteSocket = $remoteSocket;

        Net::$event->addReadStream($this->socket, [$this, "baseRead"]);
    }

    public function baseRead($connection)
    {
        if ($this->transport === "ssl" && $this->sslHandshakeCompleted === false) {
            if ($this->sslHandshake($connection)) {
                $this->sslHandshakeCompleted = true;
            } else {
                return;
            }
        }

        $buffer = fread($connection, self::READ_BUFFER_SIZE);
        if ($buffer == "" || $buffer === false) {
            $this->destroy();
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
                if ($this->buffSize > strlen($this->recvBuff)) {
                    return;
                }

                if (strlen($this->buffSize) == $this->buffSize) {
                    $oneBuff = $this->recvBuff;
                } else {
                    $oneBuff = substr($this->recvBuff, 0, $this->buffSize);
                }
                $this->recvBuff = "";
                $this->buffSize = 0;

                if (!$this->onMessage) {
                    return;
                }

                call_user_func($this->onMessage, $this, $protocol::decode($oneBuff, $this));
            }
        }
    }

    public function send($buff, $raw = false)
    {
        $protocol = $this->protocol;
        $sendContent = $raw ? $buff : $protocol::encode($buff, $this);

        if (!$sendContent) {
            return null;
        }

        $ret = fwrite($this->socket, $sendContent);
        if ($ret) {
            return true;
        }
    }

    public function getRemoteIp()
    {
        return explode(":", $this->remoteSocket)[0];
    }

    public function getRemoteSocket()
    {
        return $this->remoteSocket;
    }

    public function sslHandshake($socket)
    {
        $type = \STREAM_CRYPTO_METHOD_SSLv2_SERVER | \STREAM_CRYPTO_METHOD_SSLv23_SERVER;
        $ret = \stream_socket_enable_crypto($socket, true, $type);
        // Negotiation has failed.
        if (false === $ret) {
            $this->destroy();
            return false;
        } elseif (0 === $ret) {
            // There isn't enough data and should try again.
            return 0;
        }

        return true;
    }

    public function close()
    {
        return $this->destroy();
    }

    public function destroy()
    {
        Net::$event->removeReadStream($this->socket);
        try {
            var_dump($this->socket);
            @fclose($this->socket);
        } catch (\Exception $e) {
        } catch (\Error $e) {
        }

        if ($this->onClose) {
            call_user_func($this->onClose, $this);
        }

        unset($this->net->connections[$this->id]);
    }
}
