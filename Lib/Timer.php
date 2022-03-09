<?php
namespace Server\Lib;

class Timer
{
    private $id = null;
    private $interval = "";
    private $callback = null;
    private $argv = [];
    private $persist = true;

    private static $sid = 0;

    public function __construct($interval, $callback, $argv = [], $persist = true)
    {
        $this->interval = $interval;
        $this->callback = $callback;
        $this->argv = $argv;
        $this->persist = $persist;
        $this->id = self::$sid++;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getInterval()
    {
        return $this->interval;
    }

    public function getCallback()
    {
        return $this->callback;
    }

    public function getArgv()
    {
        return $this->argv;
    }

    public function isPersist()
    {
        return $this->persist;
    }
}
