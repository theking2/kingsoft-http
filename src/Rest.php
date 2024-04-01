<?php declare(strict_types=1);
namespace Kingsoft\Http;

abstract class Rest implements RestInterface
{
  public abstract function get(): void;
  public abstract function post(): void;
  public abstract function put(): void;
  public abstract function delete(): void;
  public abstract function head(): void;

  protected abstract function createExceptionBody( \Throwable $e ): string;

  protected string $resource_handler;
  public function __construct(
    readonly Request $request,
    readonly \Psr\Log\LoggerInterface $logger = new \Psr\Log\NullLogger
    ) {
    $this->request->setLogger( $logger );

    $this->request
      ->addMethodHandler( RequestMethod::Head, [ $this, 'head' ] )
      ->addMethodHandler( RequestMethod::Get, [ $this, 'get' ] )
      ->addMethodHandler( RequestMethod::Post, [ $this, 'post' ] )
      ->addMethodHandler( RequestMethod::Put, [ $this, 'put' ] )
      ->addMethodHandler( RequestMethod::Delete, [ $this, 'delete' ] );
  }  
  /**
   * handleRequest handle the request by calling the appropriate method
   *
   * @return void
   */
  public function handleRequest(): void
  {
    try {
      if( $this->request->handleRequest() ) {
        $this->request->callMethodHandler();
      }

    } catch ( \InvalidArgumentException $e ) {
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendContentType( ContentType::Json );
      exit( $this->createExceptionBody( $e ) );

    } catch ( \Exception $e ) {
      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendContentType( ContentType::Json );
      exit( $this->createExceptionBody( $e ) );
    }
  }

}
