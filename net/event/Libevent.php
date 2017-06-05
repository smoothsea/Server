<?php
namespace net\event;

class Libevent
{
	public $eventBase = null;
	public $listeners = [];
	public $events = [];

	public function __construct ()
	{
		$this->eventBase = event_base_new();
	}

	public function addReadStream($socket, $callback)
	{
		$id = (int)$socket;

		if (!isset($this->listeners[$id])) {
			$this->listeners[$id] = $callback;
			return $this->setEvent($socket, EV_READ);
		}
	}

	public function addWriteStream()
	{

	}

	public function removeReadStream($socket)
	{
		$id = (int)$socket;

		if (isset($this->listeners[$id])) {
			unset($this->listeners[$id]);
			return $this->unsetEvent($socket, EV_READ);
		}
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

		$this->events[$id][$flag] = $event;

		return true;
	}

	public function unsetEvent($socket, $flag)
	{
		$id = (int)$socket;

		if (isset($this->events[$id]) && isset($this->events[$id][$flag]) ) {
			$event = $this->events[$id][$flag];
			unset($this->events[$id][$flag]);
			event_del($event);
		}

		return true;
	}

	public function run()
	{
		event_base_loop($this->eventBase);
	}
}