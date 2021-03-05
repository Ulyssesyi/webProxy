<?php
require_once __DIR__.'/vendor/autoload.php';
use App\ProxyClient;
use App\ProxyServer;

$params = getopt('t:u::p::s::', ['type:', 'user::', 'pass::', 'server::']);
$type = isset($params['t']) ? $params['t'] : ( isset($params['type']) ? $params['type'] : '');
$user = isset($params['u']) ? $params['u'] : ( isset($params['user']) ? $params['user'] : '');
$pass = isset($params['p']) ? $params['p'] : ( isset($params['pass']) ? $params['pass'] : '');
if ($type === 'client') {
    $serverIp = isset($params['s']) ? $params['s'] : ( isset($params['server']) ? $params['server'] : '');
    $client = new ProxyClient($serverIp, $user, $pass);
    $client->run();
} else {
    $server = new ProxyServer($user, $pass); //如果传入user和pass，就是需要账号密码验证
    $server->run();
}
