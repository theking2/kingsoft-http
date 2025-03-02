<?php declare(strict_types=1);
namespace Kingsoft\Http;

abstract readonly class Rest implements RestInterface
{
  // #MARK: Abstracts

  public abstract function get(): void;
  public abstract function post(): void;
  public abstract function put(): void;
  public abstract function delete(): void;
  public abstract function head(): void;

  protected abstract function createExceptionBody( \Throwable $e ): string;
  protected string $resource_handler;
  protected ?int $controlMaxAge;

  // #MARK: Construction

  public function __construct(
    readonly Request $request,
    readonly \Psr\Log\LoggerInterface $logger = new \Psr\Log\NullLogger
  ) {
  }

  // #MARK: public methods
  public function setMaxAge( int $maxAge ): self
  {
    $this->controlMaxAge = $maxAge;
    return self;
  }

  /**
   * handleRequest handle the request by calling the appropriate method
   */
  public function handleRequest(): void
  {
    try {
      if( $this->request->handleRequest() ) {
        $this->{strtolower( $this->request->method->value )}();
      }

    } catch ( \InvalidArgumentException $e ) {
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendContentType( ContentType::Json );
      exit($this->createExceptionBody( $e ));

    } catch ( \Exception $e ) {
      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendContentType( ContentType::Json );
      exit($this->createExceptionBody( $e ));
    }
  }

  // #MARK: Methods options

  /**
   * Handling the OPTION request for preflight
   * Sending the allowed headers and exit
   */
  public function options(): void
  {
    $this->logger->info( "Handle OPTION" );
    header( 'Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Origen, Access-Control-Request-Method, Origin' );
    if( isset($this->controlMaxAge) )
      header( 'Access-Control-Max-Age: ' . $this->controlMaxAge );
    Response::sendStatusCode( StatusCode::NoContent );
    exit;
  }
}
