<?php
class Trh
{
    private $address = "";
    private $port = "";
    private $protocal = "";
    public $count = 1;

    public function __construct($address, $port, $protocal)
    {
        $this->address = $address;
        $this->port = $port;
        $this->protocal = $protocal;
    }

    public function run()
    {
        $this->start();
    }

    private function start()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket, $this->address, $this->port);


        for ($i=0; $i<$this->count; $i++) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                while (1) {
                    socket_listen($socket);
                    $connection = socket_accept($socket);

                    $request = socket_read($connection, 8192);
                    $httpHeader = "HTTP/1.1 200 OK\r\n" .
                        "Server:self\r\n" .
                        "Content-Type:text/html\r\n\r\n";
                    socket_write($connection, $httpHeader . "hello world");
                    socket_close($connection);
                }
            }

        }

        pcntl_wait($status);
    }
}

$trh = new Trh("192.168.2.233", "8001", "http");
$trh->count = 4;
$trh->run();


