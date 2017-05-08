<?php
namespace net;

class Net
{
    private $address = "";
    private $port = "";
    private $protocal = "";
    public $count = 1;
    public static $event = null;
    public static $masterPid = null;

    public function __construct($address, $port, $protocal)
    {
        $this->address = $address;
        $this->port = $port;
        $this->protocal = $protocal;
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
                $event = event_new();
                event_set($event, $socket, EV_READ | EV_PERSIST, [$this, "acceptConnection"]);
                event_base_set($event, self::$event);
                event_add($event);
                event_base_loop(self::$event);
            } else {
                self::$masterPid = getmypid();
            }
        }

    }

    private function acceptConnection($connection)
    {
        $connection = @stream_socket_accept($connection, 0, $remoteAddress);

        // Thundering herd.
        if (!$connection) {
            return ;
        }

        $request = fread($connection, 8093);
        echo $request;
        $httpHeader = "HTTP/1.1 200 OK\r\n" .
            "Server:self\r\n" .
            "Content-Type:text/html\r\n\r\n";
        $r = fwrite($connection, $httpHeader."this is server ".getmypid(), 9999);
        fclose($connection);
    }

    private function registerEvent()
    {
        if (extension_loaded("libevent") ) {
            self::$event = event_base_new();
            return true;
        }

        exit("please load libevent extension");
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
