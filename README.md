# http

HTTP request, response, statuscodes

## sample implementation

Sample implementation of the abstract Rest class under sample. Here a possible implementation:

```php
use Kingsoft\Http\StatusCode;
use Kingsoft\PersistRest\PersistRest;
use Kingsoft\PersistRest\PersistRequest;
use Kingsoft\Http\Response;

class MyRest extends Rest
{
  public function get(): {
    Response::sendStatusCode( StatusCode::OK );
    Response::sendPayload( [ 'result'=> 'ok']);
  }
  public function post(): {
    Response::sendStatusCode( StatusCode::OK );
    Response::sendPayload( [ 'result'=> 'ok']);
  }
}

try {
  $request = new Request(
    [ 'Test' ],                         // allowed endpoints
    "GET, POST",                        // allowedmethods
    "http://client.example.com",        // allowedorigin
  );

  $request->setLogger( LOG );           // add a (monolog) logger
  $api = new MyRest( $request, LOG );   // create the request handler
  $api->handleRequest();                // handle the request, which will send a well-formed HATEOAS response
} catch ( Exception $e ) {              // If things go terribly wrong, send an error to the client
  Response::sendError( $e->getMessage(), StatusCode::InternalServerError->value );
                                        // By this time one or more errors have been logged already.
}
```
