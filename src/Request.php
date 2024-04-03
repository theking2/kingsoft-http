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
 * Request class - Facade for the request
 */
readonly class Request implements \Psr\Log\LoggerAwareInterface
{
  /** @var int $maxAge max age for the OPTIONS request */
  protected int $maxAge;
  /**
   * setMaxAge set the max age for the preflight cachcing
   *
   * @param  mixed $maxAge max age in seconds
   * @return self
   */
  public function setMaxAge( int $maxAge ): self
  {
    $this->maxAge = $maxAge;
    return $this;
  }
  /** @var RequestMethod $method that is requested */
  public RequestMethod $method;
  /** @var string $resource name of the requested resource */
  public string $resource;
  /** @var int $offset offset in the resrouce list */
  public int $offset;
  /** @var int $limit max number of resrouces */
  public int $limit;
  /** @var int|string|null $id id of the requested resource */
  public int|string|null $id;
  /** @var array|null $query query parameters as key value array */
  public array|null $query;
  /** @var array|null $payload payload of the request */
  public array|null $payload;

  /**
   * __construct create a new Request object
   * Parse the request and set the properties
   * @param  array $allowedEndpoints list of allowed endpoints
   * @param  ?string $allowedOrigin comma separated list of allowed origins
   * @param  ?string $allowedMethods comma separated list of allowed methods
   * @param  ?int $skipPathParts number of lefthand path parts to skip
   * @throws \InvalidArgumentException
   *
   * @return void
   */
  public function __construct(
    protected array $allowedEndpoints,
    protected ?string $allowedMethods = 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
    protected ?string $allowedOrigin = '*',
    protected ?int $skipPathParts = 0
  ) {
  }
  /**
   * parseMethod Parse the request method
   *
   * @return bool
   */
  private function parseMethod(): bool
  {
    $this->method          = RequestMethod::from( $_SERVER["REQUEST_METHOD"] );
    $requestInfo['method'] = $this->method;
    $this->logger->debug( "Request received", $requestInfo );

    if( !$this->isMethodAllowed() ) {
      $this->logger->notice( "Method not allowed" . $this->method, [ 'allowed' => $this->allowedMethods ] );
      Response::sendStatusCode( StatusCode::MethodNotAllowed );
      return false;
    }
    return true;
  }

  /**
   * parseRequest Parse the request and set the properties
   * side effect: set the properties
   * @return bool
   */
  private function parseRequest(): bool
  {
    /**
     * get the endpoint from the request
     * e.g. /api/index.php/<endpoint>[/<id>] or /api/index.php/<endpoint>?<query>
     * $uri[0] is always empty, $uri[1] is the endpoint
     */
    if( null === $path = parse_url( str_replace( '\\\\', '\\', $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) ) {
      $this->logger->alert( "URL parse error", [ 'url' => $_SERVER['REQUEST_URI'] ] );
      Response::sendStatusCode( StatusCode::BadRequest );
      Response::sendMessage(
        StatusCode::toString( StatusCode::BadRequest ),
        StatusCode::BadRequest->value,
        "Could not parse '" . $_SERVER['REQUEST_URI'] . "'"
      );
      return false;
    }
    $uri = explode( '/', $path );
    // remove the first empty element and additional path parts
    for( $i = 0; $i <= $this->skipPathParts; $i++ ) {
      array_shift( $uri );
    }
    $this->logger->debug( "URI parsed", [ 'uri' => $uri ] );
    $this->parseResource( implode( '/', $uri ) );

    $requestInfo['resource'] = $this->resource;
    $requestInfo['offset']   = $this->offset;
    $requestInfo['limit']    = $this->limit;

    if( !$this->isResourceValid() ) {
      $this->logger->info( "Invalid resource", $requestInfo );

      Response::sendStatusCode( StatusCode::NotFound );
      Response::sendMessage( "unknown resource", 0, "Resource $this->resource not found" );
      return false;
    }
    $this->logger->debug( "Resource parsed", $requestInfo );

    if( isset( $uri[1] ) && $uri[1] === '' ) {
      $this->logger->debug( "Empty ID", $requestInfo );
      unset( $uri[1] );
    }
    $this->id = $requestInfo['id'] = $uri[1] ?? null;
    if( $this->id )
      $this->logger->debug( "ResourceID parsed", $requestInfo );

    $queryString = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
    $this->query = $this->parseParameters( $queryString );

    if( $this->query ) {
      $requestInfo['query'] = $this->query;
      $this->logger->debug( "Query parsed", $requestInfo );
    }
    return true;
  }
  private function parsePayload(): bool
  {
    $this->payload = json_decode( file_get_contents( 'php://input' ), true );
    if( $this->payload ) {
      $requestInfo['payload'] = json_encode( $this->payload );
      $this->logger->debug( "Payload parsed", $requestInfo );
    }
    return true;
  }
  /**
   * handleRequest Parse the api reqeust and call the method handler for the requested method
   *
   * @return bool
   */
  public function handleRequest(): bool
  {
    //header( 'Connection: Keep-Alive' );
    header( 'Access-Control-Allow-Methods: ' . $this->allowedMethods );
    header( 'Access-Control-Allow-Origin: ' . $this->allowedOrigin );

    if( !$this->parseMethod() ) {
      return false;
    }

    /* if the request method is OPTIONS, we don't need to parse the request further */
    if( $this->method === RequestMethod::Options ) {
      $this->handleOption();
      return true;
    }

    if( !$this->parseRequest() ) {
      return false;
    }

    if( !$this->parsePayload() ) {
      return false;
    }
    return true;
  }


  /**
   * hendleOption we are handling the OPTION request
   * side effect: send the headers and exit
   * @return void
   */
  private function handleOption(): void
  {
    $this->logger->info( "Handle OPTION" );
    header( 'Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Origen, Access-Control-Request-Method, Origin' );

    if( isset($this->maxAge) )
      header( 'Access-Control-Max-Age: ' . $this->maxAge );

    Response::sendStatusCode( StatusCode::NoContent );
    exit;
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
    return in_array( $this->method->value, explode( ',', $this->allowedMethods ) );
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

  /**
   * Parse the resource from request
   *
   * @param  mixed $rawResource
   * @return void
   */
  private function parseResource( string $rawResource )
  {
    $regexp = "/(?'resource'\w*)(\[(?'offset'\d*)(\,(?'limit'\d*)\])?)?(?'query'.*)$/";

    if( !preg_match( $regexp, $rawResource, $matches ) ) {
      $this->logger->debug( "regexp not matched, normal endpoint", [ 'resource' => $rawResource ] );
      $this->resource = $rawResource;
      $this->offset   = 0;
      $this->limit    = 0;

    } else {
      $this->logger->debug( "regexp match", $matches );
      $this->resource = $matches['resource'];
      $this->offset   = (int) $matches['offset'];
      $this->limit    = (int) $matches['limit'] ?? 0;

    }
  }
  /** @var \Psr\Log\LoggerInterface $logger */
  protected \Psr\Log\LoggerInterface $logger;
  /**
   * setLogger 
   *
   * @param  mixed $loggerInterface
   * @return self
   */
  public function setLogger( \Psr\Log\LoggerInterface $logger ): void
  {
    $this->logger = $logger;
  }
}
