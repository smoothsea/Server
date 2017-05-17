<?php
namespace net\event;

use \net\event\Libevent;

class EventFactory
{
	static public $instance = null;

	static public function getInstance()
	{
		if (!self::$instance) {
			if (extension_loaded("libevent")) {
				self::$instance = new Libevent();
			} else {
				return false;
			}
		}
		return self::$instance;
	}
}