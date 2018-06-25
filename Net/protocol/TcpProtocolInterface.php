<?php
namespace Smoothsea\Net\Protocol;

use Smoothsea\Net\Connection\TcpConnection;

interface TcpProtocolInterface
{
	public static function input($buff, TcpConnection $connection);

	public static function encode($buff, TcpConnection $connection);

	public static function decode($data, TcpConnection $connection);
}