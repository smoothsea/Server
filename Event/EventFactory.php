<?php
namespace Server\Event;

use Server\Event\Libevent;
use Server\Event\StreamSelect;

class EventFactory
{
    public static $instance = null;

    public static function getInstance()
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
