<?php
/**
* @package up.ivacuum.ru
* @copyright (c) 2013
*/

namespace app;

require('../../_/fw/1.2.1/bootstrap.php');

/* Маршрутизация запроса */
$app['router']->_init()->handle_request();
