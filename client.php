<?php
/**
 * Web代理客户端(支持http/https)
 */
class client
{
    private $_client = [];
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
        $this->_server = new Swoole\Server("0.0.0.0", 8998);

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
            $data = $this->generateEncryptData($buffer);
            if (!isset($this->_client[$fd])) {
                $this->_client[$fd] = new Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
                if ($this->_client[$fd]->connect($this->_proxyHost, '8999')) {
                    $this->log("连接成功!");
                    //直接转发数据
                    $this->_client[$fd]->send($data);
                } else {
                    $this->log("Client {$fd} error");
                }
            } else {
                //已连接，正常转发数据
                if ($this->_client[$fd]->isConnected()) {
                    $this->_client[$fd]->send($data);
                }
            }
            while (true && isset($this->_client[$fd])) {
                $data = $this->_client[$fd]->recv();
                if (strlen($data) > 0) {
                    //将收到的数据转发到客户端
                    if ($this->_server->exist($fd)) {
                        $this->_server->send($fd, $data);
                    }
                } else {
                    if ($data === '') {
                        // 全等于空 直接关闭连接
                        $this->log("返回空-连接关闭!");
                        $this->_client[$fd]->close();
                        break;
                    } else {
                        if ($data === false) {
                            // 可以自行根据业务逻辑和错误码进行处理，例如：
                            // 如果超时时则不关闭连接，其他情况直接关闭连接
                            if ($this->_client[$fd]->errCode !== SOCKET_ETIMEDOUT) {
                                $this->log("连接超时-连接关闭!");
                                $this->_client[$fd]->close();
                                break;
                            }
                        } else {
                            $this->log("其他异常-连接关闭!");
                            $this->_client[$fd]->close();
                            break;
                        }
                    }
                }
                \Swoole\Coroutine::sleep(1);
            }
        });

        $this->_server->on('Close', function ($server, $fd) {
            $this->log("Server connection close: {$fd}");
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
