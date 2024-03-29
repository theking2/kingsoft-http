<?php declare(strict_types=1);
namespace Kingsoft\Http;

use Kingsoft\Http\Request;
use Kingsoft\Http\RequestMethod as RM;
use Kingsoft\Http\Response;
use Kingsoft\Http\StatusCode;
use Kingsoft\Http\ContentType;

abstract class Rest
{
  public abstract function get(): void;
  public abstract function post(): void;
  public abstract function put(): void;
  public abstract function delete(): void;
  public abstract function head(): void;
  
  protected abstract function createExceptionBody( \Throwable $e ): string;


  public function __construct( Request $request )
  {
    try {
      $request
        ->addMethodHandler( RM::Head, [ $this, 'head' ] )
        ->addMethodHandler( RM::Get, [ $this, 'get' ] )
        ->addMethodHandler( RM::Post, [ $this, 'post' ] )
        ->addMethodHandler( RM::Put, [ $this, 'put' ] )
        ->addMethodHandler( RM::Delete, [ $this, 'delete' ] );

      $request->handleRequest();

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
