<?php
$params = getopt('t:u::p::s::', ['type:', 'user::', 'pass::', 'server::']);
$type = isset($params['t']) ? $params['t'] : ( isset($params['type']) ? $params['type'] : '');
$user = isset($params['u']) ? $params['u'] : ( isset($params['user']) ? $params['user'] : '');
$pass = isset($params['p']) ? $params['p'] : ( isset($params['pass']) ? $params['pass'] : '');
if ($type === 'client') {
    $serverIp = isset($params['s']) ? $params['s'] : ( isset($params['server']) ? $params['server'] : '');
    require_once 'client.php';
    $client = new client($serverIp, $user, $pass);
    $client->run();
} else {
    require_once 'server.php';
    $server = new server($user, $pass); //如果传入user和pass，就是需要账号密码验证
    $server->run();
}
