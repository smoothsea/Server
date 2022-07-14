<?php
namespace Server\Protocol;

use Server\Connection\TcpConnection;

class Websocket implements TcpProtocolInterface
{
    const BINARY_TYPE_BLOD = "\x81";
    const BINARY_TYPE_ARRAYBUFFER = "\x82";

    public static function input($buff, TcpConnection $connection)
    {
        if (empty($connection->websocketHandshake) ) {
            return self::dealHandshake($buff, $connection);
        }

        $recvLen = strlen($buff);

        if ($recvLen < 6) {
            return 0;
        }

        if ($connection->websocketCurrentFrameLength) {
            if ($recvLen < $connection->websocketCurrentFrameLength) {
                return 0;
            }
        } else {
            $firstByte = ord($buff[0]);
            $secondByte = ord($buff[1]);
            $isFinFrame = $firstByte>>7;
            $opcode = $firstByte & 0xf;
            $payloadLength = $secondByte & 127;

            switch ($opcode) {
                case 0x0:
                    break;
                case 0x1:
                    //text type
                    break;
                case 0x2:
                    //arrayBuffer type
                    break;
                case 0x8:
                    //close type
                    if ($connection->onClose && is_callable($connection->onClose)) {
                        call_user_func($connection->onClose, $connection);
                    }

                    $connection->destory();
                    return 0;
                case 0x9:
                    //ping type
                    break;
                case 0xa:
                    //pong type
                    break;
                default:
                    echo "error opcode";
                    $connection->destory();
                    return 0;
            }

            $headLen = 6;
            if ($payloadLength == 126) {
                $headLen = 8;
                $pack = unpack("nn/ntotalLength", $buff);
                $payloadLength = $pack["totalLength"];
            } else if ($payloadLength == 127) {
                $headLen = 14;
                $pack = unpack("n/N2c", $buff);
                $payloadLength = $pack["c1"]*4294967296 + $pack["c2"];
            }

            $frameLength = $headLen + $payloadLength;

            if ($isFinFrame == 1) {
                if ($opcode === 0x9) {
                } else if ($opcode === 0xa) {
                }
                return $frameLength;
            } else {
                $connection->websocketCurrentFrameLength = $frameLength;
            }
        }

        if ($connection->websocketCurrentFrameLength == $recvLen) {
            static::decode($buff, $connection);
            $connection->consumeRecvBuffer($recvLen);
            $connection->websocketCurrentFrameLength = 0;
            return 0;
        } else if ($connection->websocketCurrentFrameLength < $recvLen) {
            static::decode(substr($buff, 0, $connection->websocketCurrentFrameLength), $connection);
            $currentLen = $connection->websocketCurrentFrameLength;
            $connection->consumeRecvBuffer($recvLen);
            $connection->websocketCurrentFrameLength = 0;
            return static::input(substr($buff, $currentLen), $connection);
        } else {
            return 0;
        }
    }

    public static function decode($buff, TcpConnection $connection)
    {
        $len = ord($buff[1]) & 127;
        if ($len == 126) {
            $masking = substr($buff, 4, 4);
            $data = substr($buff, 8);
        } else if ($len == 127) {
            $masking = substr($buff, 10, 4);
            $data = substr($buff, 14);
        } else {
            $masking = substr($buff, 2, 4);
            $data = substr($buff, 6);
        }

        $dataLength = strlen($data);
        $masking = str_repeat($masking, floor($dataLength / 4)) . substr($masking, 0, $dataLength % 4);
        $decoded = $data ^ $masking;

        if ($connection->websocketCurrentFrameLength) {
            $connection->websocketDataBuff .= $decoded;
            return $connection->websocketDataBuff; 
        } else {
            if ($connection->websocketDataBuff !== "") {
                $decoded = $connection->websocketDataBuff.$decoded;
                $connection->websocketDataBuff = "";
            }
        }

        return $decoded;
    }

    public static function encode($buff, TcpConnection $connection)
    {
        $len = strlen($buff);
        if (empty($connection->websocketType)) {
            $connection->websocketType = static::BINARY_TYPE_BLOD;
        }

        $firstBuff = $connection->websocketType;
        if ($len <= 125) {
            $encodeBuff = $firstBuff.chr($len).$buff;
        } else if ($len <= 65535) {
            $encodeBuff = $firstBuff.chr(126).pack("n", $len).$buff;
        } else {
            $encodeBuff = $firstBuff.chr(127).pack("N", $len).$buff;
        }

        return $encodeBuff;

    }

    public static function dealHandshake($buff, TcpConnection $connection)
    {
        $secWebsocketKey = "";
        if (!preg_match("/Sec-WebSocket-Key: *(.*)?\r\n/i", $buff, $match)) {
            $connection->send("HTTP/1.1 400 Bad Request\r\n\r\n<b>400 Bad Request</b><br>Sec-WebSocket-Key not found.<br>This is a WebSocket service and can not be accessed via HTTP.", true);
            $connection->destory();
            return 0;
        }
        $secWebsocketKey = $match[1];

        $newKey = base64_encode(sha1($secWebsocketKey."258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true) );
        $connection->send("HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Accept: {$newKey}\r\n\r\n", true);
        $connection->websocketHandshake = true;
        $connection->recvBuff = false;
        if (empty($connection->websocketType)) {
            $connection->websocketType = static::BINARY_TYPE_BLOD;
        }
        $connection->websocketDataBuff = "";
        $connection->websocketCurrentFrameLength = 0;

        if (isset($connection->onWebSocketConnect) ) {
            $connection->httpHeaders = self::parseHttpHeader($buff);
            \call_user_func($connection->onWebSocketConnect, $connection, $buff);
        }

        return 0;
    }

    public static function parseHttpHeader($buff)
    {
        // Parse headers.
        list($httpHeader, ) = \explode("\r\n\r\n", $buff, 2);
        $headerData = \explode("\r\n", $httpHeader);

        $headers = [];
        list($headers['REQUEST_METHOD'], $headers['REQUEST_URI'], $headers['SERVER_PROTOCOL']) = \explode(' ',
            $headerData[0]);

        unset($headerData[0]);
        foreach ($headerData as $content) {
            // \r\n\r\n
            if (empty($content)) {
                continue;
            }
            list($key, $value)       = \explode(':', $content, 2);
            $key                     = \str_replace('-', '_', \strtoupper($key));
            $value                   = \trim($value);
            $headers['HTTP_' . $key] = $value;
            switch ($key) {
                // HTTP_HOST
                case 'HOST':
                    $tmp                    = \explode(':', $value);
                    $headers['SERVER_NAME'] = $tmp[0];
                    if (isset($tmp[1])) {
                        $headers['SERVER_PORT'] = $tmp[1];
                    }
                    break;
                // cookie
                case 'COOKIE':
                    \parse_str(\str_replace('; ', '&', $headers['HTTP_COOKIE']), $cookie);
                    break;
            }
        }

        // QUERY_STRING
        $headers['QUERY_STRING'] = \parse_url($headers['REQUEST_URI'], \PHP_URL_QUERY);
        if ($headers['QUERY_STRING']) {
            // $GET
            \parse_str($headers['QUERY_STRING'], $get);
        } else {
            $headers['QUERY_STRING'] = '';
        }
        return $headers;
    }
}

