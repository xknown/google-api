<?php
use Firebase\JWT\JWT;

class SimpleGoogleClient {
	const GOOGLE_APIS_URL = 'https://www.googleapis.com';
	private $accountToImpersonate;
	private $credentials;

	public function __construct( string $accountToImpersonate )
	{
		$this->accountToImpersonate = $accountToImpersonate;
	}
	public function auth() : void {
		$now = time();
		if ( !empty( $this->credentials ) && $this->credentials->issuedAt < $now ) {
			return;
		}

		$ttl = 3600;
		$payload = [
			'iss' => get_google_application_credentials()->client_email,
			'aud' => 'https://oauth2.googleapis.com/token',
			'exp' => $now + $ttl,
			'iat' => $now,
			'scope' => 'https://www.googleapis.com/auth/calendar',
			'sub' => $this->accountToImpersonate,
		];
		$jwt_token = JWT::encode( $payload, get_google_application_credentials()->private_key, 'RS256' );
		$body      = [
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion' => $jwt_token
		];
		$this->credentials = (object) [
			'issuedAt' => $now,
			'data' => $this->request( 'https://oauth2.googleapis.com/token', $body, [], 'POST' )
		];
	}

	private function request( $url, $body = [], $headers = [], $method = 'GET' ) {
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		if ( 'POST' === strtoupper( $method ) ) {
			curl_setopt($ch, CURLOPT_POST, 1 );
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query( $body ) );
		}
		if ( $headers ) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		$result = curl_exec( $ch );
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( 200 !== $code ) {
			throw new \Exception( 'Failed request: ' . var_export( $result, true )  );
		}
		return json_decode( $result );
	}

	public function get( $path ) {
		$this->auth();
		$headers = [
			'Authorization: Bearer ' . $this->credentials->data->access_token
		];
		return $this->request(self::GOOGLE_APIS_URL . '/' . ltrim( $path, '/' ), [], $headers);
	}

	public function post($path, $body) {
		$this->auth();
		$headers = [
			'Authorization: Bearer ' . $this->credentials->data->access_token
		];
		return $this->request(self::GOOGLE_APIS_URL . '/' . ltrim( $path, '/' ), $body, $headers,'POST');
	}
}

function get_google_application_credentials() {
	static $credentials;
	if ( !isset( $credentials ) ) {
		if ( empty( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) ) || ! is_file( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) ) ) {
			throw new \Exception( 'Invalid file in GOOGLE_APPLICATION_CREDENTIALS' );
		}
		$credentials = json_decode( file_get_contents( getenv( 'GOOGLE_APPLICATION_CREDENTIALS' ) ) );
		if ( empty( $credentials->client_email ) ) {
			throw new \Exception( 'Invalid credentials file' );
		}
	}
	return $credentials;
}
