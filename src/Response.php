<?php

declare(strict_types=1);

namespace Kingsoft\HTTP;

enum ApplicationContentTypeString : string
{
	case APPLICATION_JSON = 'application/json';
	case APPLICATION_PROBLEM_JSON = 'application/problem+json';
	case APPLICATION_XML = 'application/xml';
	case APPLICATION_PROBLEM_XML = 'application/problem+xml';
}
enum ApplicationType : string
{
	case JSON = 'json';
	case XML = 'xml';
}

class Response
{
	public function __construct(
		readonly StatusCode $statusCode,
		readonly string|null $body,
		readonly string|null $allowedMethods = 'OPTIONS,GET,POST,PUT,DELETE',
		readonly string|null $etag,
		readonly string|null $headers = 'Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With',
		readonly string|null $origin = '*',
		readonly int|null $maxAge = 3600,
		
	) {}

	private function decodeHttpResponse( ): string
	{
		return match ( $this-> statusCode ) {
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
	 * sendAccessControlAllowOrigin` send the Access-Control-Allow-Origin header
	 *
	 * @return self
	 */
	public function sendAccessControlAllowOrigin( ): self
	{
		header( "Access-Control-Allow-Origin: " . ( $this-> origin ?? '*' ) );
		return $this;
	}	
	/**
	 * sendAccessControlAllowMethods send the Access-Control-Allow-Methods header
	 *
	 * @return Response
	 */
	public function sendAccessControlAllowMethods( ): self
	{
		header( "Access-Control-Allow-Methods: " . ( $this-> allowedMethods ?? 'OPTIONS,GET,POST,PUT,DELETE' ) );
		return $this;
	}	
	/**
	 * sendAccessControlMaxAge send the Access-Control-Max-Age header
	 *
	 * @return self
	 */
	public function sendAccessControlMaxAge( ): self
	{
		header( "Access-Control-Max-Age: " . ( $this-> maxAge ?? 3600 ) );
		return $this;
	}	
	/**
	 * sendAccessControlAllowHeaders send the Access-Control-Allow-Headers header
	 *
	 * @return Response
	 */
	public function sendAccessControlAllowHeaders( ): self
	{
		header( "Access-Control-Allow-Headers: " . ( $this-> headers ?? 'Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With' ) );
		return $this;
	}	
	
	/**
	 * sendETag send the ETag header based on the body
	 *
	 * @return Response
	 */
	public function sendETag( ): self
	{
		header( "ETag: " . $this-> etag ?? hash( 'md5', $this-> body ) );
		return $this;
	}	
	/**
	 * sendContentTypeJson based on the HTTP status code
	 * json or problem+json
	 *
	 * @param  mixed $httpStatusCode
	 * @return Response
	 */
	public function sendContentType( ApplicationType|null $type=ApplicationType::JSON ): self
	{
		$contentType = null;
		if( $this-> statusCode->value >= 400 && $this-> statusCode->value < 600 ) {
			$contentType = match( $type ) {
				ApplicationType::JSON => ApplicationContentTypeString::APPLICATION_PROBLEM_JSON,
				ApplicationType::XML => ApplicationContentTypeString::APPLICATION_PROBLEM_XML,
			};
		} else {
			$contentType = match( $type ) {
				ApplicationType::JSON => ApplicationContentTypeString::APPLICATION_JSON,
				ApplicationType::XML => ApplicationContentTypeString::APPLICATION_XML,
			};
		}
		header( "Content-Type: " . ( $contentType ?? 'application/json' ) . "; charset=UTF-8" );
		return $this;
	}	
	/**
	 * sendStatusCode
	 *
	 * @return self
	 */
	public function sendStatusCode( ): self
	{
		header(
			sprintf(
				'HTTP/1.1 %d %s',
				$this-> statusCode->value,
				self::decodeHttpResponse( )
			)
		);
		return $this;
	}


}