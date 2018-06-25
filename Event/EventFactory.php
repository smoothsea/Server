<?php
namespace Smoothsea\Event;

use Smoothsea\Event\Libevent;
use Smoothsea\Event\StreamSelect;

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