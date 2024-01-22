<?php declare(strict_types=1);

use Kingsoft\Http\Response;
use Kingsoft\Http\Request;
use Kingsoft\Http\StatusCode;
use Kingsoft\Http\ContentType;
use Kingsoft\Http\Rest;
use Kingsoft\Db\DatabaseException;
use Kingsoft\Persist\Base as PersistBase;


class RestImplementation extends Rest
{
  /**
   * Handle a GET request, if {id} is provided attempt to retrieve one, otherwise all.
   *
   * @return void
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   */

  protected function getNamespace(): string
  {
    return SETTINGS['api']['namespace'];
  }

  public function __construct( readonly Request $request ) {
    try{
      parent::__construct( $request );
    } catch ( DatabaseException $e ) {
      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendContentType( ContentType::Json );
      exit( $this->createExceptionBody( $e ) );

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
    /**
   * Get a resource by id
   * @return \Kingsoft\Persist\Base
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * side effect: sends a response and exits if the resource is not found
   */
  protected function getResource(): PersistBase
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
  /* #region GET */
  public function get(): void
  {
    try {
    $result = [];
    if( $this->request->id ) {
      /* get one element by key */
      $this->doGetOne();
    }
    if( isset( $this->request->query ) and is_array( $this->request->query ) ) {
      /**
       * no key provided, return all or selection
       * paging would be nice here
       */
      $this->doGetMany();

    }
    /**
     * no key provided, return all
     * paging would be nice here
     */
    $this->doGetAll();
    } catch( \Exception $e ) {
      LOG->error( 'Exception in GET', ['error'=> $e->getMessage()]);
    }
  }
  /**
   * Get a single record by id
   *
   * @return void
   */
  function doGetOne(): void
  {
    if( $obj = $this->getResource() ) {
      Response::sendStatusCode( StatusCode::OK );
      $payload = $obj->getArrayCopy();
      Response::sendPayload( $payload, [ $obj, "getStateHash" ] );
    }

  }
  /**
   * Get multiple records by criteria
   *
   * @return void
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   */
  function doGetMany(): void
  {
    $records   = [];
    $keys      = [];
    $row_count = SETTINGS['api']['maxresults']??10;

    $where = [];
    foreach( $this->request->query as $key => $value ) {
      $where[ $key ] = urldecode( $value );
    }

    foreach( ( $this->resource_handler )::findAll( $where ) as $o ) {
      if( !$row_count-- )
        break; // limit the number of rows returned (paging would be nice here) 
      $records[] = $o->getArrayCopy();
      $keys[]    = $o->getKeyValue();
    }

    if( count( $keys ) ) {
      Response::sendStatusCode( StatusCode::OK );
      // Here we should allow for paging
      header( 'Content-Range: ID ' . $keys[0] . '-' . $keys[ count( $keys ) - 1 ] );
      Response::sendPayload( $records );
    }

    Response::sendStatusCode( StatusCode::NoContent );
    exit();
  }

  /**
   * Get all records up to MAXROWS
   *
   * @return void
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   */
  function doGetAll(): void
  {
    $records   = [];
    $keys      = [];
    $row_count = SETTINGS['api']['maxresults']??10;
    $partial   = false;

    foreach( ( $this->resource_handler )::findAll() as $id => $obj ) {
      if( !$row_count-- ) {
        $partial = true;
        break; // limit the number of rows returned (paging would be nice here) 
      }
      $records[] = $obj->getArrayCopy();
      $keys[]    = $obj->getKeyValue();
    }
    if( count( $keys ) ) {
      Response::sendStatusCode( $partial ? StatusCode::PartialContent : StatusCode::OK );
      // Here we should allow for paging
      header( 'Content-Range: ID ' . $keys[0] . '-' . $keys[ count( $keys ) - 1 ] );
      Response::sendPayload( $records );
    }
    Response::sendStatusCode( StatusCode::NoContent );
    exit();
  }
  /* #endregion */

  /* #region POST */

  /**
   * post
   *
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * @return void
   */
  public function post(): void
  {
    $input = json_decode( file_get_contents( 'php://input' ), true );

    /** @var \Kingsoft\Persist\Base $obj */
    $obj = ( $this->resource_handler )::createFromArray( $input );
    if( $obj->freeze() ) {
      Response::sendStatusCode( StatusCode::OK );
      $payload = [ 
        'result' => 'created',
        'message' => '',
        'id' => $obj->getKeyValue(),
        'ETag' => $obj->getStateHash() ];
      Response::sendPayload( $payload, [ $obj, "getStateHash" ] );
    }
    Response::sendStatusCode( StatusCode::InternalServerError );
    Response::sendMessage( 'error', 0, 'Internal error' );
  }

  /* #endregion */

  /* #region PUT */

  /**
   * put
   *
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * @return void
   */
  public function put(): void
  {
    /** @var \Kingsoft\Persist\Base $obj */
    if( $obj = $this->getResource() ) {

      $input = json_decode( file_get_contents( 'php://input' ), true );
      $obj->setFromArray( $input );

      if( $result = $obj->freeze() ) {
        Response::sendStatusCode( StatusCode::OK );
        $payload = [ 'id' => $obj->getKeyValue(), 'result' => $result ];
        Response::sendPayLoad( $payload, [ $obj, "getStateHash" ] );

      }
      Response::sendStatusCode( StatusCode::InternalServerError );
      Response::sendMessage( 'error', 0, 'Internal error' );

    }
  }

  /* #endregion */

  /* #region DELETE */
  /**
   * delete
   *
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * @return void
   */
  public function delete(): void
  {
    /**@var \Kingsoft\Persist\Db\DBPersistTrait $obj */
    if( $obj = $this->getResource() ) {
      Response::sendStatusCode( StatusCode::OK );
      $payload = [ 'id' => $obj->getKeyValue(), 'result' => $obj->delete() ];
      Response::sendPayLoad( $payload );
    }
  }

  /* #endregion */

  /* #region HEAD */

  /**
   * head
   *
   * @throws \Exception, \InvalidArgumentException, \Kingsoft\DB\DatabaseException, \Kingsoft\Persist\RecordNotFoundException
   * @return void
   */
  public function head(): void
  {
    if( $obj = $this->getResource() ) {
      $null = null;
      if( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) and $_SERVER['HTTP_IF_NONE_MATCH'] == $obj->getStateHash() ) {
        Response::sendStatusCode( StatusCode::NotModified );
        Response::sendPayload( $null, [ $obj, "getStateHash" ] );
      }
      Response::sendStatusCode( StatusCode::NoContent );
      Response::sendPayload( $null, [ $obj, "getStateHash" ] );
    }
    Response::sendStatusCode( StatusCode::NotFound );
    exit();
  }

  /* #endregion */

  /* #region PATCH */
  /**
   *
   */
  public function patch(): void
  {
    $this->put();
  }
  /* #endregion */
}