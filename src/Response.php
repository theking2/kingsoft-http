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
class Response
{

	/**
	 * sendStatusCode - Send status code header
	 *
	 * @param  mixed $statusCode
	 * @return void
	 */
	public static function sendStatusCode( StatusCode $statusCode ): void
	{
		header_remove( 'x-powered-by' );
		http_response_code( $statusCode->value );

	}
	/**
	 * sendContentType - Send content type header
	 *
	 * @param  mixed $contentType
	 * @return void
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
	 * sendPayload - Send payload
	 * Side effect: exit
	 * @param  array|object|null $payload - payload to send, if null, exit
	 * @param  callable $get_etag - function to get etag, even if payload is null
	 * @param  ContentType $type - content type, default json
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
	 * sendMessage Send a fixed message
	 * @side-effect exit
	 * @param $result
	 * @param $code
	 * @param $message
	 * @param $type
	 *
	 * @return void
	 */
	public static function sendMessage(
		string $result,
		int|StatusCode $code = StatusCode::OK,
		?string $message = "",
		?ContentType $type = ContentType::Json,
	) {
		if ( is_int( $code ) ) {
			$code = StatusCode::tryFrom( $code ) ?? StatusCode::OK;
		}
		$payload = [ 
			"result" => $result,
			"message" => $message,
			"code" => $code->value
		];
		self::sendPayload( $payload, null, $type );
	}

	/**
	 * sendError
	 * @param $message
	 * @param $code
	 *
	 * @return void
	 */
	public static function sendError(
		string $message,
		int|StatusCode $code = StatusCode::InternalServerError,
		?ContentType $type = ContentType::Json,
	) {
		if ( is_int( $code ) ) {
			$code = StatusCode::tryFrom( $code ) ?? StatusCode::InternalServerError;
		}
		self::sendStatusCode( $code );
		self::sendMessage(
			StatusCode::toString( $code ),
			$code,
			$message,
			$type
		);
	}
}
