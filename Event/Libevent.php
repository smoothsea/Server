<?php
namespace Server\Event;

use Server\Lib\Timer;
use Server\Lib\Timers;

class Libevent
{
	public $eventBase = null;
	public $listeners = [];
	public $events = [];
	public $timerEvents = null;
	public $timerCallback = null;

	public function __construct ()
	{
		$this->eventBase = event_base_new();

		$this->timerEvents = new \SplObjectStorage();
		$this->createTimerCallback();
	}

	public function addReadStream($socket, $callback)
	{
		$id = (int)$socket;

		if (!isset($this->listeners[$id])) {
			$this->listeners[$id] = $callback;
			return $this->setEvent($socket, EV_READ);
		}
	}

	public function removeReadStream($socket)
	{
		$id = (int)$socket;

		if (isset($this->listeners[$id])) {
			unset($this->listeners[$id]);
			return $this->unsetEvent($socket, EV_READ);
		}
	}

	public function addWriteStream()
	{

	}

	public function addTimer($timer)
    {
        $event = event_timer_new();
        $this->timerEvents[$timer] = $event;

        event_timer_set($event, $this->timerCallback, $timer);
        event_base_set($event, $this->eventBase);
        event_add($event, $timer->getInterval() * 1000000);
    }

    public function removeTimer($timer)
    {
        $event = $this->timerEvents[$timer];

        event_del($event);
        event_free($event);

       unset($this->timerEvents[$timer]);
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

	private function createTimerCallback()
    {
        $this->timerCallback = function ($_, $__, $timer) {
            call_user_func($timer->getCallback(), $timer);

            if ($timer->isPersist()) {
                event_add($this->timerEvents[$timer], $timer->getInterval()*1000000);
            } else {
                $this->removeTimer($timer);
            }
        };
    }
}