<?php
namespace App;
/**
 * Web代理服务器(支持http/https)
 * @author zhjx922
 */
class ProxyServer
{

    private $_client = [];
    private $_server;
    private $_authUser;
    private $_authPass;

    public function __construct($user = '', $pass = '')
    {
        $this->_authUser = $user;
        $this->_authPass = $pass;
    }

    /**
     * 日志打印
     * @param string $message
     * @author zhjx922
     */
    protected function log($message)
    {
        echo $message . PHP_EOL;
    }

    /**
     * 获取代理ip
     * @author zhjx922
     */
    protected function getLocalIp()
    {
        //获取代理IP
        $ipList = swoole_get_local_ip();
        foreach ($ipList as $interface => $ip) {
            $this->log("服务器IP:{$ip}");
        }
    }

    /**
     * 初始化
     * @author zhjx922
     */
    protected function init()
    {
        $this->getLocalIp();

        $this->_server = new \Swoole\WebSocket\Server("0.0.0.0", 8999);

        $this->_server->set([
            'buffer_output_size' => 64 * 1024 * 1024, //必须为数字
        ]);
    }

    /**
     * 跑起来
     * @author zhjx922
     */
    public function run()
    {
        $this->init();

        $this->_server->on('Open', function ($server, $request) {
            $this->log("Server connection open: {$request->fd}");
        });

        $this->_server->on('Message', function ($server, $frame) {
            //判断是否为新连接
            $encryptData = $frame->data;
            $fd = $frame->fd;
            list($authStr, $buffer) = explode(' ', $encryptData, 2);
            $this->log($buffer);
            if ($this->_authUser && $authStr !== md5($this->_authUser . $this->_authPass)) {
                $this->_server->push($fd, '认证失败');
                return;
            }
            if (!isset($this->_client[$fd])) {
                //判断代理模式
                list(, $url) = explode(' ', $buffer, 3);
                $url = parse_url($url);

                //ipv6为啥外面还有个方括号？
                if (strpos($url['host'], ']')) {
                    $url['host'] = str_replace(['[', ']'], '', $url['host']);
                }

                //解析host+port
                $host = $url['host'];
                $port = isset($url['port']) ? $url['port'] : 80;

                //ipv4/v6处理
                $tcpMode = strpos($url['host'], ':') !== false ? SWOOLE_SOCK_TCP6 : SWOOLE_SOCK_TCP;
                $this->_client[$fd] = new \Swoole\Client($tcpMode);
                if ($this->_client[$fd]->connect($host, $port)) {
                    //直接转发数据
                    $this->_client[$fd]->send($buffer);
                } else {
                    $this->log("Client {$fd} error");
                }
            } else {
                //已连接，正常转发数据
                if ($this->_client[$fd]->isConnected()) {
                    $this->_client[$fd]->send($buffer);
                }
            }
            $data = $this->_client[$fd]->recv();
            $this->log('recv:' . $data);
            if ($this->_server->exist($fd)) {
                $this->_server->push($fd, $data);
            }
        });

        $this->_server->on('Close', function ($server, $fd) {
            $this->log("Server connection close: {$fd}");
            $this->_client[$fd]->close();
            unset($this->_client[$fd]);
        });

        $this->_server->start();
    }
}
