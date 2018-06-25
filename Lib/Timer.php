<?php
namespace Smoothsea\Lib;

class Timer
{
    private $interval = "";
    private $callback = null;
    private $argv = [];
    private $persist = true;

    public function __construct($interval, $callback, $argv=[], $persist=true)
    {
        $this->interval = $interval;
        $this->callback = $callback;
        $this->argv = $argv;
        $this->persist = $persist;
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