<?php
namespace Server\Lib;

use Server\Lib\Timer;

class Timers
{
    public static $task = [];
    public static $time = 1;
    public static $event = null;

    public static function run($event=null)
    {
        if ($event) {
            self::$event = $event;
        } else {
            static::installHandle();
        }

    }

    public static function installHandle()
    {
        pcntl_signal(SIGALRM, array("\\Server\\Lib\\Timers", "signalHandle"));
    }

    public static function signalHandle()
    {
        static::tick();
        pcntl_alarm(self::$time);
    }

    public static function tick()
    {
        $time = time();
        foreach (self::$task as $k=>$v) {
            if ($k == $time) {
                call_user_func($v["func"], $v["argv"]);

                if ($v["persist"]) {
                    static::add($v["interval"], $v["func"], $v["argv"], $v["persist"]);
                }

                unset(self::$task[$k]);
            } else if ($k < $time) {
                unset(self::$task[$k]);
            }
        }
    }

    public static function add($interval, $func, $argv=[], $persist=true)
    {
        if (!$interval) {
            return false;
        }

        $timeObj = new Timer($interval, $func, $argv, $persist);

        if (self::$event) {
            self::$event->addTimer($timeObj);
        } else {
            $time = time();
            self::$task[$time + $interval] = [
                "func"=>$func,
                "argv"=>$argv,
                "persist"=>$persist,
                "interval"=>$interval
            ];

            pcntl_alarm(self::$time);
        }

        return $timeObj;
    }

    public function del($timer)
    {
        if (self::$event) {
            self::$event->removeTimer($timer);
        }
    }

    public static function delAll()
    {
        self::$task = [];
    }
}