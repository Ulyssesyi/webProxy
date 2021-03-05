# webProxy
swoole透明代理，要求：
- PHP版本>7.3
- Swoole版本>4.6
```
composer install
php main.php -uyijin -p123456 //启动代理服务器，如果不传入-u账号 -p密码就是免密代理
php main.php -tclient -s127.0.0.1 -uyijin -p123456 //启动代理客户端，-s传入代理服务器的ip地址，如果不传入-u账号 -p密码就是免密代理。-t参数client代表启动客户端，server或不传代表启动服务器。
```
