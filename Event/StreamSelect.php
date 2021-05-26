<?php

namespace Server\Event;

class StreamSelect
{
    public $readStreams = [];
    public $listeners = [];
    public $events = [];
    private $scheduler = null;  // Timer scheduler
    private $eventTimer = [];   // Timer event listeners

    public function __construct()
    {
        $this->scheduler = new \SplPriorityQueue();
        $this->scheduler->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
    }

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


    public function addTimer($timer)
    {
        $timeId = $timer->getId();
        $time =  microtime(true);
        $this->scheduler->insert($timeId, -($time + $timer->getInterval()));
        $this->eventTimer[$timeId] = $timer;
    }

    public function removeTimer($timer)
    {
        $id = $timer->getId();
        unset($this->eventTimer[$id]);
    }

    public function run()
    {
        while (1) {
            $this->waitForStreamActivity($timeout = 100000000);

            if (!$this->scheduler->isEmpty()) {
                $this->tick();
            }

            usleep(1000);
        }
    }

    private function tick()
    {
        while (!$this->scheduler->isEmpty()) {
            $schedulerData       = $this->scheduler->top();
            $timerId             = $schedulerData["data"];
            $nextRunTime         = -$schedulerData["priority"];
            $now                 = \microtime(true);
            if ($nextRunTime <=  $now) {
                $this->scheduler->extract();

                if (!isset($this->eventTimer[$timerId])) {
                    continue;
                }

                $taskTimer = $this->eventTimer[$timerId];
                if ($taskTimer->isPersist()) {
                    $nextRunTime = $now + $taskTimer->getInterval();
                    $this->scheduler->insert($timerId, -$nextRunTime);
                }
                \call_user_func_array($taskTimer->getCallback(), $taskTimer->getArgv());
                if (!$taskTimer->isPersist()) {
                    $this->removeTimer($taskTimer);
                }
                continue;
            }
            return;
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

            return stream_select($read, $write, $except, 0, $timeout === null ? null : 0);
        }

        return 0;
    }
}