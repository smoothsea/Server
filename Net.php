<?php
namespace Server;

use Server\Connection\TcpConnection;
use Server\Event\EventFactory;
use Server\Lib\Timers;

class Net
{
    private $address = "";
    private $port = "";
    private $socket = null;

    public $protocol = "";
    public $contextOption = [];
    public $count = 1;
    public $transport = null;
    public $onConnect = null;
    public $onClose = null;
    public $onMessage = null;
    public $onStart = null;
    public $connections = [];
    public static $event = null;
    public static $masterPid = null;

    public function __construct($address, $port, $protocol, $context = [])
    {
        $this->address = $address;
        $this->port = $port;
        $this->protocol = $protocol;
        $this->contextOption = $context;
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
        $socket = @\stream_socket_accept($connection, 0, $remoteIp);

        // Thundering herd.
        if (!$socket) {
            return;
        }

        $connection = new TcpConnection($socket, $remoteIp);
        $connection->onMessage = $this->onMessage;
        $connection->onClose = $this->onClose;
        $connection->protocol = $this->protocol;
        $connection->transport = $this->transport;
        $connection->net = $this;

        if ($this->onConnect) {
            \call_user_func($this->onConnect, $connection);
        }

        $this->connections[$connection->id] = $connection;
    }

    public static function getEvent()
    {
        return self::$event;
    }

    private function init()
    {
        $this->checkSapi();

        //加载协议
        if (!\class_exists($this->protocol)) {
            $protocol = '\\Server\\Protocol\\'.\ucfirst($this->protocol);
            if (!\class_exists($protocol)) {
                exit("{$protocol} not exist");
            }
            $this->protocol = $protocol;
        }

        $this->registerEvent();
    }

    private function start()
    {
        $context = \stream_context_create($this->contextOption);
        $this->socket = \stream_socket_server(
            "tcp://{$this->address}:{$this->port}",
            $errno,
            $errmsg,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if ($this->transport = "ssl") {
            \stream_socket_enable_crypto($this->socket, false);
        }

        \stream_set_blocking($this->socket, 0);

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
        $pid = \pcntl_fork();

        if ($pid == 0) {
            if ($this->onStart) {
                \call_user_func($this->onStart, $this);
            }

            self::$event->addReadStream($this->socket, [$this, "acceptConnection"]);
            self::$event->run();
        } else {
            self::$masterPid = \getmypid();
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
        echo "Server is running.\r\naddress {$this->address}:{$this->port}.\r\nprint Ctrl+c to close\r\n";
    }

    private function monitor()
    {
        if (self::$masterPid == \getmypid()) {
            while (1) {
                \pcntl_signal_dispatch();

                $pid = \pcntl_wait($status);

                //if a child has already exited
                if ($pid > 0) {
                    self::forkOneWorker();
                }
            }
        }
    }

    private static function checkSapi()
    {
        if (\php_sapi_name() !== "cli") {
            exit("Please run in cli environment");
        }
    }
}
