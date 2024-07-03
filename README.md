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
    [ 'Test' ],
    "GET, POST",
    "http://client.example.com",
  );
  $request->setLogger( LOG );
  $api = new MyRest( $request, LOG );
  $api->handleRequest();
} catch ( Exception $e ) {
  Response::sendError( $e->getMessage(), StatusCode::InternalServerError->value );
}
```
