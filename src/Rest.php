<?php declare(strict_types=1);
namespace Kingsoft\Http;

use Kingsoft\Http\{
  Request, 
  RequestMethod as RM,
  Response, 
  StatusCode, 
  ContentType
};	

abstract class Rest
{
  public abstract function get(): void;
  public abstract function post(): void;
  public abstract function put(): void;
  public abstract function delete(): void;
  public abstract function head(): void;
  
  protected abstract function createExceptionBody( \Throwable $e ): string;

  protected string $resource_handler;
  public function __construct( readonly Request $request )
  {
    try {
      $this->request
        ->addMethodHandler( RM::Head, [ $this, 'head' ] )
        ->addMethodHandler( RM::Get, [ $this, 'get' ] )
        ->addMethodHandler( RM::Post, [ $this, 'post' ] )
        ->addMethodHandler( RM::Put, [ $this, 'put' ] )
        ->addMethodHandler( RM::Delete, [ $this, 'delete' ] );

      $this->request->handleRequest();
    } catch ( \InvalidArgumentException $e ) {
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendContentType( ContentType::Json );
      exit( $this->createExceptionBody( $e ) );

    } catch ( \Exception $e ) {
      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendContentType( ContentType::Json );
      exit( $this->createExceptionBody( $e ));
    }
  }
}
