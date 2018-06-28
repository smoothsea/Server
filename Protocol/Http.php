<?php
namespace Server\Protocol;

use Server\Connection\TcpConnection;

class Http implements \Server\Protocol\TcpProtocolInterface
{
	public static function input($buff, TcpConnection $connection)
	{
		list($headerData, ) = explode("\r\n\r\n", $buff);
		if (strpos($headerData, "POST") === 0) {
			if (preg_match("/\r\nContent-Length: ?(\d+)/i", $headerData, $match)) {
				return $match[1]+strlen($headerData)+4;
			} else {
				return 0;
			}
		} else if (strpos($headerData, "GET") === 0) {
			return strlen($headerData)+4;
		} else {
			$connection->send("HTTP/1.1 400 Bad Request\r\n");
			return 0;
		}
	}

	public static function decode($buff, TcpConnection $connection)
	{
		$_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = array();
		if (!$buff) return false;

		HttpCache::$header   = array('Connection' => 'Connection: keep-alive');

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

		$boundary = "";
		foreach ($headerData as $v) {
//			var_dump($v);
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
				case "CONTENT_TYPE":
					if (!preg_match("/boundary=\"?(\S+)\"?/", $v, $match)) {
						$_SERVER["CONTENT_TYPE"] = $v;
					} else {
						$_SERVER["CONTENT_TYPE"] = "multipart/form-data";
						$boundary = "--".$match[1];
					}
					break;
				case "COOKIE":
					$t = str_replace(";", "&", str_replace(" ", "", $messageContent) );
					parse_str($t, $_COOKIE);
			}
		}

		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			if (isset($_SERVER["CONTENT_TYPE"]) && $_SERVER["CONTENT_TYPE"] == "multipart/form-data") {
				self::parseUploadFiles($bodyData, $boundary);
			} else {
				parse_str($bodyData, $_POST);
			}
		}

		$_SERVER["QUERY_STRING"] = parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY);
		if ($_SERVER["QUERY_STRING"]) {
			parse_str($_SERVER["QUERY_STRING"], $_GET);
		} else {
			$_SERVER["QUERY_STRING"] = "";
		}

		$_REQUEST = array_merge($_GET, $_POST);
		$_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();

		self::sessionStart();
	}

	public static function encode($data, TcpConnection $connection)
	{
		$header = isset(HttpCache::$header["httpCapital"]) ? HttpCache::$header["httpCapital"]."\r\n" : "HTTP/1.1 200 Ok\r\n";
		$header .= isset(HttpCache::$header["Content-Type"]) ? HttpCache::$header["Content-Type"]."\r\n" : "Content-Type: text/html; charset=UTF-8\r\n";
		foreach (HttpCache::$header as $key=>$v) {
			if (strtolower($key) == "set-cookie") {
				foreach ($v as $v2) {
					$header .= $v2."\r\n";
				}
			} else if ($key != "httpCapital" && $key != "Content-Type") {
				$header .= $v."\r\n";
			}
		}
		$header .= "Server: Net\r\nContent-Length: " . strlen($data) . "\r\n\r\n";

		self::sessionWriteClose();

		return $header.$data;
	}

	public static function setCookie($name, $value="", $expire=0, $path="", $domain="", $secure=false, $httponly=false)
	{
		return self::header(
				'Set-Cookie: ' . $name . '=' . rawurlencode($value)
				. (empty($domain) ? '' : '; Domain=' . $domain)
				. (empty($maxage) ? '' : '; Max-Age=' . $maxage)
				. (empty($path) ? '' : '; Path=' . $path)
				. (!$secure ? '' : '; Secure')
				. (!$httponly ? '' : '; HttpOnly'), false);
	}

	public static function header($content, $replace=true, $code=0)
	{
		if (strpos($content, "HTTP") === 0 ) {
			$key = "httpCapital";
		} else {
			$key = strstr($content, ":", true);
		}

		if (strtolower($key) === "location" && $code==0) {
			self::header($content, $replace, "302");
		}

		if (isset(HttpCache::$codes[$code])) {
			HttpCache::$header['httpCapital'] = "HTTP/1.1 $code " . HttpCache::$codes[$code];
			if ($key === 'httpCapital') {
				return true;
			}
		}

		if (strtolower($key) == "set-cookie" ) {
			HttpCache::$header[$key][] = $content;
		} else {
			HttpCache::$header[$key] = $content;
		}
	}

	public static function sessionStart()
	{
		self::sessionGc();

		HttpCache::$sessionStart = true;
		if (!isset($_COOKIE[HttpCache::$sessionName]) ||
			!is_file(HttpCache::$sessionPath."/".HttpCache::$sessionFilePrefix. $_COOKIE[HttpCache::$sessionName])) {
			$tempFile = tempnam(HttpCache::$sessionPath, HttpCache::$sessionFilePrefix);

			HttpCache::$sessionFile = $tempFile;
			$sessionId = substr($tempFile, strlen(HttpCache::$sessionPath."/".HttpCache::$sessionFilePrefix));

			self::setcookie(HttpCache::$sessionName,
					$sessionId,
					ini_get('session.cookie_lifetime')
					, ini_get('session.cookie_path')
					, ini_get('session.cookie_domain')
					, ini_get('session.cookie_secure')
					, ini_get('session.cookie_httponly'));
		}

		if (!HttpCache::$sessionFile) {
			HttpCache::$sessionFile = HttpCache::$sessionPath."/".HttpCache::$sessionFilePrefix.$_COOKIE[HttpCache::$sessionName];
		}

		$data = file_get_contents(HttpCache::$sessionFile);
		if ($data) {
			session_decode($data);
		}

		return true;
	}

	public static function sessionWriteClose()
	{
		if ($_SESSION && HttpCache::$sessionStart) {
			$sessionData = session_encode();
			if ($sessionData && HttpCache::$sessionFile) {
				file_put_contents(HttpCache::$sessionFile, $sessionData);
			}
		}
		return (bool)$_SESSION;
	}

	public static function sessionGc()
	{
		if (HttpCache::$sessionGcProbability <= 0 || HttpCache::$sessionGcDivisor <=0 || rand(1, HttpCache::$sessionGcDivisor) > HttpCache::$sessionGcProbability)
			return;

		$time = time();
		foreach (glob(HttpCache::$sessionPath."/".HttpCache::$sessionFilePrefix."*") as $v) {
			if (is_file($v) && filemtime($v) < $time-HttpCache::$sessionGcMaxLifeTime) {
				unlink($v);
			}
		}
	}

	public static function parseUploadFiles($bodyData, $boudary)
	{
		$bodyData = substr($bodyData, 0, strlen($bodyData)-strlen($boudary."--\r\n"));
		$bodyData = explode($boudary."\r\n", $bodyData);
		unset($bodyData[0]);

		foreach ($bodyData as $v) {
			list($boudaryHeader, $boudaryValue) = explode("\r\n\r\n", $v);
			foreach (explode("\r\n", $boudaryHeader) as $k2=>$v2) {
				list($name, $value) = explode(":", $v2);
				switch ($name) {
					case "Content-Disposition":
						if (preg_match("/name=\".*?\"; filename=\"(.*?)\"$/", $value, $match)) {
							$_FILES[] = [
								"file_name"=>$match[1],
								"file_data"=>$boudaryValue,
								"file_size"=>strlen($boudaryValue)
							];
						} else if (preg_match("/name=\"(.*?)\"/", $value, $match)) {
							$_POST[$match[1]] = $boudaryValue;
						}
						break;
				}
			}
		}

	}
}

class HttpCache
{
	public static $codes = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => '(Unused)',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
	);
	public static $header = [];
	public static $sessionPath = "";
	public static $sessionName = "";
	public static $sessionGcProbability = 1;
	public static $sessionGcDivisor = 5;
	public static $sessionGcMaxLifeTime = 1440;
	public static $sessionStart = false;
	public static $sessionFile = "";
	public static $sessionFilePrefix = "ses";

	public static function init()
	{
		self::$sessionPath = session_save_path();
		if (!self::$sessionPath) {
			self::$sessionPath = sys_get_temp_dir();
		}

		self::$sessionName = ini_get("session.name");

		if ($gcProbobility = ini_get("session.gc_probability")) {
			self::$sessionGcProbability = $gcProbobility;
		}

		if ($gcDivisor = ini_get("session.gc_divisor")) {
			self::$sessionGcDivisor = $gcDivisor;
		}

		if ($gcMaxLifeTime = ini_get("session.gc_maxlifetime")) {
			self::$sessionGcMaxLifeTime = $gcMaxLifeTime;
		}

		@\session_start();
	}
}

HttpCache::init();