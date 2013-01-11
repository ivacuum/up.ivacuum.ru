<?php
/**
* @package up.ivacuum.ru
* @copyright (c) 2011
*/

namespace app;

require('/srv/www/vhosts/src/bootstrap.php');

/**
* Создание сессии
* Инициализация привилегий
*/
$user->session_begin();
$auth->init($user->data);
$user->setup();

/* Маршрутизация запроса */
$router = new \engine\core\router();
$router->handle_request();
