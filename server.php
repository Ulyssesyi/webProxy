<?php
/**
 * Web代理服务器(支持http/https)
 * @author zhjx922
 */
class server
{

    private $_client = [];
    private $_server;

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
            $this->log("{$interface}:{$ip}");
        }
    }

    /**
     * 初始化
     * @author zhjx922
     */
    protected function init()
    {
        $this->getLocalIp();

        $this->_server = new Swoole\Server("0.0.0.0", 8999);

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

        $this->_server->on('Connect', function ($server, $fd) {
            $this->log("Server connection open: {$fd}");
        });

        $this->_server->on('Receive', function ($server, $fd, $reactor_id, $buffer) {

            //判断是否为新连接
            if (!isset($this->_client[$fd])) {
                //判断代理模式
                list($method, $url) = explode(' ', $buffer, 3);
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
                $this->_client[$fd] = new Swoole\Coroutine\Client($tcpMode);
                if ($this->_client[$fd]->connect($host, $port)) {
                    if ($method == 'CONNECT') {
                        $this->log("隧道模式-连接成功!");
                        //告诉客户端准备就绪，可以继续发包
                        $this->_server->send($fd, "HTTP/1.1 200 Connection Established\r\n\r\n");
                    } else {
                        $this->log("正常模式-连接成功!");
                        //直接转发数据
                        $this->_client[$fd]->send($buffer);
                    }
                } else {
                    $this->log("Client {$fd} error");
                }
            } else {
                //已连接，正常转发数据
                if ($this->_client[$fd]->isConnected()) {
                    $this->_client[$fd]->send($buffer);
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
}
