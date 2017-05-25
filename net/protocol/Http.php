<?php
namespace net\protocol;

use net\connection\TcpConnection;

class Http implements \net\protocol\TcpProtocolInterface
{
	public static function input($buff, TcpConnection $connection)
	{

	}

	public static function decode($buff, TcpConnection $connection)
	{
		$_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = array();
		if (!$buff) return false;

		$_SERVER = array(
				'QUERY_STRING'         => '',
				'REQUEST_METHOD'       => '',
				'REQUEST_URI'          => '',
				'SERVER_PROTOCOL'      => '',
				'SERVER_NAME'          => '',
				'HTTP_HOST'            => '',
				'HTTP_USER_AGENT'      => '',
				'HTTP_ACCEPT'          => '',
				'HTTP_ACCEPT_LANGUAGE' => '',
				'HTTP_ACCEPT_ENCODING' => '',
				'HTTP_COOKIE'          => '',
				'HTTP_CONNECTION'      => '',
				'REMOTE_ADDR'          => '',
				'REMOTE_PORT'          => '0',
		);
		list($headerData, $bodyData) = explode("\r\n\r\n", $buff, 2);
		$headerData = explode("\r\n", $headerData);
		$httpCapital = $headerData[0];
		list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ',
				$httpCapital);
		unset($headerData[0]);

		foreach ($headerData as $v) {
			list($messageName, $messageContent) = explode(":", $v);
			$messageName = strtoupper(str_replace("-", "_", $messageName));
			$messageContent = trim($messageContent);
			$_SERVER["HTTP_".$messageName] = $messageContent;

			switch($messageName) {
				case "HOST":
					$t = explode(":", $messageContent);
					$_SERVER["SERVER_NAME"] = $t[0];
					if (isset($t[1])) {
						$_SERVER["SERVER_PORT"] = $t[1];
					}
				break;
				case "COOKIE":
					$t = str_replace(";", "&", str_replace(" ", "", $messageContent) );
					parse_str($t, $_COOKIE);
			}
		}

		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			parse_str($bodyData, $_POST);
		}

		$_SERVER["QUERY_STRING"] = parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY);
		if ($_SERVER["QUERY_STRING"]) {
			parse_str($_SERVER["QUERY_STRING"], $_GET);
		} else {
			$_SERVER["QUERY_STRING"] = "";
		}

		$_REQUEST = array_merge($_GET, $_POST);
		$_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();

	}

	public static function encode($data, TcpConnection $connection)
	{
		$header = "HTTP/1.1 200 Ok\r\n";
		$header .= "Content-Type: text/html; charset=UTF-8\r\n";
		$header .= "Server: net\r\nContent-Length: " . strlen($data) . "\r\n\r\n";
		return $header.$data;
	}
}

