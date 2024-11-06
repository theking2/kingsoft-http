<?php declare(strict_types=1);
use Kingsoft\Http\RestInterface;
// make sure to create  config.ini.php in the root directory
require '../config.inc.php';

require 'RestDummyImplementation.php';

use Kingsoft\Http\{Request, Response, RestDummyImplementation, StatusCode, ContentType};

try {
  $request = new Request(
    SETTINGS[ 'api' ][ 'allowedendpoints' ],
    SETTINGS[ 'api' ][ 'allowedmethods' ] ?? 'OPTIONS,HEAD,GET,POST,PUT,DELETE',
    SETTINGS[ 'api' ][ 'allowedorigin' ] ?? '*',
    0
  );
  $request->setLogger( new \Monolog\Logger( 'api' ) );
  $api = new RestDummyImplementation( $request );

  $api->handleRequest();
} catch ( \Throwable $e ) {
  Response::sendError( $e->getMessage(), StatusCode::InternalServerError, ContentType::Json );
}