<?php
namespace App;
/**
 * Web代理客户端(支持http/https)
 */
class ProxyClient
{
    private $_client = [];
    private $_cacheBuffer = [];
    private $_server;
    private $_proxyHost;
    private $_authUser;
    private $_authPass;

    public function __construct($proxyHost, $user = '', $pass = '')
    {
        $this->_proxyHost = $proxyHost;
        $this->_authUser = $user;
        $this->_authPass = $pass;
    }

    /**
     * 日志打印
     * @param string $message
     */
    protected function log($message)
    {
        echo $message . PHP_EOL;
    }

    /**
     * 初始化
     */
    protected function init()
    {
        $this->_server = new \Swoole\Server("0.0.0.0", 8998);

        $this->_server->set([
            'buffer_output_size' => 64 * 1024 * 1024, //必须为数字
        ]);
    }

    public function run()
    {
        $this->init();

        $this->_server->on('Connect', function ($server, $fd) {
            $this->log("Server connection open: {$fd}");
        });

        $this->_server->on('Receive', function ($server, $fd, $reactor_id, $buffer) {
            //判断是否为新连接
            $this->log($buffer);
            list($method,) = explode(' ', $buffer, 3);
            if ($method == 'CONNECT') {
                $this->log("隧道模式-连接成功!");
                //告诉客户端准备就绪，可以继续发包
                $this->_server->send($fd, "HTTP/1.1 200 Connection Established\r\n\r\n");
            }
            $data = $this->generateEncryptData($buffer);
            if (!isset($this->_client[$fd])) {
                $this->_client[$fd] = new \Swoole\Coroutine\Http\Client($this->_proxyHost, '8999');
                if ($this->_client[$fd]->upgrade('')) {
                    $this->log("连接成功!{$fd}");
                    //直接转发数据
                    $this->_client[$fd]->push($data);
                } else {
                    $this->log("Client {$fd} error");
                }
            } else {
                //已连接，正常转发数据
                if ($this->_client[$fd]->isConnected()) {
                    $this->log("继续发送数据!{$fd}");
                    $this->_client[$fd]->push($data);
                }
            }
            $data = $this->_client[$fd]->recv();
            $this->log('recv:' . $data);
            //将收到的数据转发到客户端
            if ($this->_server->exist($fd)) {
                $this->_server->send($fd, $data);
            }
        });

        $this->_server->on('Close', function ($server, $fd) {
            $this->log("Server connection close: {$fd}");
            $this->_client[$fd]->close();
            unset($this->_client[$fd]);
        });

        $this->_server->start();
    }

    public function generateEncryptData($data) {
        $str = "GET {$this->_proxyHost} HTTP/1.1".PHP_EOL;
        $str .= "Host: {$this->_proxyHost}".PHP_EOL;
        $str .= "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3100.0 Safari/537.36".PHP_EOL;
        $str .= "Accept: */*".PHP_EOL;
        $str .= "Connection: Keep-Alive".PHP_EOL;
        if ($this->_authUser) {
            $str .= "Authorization: Basic ".md5($this->_authUser . $this->_authPass);
        }
        $str .= PHP_EOL;
        $str .= "data=".base64_encode($data);
        return $str;
    }
}
