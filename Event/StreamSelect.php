<?php
namespace Server\Event;

class StreamSelect
{
    public $readStreams = [];
	public $listeners = [];
	public $events = [];

	public function addReadStream($socket, $callback)
	{
		$id = (int)$socket;

		if (!isset($this->listeners[$id])) {
			$this->listeners[$id] = $callback;
            $this->readStreams[$id] = $socket;
		}
	}

	public function removeReadStream($socket)
	{
		$id = (int)$socket;

		if (isset($this->listeners[$id])) {
			unset($this->listeners[$id]);
			unset($this->readStreams[$id]);
		}
	}

	public function addWriteStream()
	{

	}

	public function run()
	{
	    while (1) {
            $this->waitForStreamActivity($timeout = null);
        }
	}

	private function waitForStreamActivity($timeout)
    {
        $read = $this->readStreams;
        $write = [];

        $valid = $this->streamSelect($read, $write, $timeout);
        if (!$valid) {
            return false;
        }

        foreach ($read as $stream) {
            $id = (int)$stream;

            call_user_func($this->listeners[$id], $stream, $this);
        }

        foreach ($write as $stream) {
            $id = (int)$stream;

            call_user_func($this->listeners[$id], $stream, $this);
        }
    }

    private function streamSelect(&$read, &$write, $timeout)
    {
        if ($read || $write) {
            $except = null;

            return stream_select($read, $write, $except, $timeout === null ? null : 0, $timeout);
        }

        $timeout && usleep($timeout);

        return 0;
    }
}