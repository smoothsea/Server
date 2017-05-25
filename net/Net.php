<?php
namespace net;

use net\connection\TcpConnection;
use net\event\EventFactory;

class Net
{
    private $address = "";
    private $port = "";
    public $protocol = "";
    public $count = 1;
    public $onMessage = null;
    public static $event = null;
    public static $masterPid = null;

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

    private function init()
    {
        $this->checkSapi();

        //加载协议
        if (!class_exists($this->protocol)) {
            $protocol = '\\net\\protocol\\'.ucfirst($this->protocol);
            if (!class_exists($protocol)) exit("{$protocol} not exist");
            $this->protocol = $protocol;
        }

        $this->registerEvent();
    }

    private function start()
    {
        $context = stream_context_create();
        $socket = stream_socket_server("tcp://{$this->address}:{$this->port}", $errno, $errmsg,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        stream_set_blocking($socket, 0);

        if (!$socket) {
            echo "error:{$errmsg}";
            exit;
        }

        for ($i=0; $i<$this->count; $i++) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                $that = $this;
                self::$event->addReadStream($socket, [$this, "acceptConnection"]);
                self::$event->run();
            } else {
                self::$masterPid = getmypid();
            }
        }

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
        $connection->protocol = $this->protocol;
    }

    private function registerEvent()
    {
        self::$event = EventFactory::getInstance();

        if (!self::$event) {
            exit("please load libevent extension");
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
                pcntl_wait($status);
            }
        }
    }

    static private function checkSapi()
    {
        if (php_sapi_name() !== "cli") exit("Please run in cli environment");
    }
}
