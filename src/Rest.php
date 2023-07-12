<?php declare(strict_types=1);
namespace Kingsoft;

use Kingsoft\Http\Request;
use Kingsoft\Http\RequestMethod as RM;
use Kingsoft\Http\Response;
use Kingsoft\Http\StatusCode;
use Kingsoft\Db\DatabaseException;
use Kingsoft\Http\ContentType;

abstract class Rest
{
  protected abstract function get(): void;
  protected abstract function post(): void;
  protected abstract function put(): void;
  protected abstract function delete(): void;
  protected abstract function head(): void;

  protected abstract function getNamespace(): string;

  protected string $resource_handler;
  public function __construct( readonly Request $request )
  {
    try {
      $request
        ->addMethodHandler( RM::Head, [ $this, 'head' ] )
        ->addMethodHandler( RM::Get, [ $this, 'get' ] )
        ->addMethodHandler( RM::Post, [ $this, 'post' ] )
        ->addMethodHandler( RM::Put, [ $this, 'put' ] )
        ->addMethodHandler( RM::Delete, [ $this, 'delete' ] );

      $this->resource_handler = '\\' . $this->getNamespace() . '\\' . $this->request->resource;
      $request->handleRequest();

    } catch ( \InvalidArgumentException $e ) {
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendContentType( ContentType::Json );
      echo $this->createExceptionBody( $e );
      exit();

    } catch ( DatabaseException $e ) {
      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendContentType( ContentType::Json );
      echo $this->createExceptionBody( $e );
      exit();

    } catch ( \Exception $e ) {
      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendContentType( ContentType::Json );
      echo $this->createExceptionBody( $e );
      exit();
    }
  }
  /**
   * Get a resource by id
   * @param  Request $request
   * @return \Kingsoft\Persist\Base
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * side effect: sends a response and exits if the resource is not found
   */
  protected function getResource(): \Kingsoft\Persist\Base
  {
    if( !isset( $this->request->id ) ) {
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendMessage( 'error', 0, 'No id provided' );
      exit;
    }
    if( $obj = new( $this->resource_handler )( $this->request->id ) and $obj->isRecord() ) {
      return $obj;

    } else {
      Response::sendStatusCode( StatusCode::NotFound );
      Response::sendMessage( 'error', 0, 'Not found' );
      exit;
    }
  }
  /**
   * Create a JSON string from an exception
   *
   * @param  mixed $e
   * @return string
   */
  protected function createExceptionBody( \Throwable $e ): string
  {
    // don't reveal internal errors
    if( $e instanceof Kingsoft\DB\DatabaseException ) {
      return json_encode( [ 
        'result' => 'error',
        'code' => $e->getCode(),
        'type' => 'DatabaseException',
        'message' => 'Internal error'
      ] );
    }
    return json_encode( [ 
      'result' => 'error',
      'code' => $e->getCode(),
      'type' => get_class( $e ),
      'message' => $e->getMessage(),
    ] );
  }
}