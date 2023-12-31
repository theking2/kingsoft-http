<?php declare(strict_types=1);

namespace Kingsoft\Http;

enum ContentTypeString: string
{
	case TextPlain = 'text/plain';
	case TextHtml = 'text/html';
	case Json = 'application/json';
	case JsonProblem = 'application/problem+json';
	case Xml = 'application/xml';
	case XmlProblem = 'application/problem+xml';
}
enum ContentType: string
{
	case Json = 'json';
	case Xml = 'xml';
	case Text = 'text';
}

class Response
{

	/**
	 * decodeHttpResponse
	 *
	 * @param  mixed $statusCode
	 * @return string
	 */
	private static function decodeHttpResponse( StatusCode $statusCode ): string
	{
		return match ( $statusCode ) {
			StatusCode::Continue => 'Continue',
			StatusCode::SwitchingProtocols => 'Switching Protocols',
			StatusCode::OK => 'OK',
			StatusCode::Created => 'Created',
			StatusCode::Accepted => 'Accepted',
			StatusCode::NonAuthoritativeInformation => 'Non-Authoritative Information',
			StatusCode::NoContent => 'No Content',
			StatusCode::ResetContent => 'Reset Content',
			StatusCode::PartialContent => 'Partial Content',
			StatusCode::MultipleChoices => 'Multiple Choices',
			StatusCode::MovedPermanently => 'Moved Permanently',
			StatusCode::Found => 'Found',
			StatusCode::SeeOther => 'See Other',
			StatusCode::NotModified => 'Not Modified',
			StatusCode::UseProxy => 'Use Proxy',
			StatusCode::TemporaryRedirect => 'Temporary Redirect',
			StatusCode::BadRequest => 'Bad Request',
			StatusCode::Unauthorized => 'Unauthorized',
			StatusCode::PaymentRequired => 'Payment Required',
			StatusCode::Forbidden => 'Forbidden',
			StatusCode::NotFound => 'Not Found',
			StatusCode::MethodNotAllowed => 'Method Not Allowed',
			StatusCode::NotAcceptable => 'Not Acceptable',
			StatusCode::ProxyAuthenticationRequired => 'Proxy Authentication Required',
			StatusCode::RequestTimeout => 'Request Timeout',
			StatusCode::Conflict => 'Conflict',
			StatusCode::Gone => 'Gone',
			StatusCode::LengthRequired => 'Length Required',
			StatusCode::PreconditionFailed => 'Precondition Failed',
			StatusCode::RequestEntityTooLarge => 'Request Entity Too Large',
			StatusCode::RequestURITooLong => 'Request-URI Too Long',
			StatusCode::UnsupportedMediaType => 'Unsupported Media Type',
			StatusCode::RequestedRangeNotSatisfiable => 'Requested Range Not Satisfiable',
			StatusCode::ExpectationFailed => 'Expectation Failed',
			StatusCode::InternalServerError => 'Internal Server Error',
			StatusCode::NotImplemented => 'Not Implemented',
			StatusCode::BadGateway => 'Bad Gateway',
			StatusCode::ServiceUnavailable => 'Service Unavailable',
			StatusCode::GatewayTimeout => 'Gateway Timeout',
			StatusCode::HTTPVersionNotSupported => 'HTTP Version Not Supported',
			default => 'Unknown HTTP status code'
		};
	}

	/**
	 * sendStatusCode
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
					ContentType::Xml => ContentTypeString::Xml->value,
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
		}
		if( $payload === null ) {
			exit();
		}
		match ( $type ) {
			ContentType::Json => self::sendContentType( ContentType::Json ),
			ContentType::Xml => self::sendContentType( ContentType::Xml ),
			ContentType::Text => self::sendContentType( ContentType::Text ),
			default => self::sendContentType( ContentType::Json )
		};

		exit( match ( $type ) {
			ContentType::Json => json_encode( $payload ),
			ContentType::Xml => xmlrpc_encode( $payload ),
			ContentType::Text => serialize( $payload ),
			default => json_encode( $payload )
		} );
	}

	/**
	 * sendMessage Send a fixed message
	 * @side-effect exit
	 * @param string $result
	 * @param int $code
	 * @param string $message
	 * @param ContentTYpe $type
	 *
	 * @return void
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
}
