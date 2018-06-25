<?php
namespace Smoothsea\Net\Event;

use Smoothsea\Net\Event\Libevent;
use Smoothsea\Net\Event\StreamSelect;

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