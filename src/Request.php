<?php declare(strict_types=1);

namespace Kingsoft\Http;

class Request
{
  public readonly string $method;
  public readonly array $uri;
  public readonly string $resource;
  public readonly int|string|null $id;
  public readonly array|null $query;
  public function __construct(
    readonly array $allowedEndpoints,
    readonly string $allowedMethods
  )
  {
    $this->method = $_SERVER["REQUEST_METHOD"];
    if( !$this->isMethodAllowed() ) {
      throw new \InvalidArgumentException( 'Method not allowed' );
    }

    if( $this->method === 'OPTIONS' ) {
      return;
    }

    /**
     * get the endpoint from the request
     * e.g. /api/index.php/<endpoint>[/<id>] or /api/index.php/<endpoint>?<query>
     * $uri[0] is always empty, $uri[1] is the endpoint
     */
    $path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    $uri  = explode( '/', $path );
    /**
     * remove the trailing slash, from uri[2] if present
     */
    if( isset( $uri[2] ) && $uri[2] === '' ) {
      unset( $uri[2] );
    }
    $this->id = $uri[2] ?? null;

    $this->resource = $uri[1];
    if( !$this->isResourceValid() ) {
      throw new \InvalidArgumentException( 'Resource not found' );
    }
    $queryString    = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
    $this->query    = $this->parseParameters( $queryString );
  }

  /**
   * CHeck if a the request contains a valid entity name
   *
   * @return bool
   */
  private function isResourceValid(): bool
  {
    return $this->resource and in_array( $this->resource, $this-> allowedEndpoints );
  }
  private function isMethodAllowed(): bool
  {
    return in_array( $this->method, explode(',', $this-> allowedMethods) );
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
        $param               = explode( '=', $param );
        $result[ $param[0] ] = '*' . str_replace( '*', '%', $param[1] ); // use the like operator
      }
      return $result;
    } else {
      return null;
    }
  }

}