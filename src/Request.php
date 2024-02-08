<?php declare(strict_types=1);

namespace Kingsoft\Http;

/**
 * RequestMethods
 */
enum RequestMethod: string
{
  case Options = "OPTIONS";
  case Get     = "GET";
  case Post    = "POST";
  case Put     = "PUT";
  case Delete  = "DELETE";
  case Patch   = "PATCH";
  case Head    = "HEAD";

}
/**
 * Request
 */
class Request
{
  /** @var string $method that is requested */
  public readonly string $method;
  /** @var array $methodHandlers callables for the methods signature($id, $query)*/
  public array $methodHandlers = [];
  /** @var string $resource name of the requested resource */
  public readonly string $resource;
  /** @var int $offset offset in the resrouce list */
  public readonly int $offset;
  /** @var int $limit max number of resrouces */
  public readonly int $limit;
  /** @var int|string|null $id id of the requested resource */
  public readonly int|string|null $id;
  /** @var array|null $query query parameters as key value array */
  public readonly array|null $query;
  /** @var array|null $payload payload of the request */
  public readonly array|null $payload;

  /**
   * __construct create a new Request object
   * Parse the request and set the properties
   * @param  array $allowedEndpoints list of allowed endpoints
   * @param  ?string $allowedOrigin comma separated list of allowed origins
   * @param  ?string $allowedMethods comma separated list of allowed methods
   * @throws \InvalidArgumentException
   *
   * @return void
   */
  public function __construct(
    readonly array $allowedEndpoints,
    readonly ?string $allowedMethods = 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
    readonly ?string $allowedOrigin = '*',
    readonly ?int $maxAge = 86400,
    protected ?\Psr\Log\LoggerInterface $log = new \Psr\Log\NullLogger()
  ) {


    $this->method          = $_SERVER["REQUEST_METHOD"];
    $requestInfo['method'] = $this->method;
    $this->log->debug( "Request received", $requestInfo );


    if( !$this->isMethodAllowed() ) {
      $this->log->notice( "Method not allowed" . $this->method, [ 'allowed' => $this->allowedMethods ] );
      Response::sendStatusCode( StatusCode::MethodNotAllowed );
      exit();
    }
    header( 'Access-Control-Allow-Origin: ' . $this->allowedOrigin );
    header( 'Connection: Keep-Alive' );

    /* if the request method is OPTIONS, we don't need to parse the request further */
    if( $this->method === RequestMethod::Options->value ) {
      $this->log->info( "Handle OPTION" );
      Response::sendStatusCode( StatusCode::NoContent );
      header( 'Access-Control-Allow-Methods: ' . $this->allowedMethods );
      header( 'Access-Control-Allow-Headers: Access-Control-Allow-Origen, Access-Control-Allow-Headers, Access-Control-Request-Method, Origin' );
      header( 'Access-Control-Max-Age: ' . $this->maxAge );

      exit;
    }
    /**
     * get the endpoint from the request
     * e.g. /api/index.php/<endpoint>[/<id>] or /api/index.php/<endpoint>?<query>
     * $uri[0] is always empty, $uri[1] is the endpoint
     */
    if( null === $path = parse_url( str_replace( '\\\\', '\\', $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) ) {
      $this->log->alert( "URL parse error", [ 'url' => $_SERVER['REQUEST_URI'] ] );
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendMessage(
        StatusCode::toString( StatusCode::BadRequest ),
        StatusCode::BadRequest->value,
        "Could not parse '" . $_SERVER['REQUEST_URI'] . "'" );
    }
    $uri = explode( '/', $path );

    $this->parseResource( urldecode( $uri[1] ) );
    $requestInfo['resource'] = $this->resource;
    $requestInfo['offset']   = $this->offset;
    $requestInfo['limit']    = $this->limit;

    if( !$this->isResourceValid() ) {
      $this->log->info( "Invalid resource", $requestInfo );

      Response::sendStatusCode( StatusCode::NotFound );
      Response::sendMessage( "unknown resource", 0, "Resource $this->resource not found" );
    }
    $this->log->debug( "Resource parsed", $requestInfo );

    /**
     * remove the trailing slash, from uri[2] if present
     */
    if( isset( $uri[2] ) && $uri[2] === '' ) {
      unset( $uri[2] );
    }
    $this->id          = $uri[2] ?? null;
    $requestInfo['id'] = $this->id;
    if( $this->id )
      $this->log->debug( "ResourceID parsed", $requestInfo );


    $queryString = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
    $this->query = $this->parseParameters( $queryString );

    if( $this->query ) {
      $requestInfo['query'] = $this->query;
      $this->log->debug( "Query parsed", $requestInfo );
    }


    $this->payload = json_decode( file_get_contents( 'php://input' ), true );
    if( $this->payload ) {
      $requestInfo['payload'] = json_encode( $this->payload );
      $this->log->debug( "Payload parsed", $requestInfo );
    }

    $this->log->info( "Request parsed", $requestInfo );
  }
  /**
   * addMethodHandler add a callable for a request method
   *
   * @param  mixed $requestMethod
   * @param  mixed $requestHandler
   * @return self
   */
  public function addMethodHandler( RequestMethod $requestMethod, callable|array $requestHandler ): self
  {
    $this->methodHandlers[ $requestMethod->value ] = $requestHandler;
    return $this;
  }

  /**
   * handleRequest call the method handler for the requested method
   *
   * @return void
   */
  public function handleRequest(): void
  {
    $this->log->debug( "Dispatch request", [ 'method' => $this->method ] );
    $this->methodHandlers[ $this->method ]( $this );
  }

  /**
   * isResourceValid check if the requested resource is available
   *
   * @return bool
   */
  protected function isResourceValid(): bool
  {
    return $this->resource and in_array( $this->resource, $this->allowedEndpoints );
  }
  /**
   * isMethodAllowed check if the requested method is allowed
   *
   * @return bool
   */
  private function isMethodAllowed(): bool
  {
    return in_array( $this->method, explode( ',', $this->allowedMethods ) );
  }
  /**
   * Parse the query string and return an array of key=>value pairs
   * return false if no query string is present
   * @param  string $uri
   * @return array|null
   */
  private function parseParameters( ?string $queryString ): array|null
  {
    $result = [];
    if( !is_null( $queryString ) ) {
      // parse the query string
      foreach( explode( '&', $queryString ) as $param ) {
        $keyvalue = explode( '=', $param );
        if( count( $keyvalue ) !== 2 ) {
          Response::sendStatusCode( StatusCode::BadRequest );
          Response::sendMessage(
            StatusCode::toString( StatusCode::BadRequest ),
            StatusCode::BadRequest->value,
            "Could not parse param '$param'" );
        }

        $keyvalue[1] = urldecode( $keyvalue[1] );
        if( false !== strpos( "!><", substr( $keyvalue[1], 0, 1 ) ) ) { // special selects
          $result[ $keyvalue[0] ] = $keyvalue[1];
        } else {
          $result[ $keyvalue[0] ] = '*' . str_replace( '*', '%', $keyvalue[1] ); // use the like operator
        }
      }
      return $result;
    } else {
      return null;
    }
  }

  private function parseResource( string $rawResource )
  {
    $regexp = "/(?'resource'.*)\[(?'offset'\d*)\-(?'limit'\d*)?\](.*)$/";
    if( !preg_match( $regexp, $rawResource, $matches ) ) {
      $this->log->debug( "regexp  not matched, normal endpoint" );
      $this->resource          = $rawResource;
      $this->offset            = 0;
      $this->limit             = 0;
      $requestInfo['resource'] = $this->resource;
    } else {
      $this->log->debug( "regexp match", $matches );
      $this->resource = $matches['resource'];
      $this->offset   = (int) $matches['offset'];
      $this->limit    = (int) $matches['limit'] ?? 0;

    }
  }
  /**
   * setLogger 
   *
   * @param  mixed $loggerInterface
   * @return Request
   */
  public function setLogger( \Psr\Log\LoggerInterface $loggerInterface ): Request
  {
    $this->logger = $loggerInterface;

    return $this;
  }
}
