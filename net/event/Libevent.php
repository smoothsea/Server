<?php
namespace net\event;

class Libevent
{
	private $eventBase = null;
	private $listeners = [];
	private $events = [];

	public function __construct ()
	{
		$this->eventBase = event_base_new();
	}

	public function addReadStream($socket, callable $callback)
	{
		$id = (int)$socket;

		$this->listeners[$id] = $callback;
		return $this->setEvent($socket, EV_READ);
	}

	public function addWriteStream()
	{

	}

	public function setEvent($socket, $flag)
	{
		$id = (int)$socket;

		$event = event_new();
		if (!event_set($event, $socket, EV_PERSIST | $flag, $this->listeners[$id]) ) {
			return false;
		}

		if (!event_base_set($event, $this->eventBase) ) {
			return false;
		}

		if (!event_add($event) ) {
			return false;
		}

		$this->events[$id] = $event;

		return true;
	}

	public function run()
	{
		event_base_loop($this->eventBase);
	}
}