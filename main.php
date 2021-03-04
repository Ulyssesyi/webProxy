<?php
require_once 'server.php';
$server = new server();//不做账号密码验证
//$server = new server(true, 'yijin', '123') 需要账号密码验证
$server->run();
