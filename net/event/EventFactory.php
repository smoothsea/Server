<?php
namespace net\event;

use \net\event\Libevent;
use \net\event\StreamSelect;

class EventFactory
{
	static public $instance = null;

	static public function getInstance()
	{
		if (!self::$instance) {
			if (extension_loaded("libevent")) {
				self::$instance = new Libevent();
			} else {
			    self::$instance = new StreamSelect();
			}
		}
		return self::$instance;
	}
}