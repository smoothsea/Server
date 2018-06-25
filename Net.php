<?php
namespace Smoothsea;

use net\connection\TcpConnection;
use net\event\EventFactory;
use net\lib\Timer;
use net\lib\Timers;

class Net
{
    private $address = "";
    private $port = "";
    public $protocol = "";
    public $count = 1;
    public $onMessage = null;
    public static $event = null;
    public static $masterPid = null;
    private $socket = null;

    public function __construct($address, $port, $protocol)
    {
        $this->address = $address;
        $this->port = $port;
        $this->protocol = $protocol;
    }

    public function run()
    {
        $this->init();
        $this->start();
        $this->display();
        $this->monitor();
    }

    public function acceptConnection($connection)
    {
        $socket = @stream_socket_accept($connection, 0, $remoteIp);

        // Thundering herd.
        if (!$socket) {
            return;
        }

        $connection = new TcpConnection($socket, $remoteIp);
        $connection->onMessage = $this->onMessage;
        $connection->onClose = $this->onClose;
        $connection->protocol = $this->protocol;
    }

    private function init()
    {
        $this->checkSapi();

        //加载协议
        if (!class_exists($this->protocol)) {
            $protocol = '\\Net\\Protocol\\'.ucfirst($this->protocol);
            if (!class_exists($protocol)) exit("{$protocol} not exist");
            $this->protocol = $protocol;
        }

        $this->registerEvent();
    }

    private function start()
    {
        $context = stream_context_create();
        $this->socket = stream_socket_server("tcp://{$this->address}:{$this->port}", $errno, $errmsg,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        stream_set_blocking($this->socket, 0);

        if (!$this->socket) {
            echo "error:{$errmsg}";
            exit;
        }

        Timers::run(self::$event);

        for ($i=0; $i<$this->count; $i++) {
            $this->forkOneWorker();
        }

    }

    private function forkOneWorker()
    {
        $pid = pcntl_fork();

        if ($pid == 0) {
            self::$event->addReadStream($this->socket, [$this, "acceptConnection"]);
            self::$event->run();
        } else {
            self::$masterPid = getmypid();
        }
    }

    private function registerEvent()
    {
        self::$event = EventFactory::getInstance();

        if (!self::$event) {
            exit("Event loop error");
        }
    }

    private function display()
    {
        echo "Server is running.prot {$this->port}.print Ctrl+c to close";
    }

    private function monitor()
    {
        if (self::$masterPid == getmypid()) {
            while (1) {
                pcntl_signal_dispatch();

                $pid = pcntl_wait($status);

                //if a child has already exited
                if ($pid > 0) {
                    self::forkOneWorker();
                }
            }
        }
    }

    static private function checkSapi()
    {
        if (php_sapi_name() !== "cli") exit("Please run in cli environment");
    }
}
