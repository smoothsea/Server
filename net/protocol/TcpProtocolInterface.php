<?php
namespace net\protocol;

use net\connection\TcpConnection;

interface TcpProtocolInterface
{
	public static function input($buff, TcpConnection $connection);

	public static function encode($buff, TcpConnection $connection);

	public static function decode($data, TcpConnection $connection);
}