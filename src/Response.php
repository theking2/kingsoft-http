<?php declare(strict_types=1);

namespace Kingsoft\Http;

enum ContentTypeString: string
{
	case TextPlain   = 'text/plain';
	case TextHtml    = 'text/html';
	case Json        = 'application/json';
	case JsonProblem = 'application/problem+json';
	// case Xml         = 'application/xml'; no explicit support for xml
	// case XmlProblem  = 'application/problem+xml';
}
enum ContentType: string
{
	case Json = 'json';
	// case Xml  = 'xml'; no explicit support for xml
	case Text = 'text';
}

/**
 * Response - Send response to client
 */
readonly class Response
{
	/**
	 * Send status code header
	 * @param $statusCode Code to be send
	 */
	public static function sendStatusCode( StatusCode $statusCode ): void
	{
		header_remove( 'x-powered-by' );
		http_response_code( $statusCode->value );

	}
	/**
	 * Send content type header
	 * @param $contentType Type to be send
	 */
	public static function sendContentType( ContentType $contentType ): void
	{
		header_remove( 'content-type' );
		header(
			sprintf(
				'Content-Type: %s',
				match ( $contentType ) {
					ContentType::Json => ContentTypeString::Json->value,
					ContentType::Text => ContentTypeString::TextPlain->value,
					default => ContentTypeString::TextPlain->value
				}
			)
		);
	}
	/**
	 * Send payload
	 * Side effect: exit script
	 * 
	 * @param $payload Payload to be send as array or object, if null exit
	 * @param $get_etag Callable that creates the etag or if missing sha1 of the payload
	 * @param $type Type of response to be send
	 */
	public static function sendPayload(
		array|object|null &$payload,
		?callable $get_etag = null,
		?ContentType $type = ContentType::Json ): void
	{
		if( $get_etag ) {
			header( 'ETag: ' . $get_etag() );
		} else {
			header( 'ETag: ' . sha1( serialize( $payload ) ) );
		}
		if( $payload === null ) {
			exit();
		}
		match ( $type ) {
			ContentType::Json => self::sendContentType( ContentType::Json ),
			ContentType::Text => self::sendContentType( ContentType::Text ),
			default => self::sendContentType( ContentType::Json )
		};

		exit( match ( $type ) {
			ContentType::Json => json_encode( $payload ),
			ContentType::Text => serialize( $payload ),
			default => json_encode( $payload )
		} );
	}

	/**
	 * Send a fixed message
	 * 
	 * @param $result of action
	 * @param $code code of the action
	 * @param $message string that contains the message
	 */
	public static function sendMessage(
		string $result,
		?int $code = 0,
		?string $message = "",
		?ContentType $type = ContentType::Json,
	) {
		$payload = [ 
			"result" => $result,
			"message" => $message,
			"code" => $code
		];
		self::sendPayload( $payload, null, $type );
	}

	/**
	 * Send error message
	 * 
	 * @param $message The error message
	 * @param $code Code that describes the error
	 * @param $type ContentType of the response
	 */
	public static function sendError(
		string $message,
		?int $code = 0,
		?ContentType $type = ContentType::Json,
	) {
		self::sendStatusCode( StatusCode::InternalServerError );
		self::sendMessage(
			StatusCode::toString( StatusCode::InternalServerError ),
			$code,
			$message,
			$type
		);
	}
}