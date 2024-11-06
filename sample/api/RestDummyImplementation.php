<?php declare(strict_types=1);

namespace Kingsoft\Http;


class RestDummyImplementation implements RestInterface
{
    public function __construct(
        readonly Request $request,
        readonly \Psr\Log\LoggerInterface $logger = new \Psr\Log\NullLogger
    ) {
    }

    public function delete(): void
    {
        $this->logger->info('delete');
        Response::sendStatusCode(StatusCode::NoContent);
    }

    public function get(): void
    {
        $this->logger->info('Handle GET');
        Response::sendStatusCode(StatusCode::OK);
    }

    public function head(): void
    {
        $this->logger->info('Handle HEAD');
        Response::sendStatusCode(StatusCode::OK);
    }

    public function post(): void
    {
        $this->logger->info('Handle POST');
        Response::sendStatusCode(StatusCode::Created);
    }

    public function put(): void
    {
        $this->logger->info('Handle PUT');
        Response::sendStatusCode(StatusCode::NoContent);
    }
    public function options(): void
    {
        $this->logger->info( "Handle OPTION" );
        header( 'Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Origen, Access-Control-Request-Method, Origin' );
    
        if( isset( $this->maxAge ) )
          header( 'Access-Control-Max-Age: ' . $this->maxAge );
    
        Response::sendStatusCode( StatusCode::NoContent );
        exit;
  
    }
    public function handleRequest(): void {
        try {
            if( $this->request->handleRequest() ) {
                $this->{strtolower( $this->request->method->value )}();
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
    protected function createExceptionBody( \Throwable $e ): string
    {
        return json_encode( [ 'error' => $e->getMessage() ] );
    }
}