<?php declare(strict_types=1);
define( 'ROOT', $_SERVER['DOCUMENT_ROOT'] . '/' );
require ROOT . 'config.inc.php';

require 'RestImplementation.class.php';

use Kingsoft\Http\Request;


$api = new RestImplementation( new Request(
  SETTINGS['api']['allowedendpoints'],
  SETTINGS['api']['allowedmethods'] ?? 'OPTIONS,HEAD,GET,POST,PUT,DELETE',
  SETTINGS['api']['allowedorigin'] ?? '*',
  (int) SETTINGS['api']['maxage']
) );
