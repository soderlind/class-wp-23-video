<?php
/**
 *  WP_23_Video is a WordPress client library for 23 Video API  http://www.23video.com/api/
 *
 * Example:
 *   require_once('class-wp-23-video.php');
 *
 *  $client = new WP_23_Video( $apiurl, $consumerKey, $consumerSecret, $accessToken, $accessTokenSecret );
 *
 *  $response = $client->get( '/api/photo/list' ,  array(
 *    , 'include_unpublished_p' => 0
 *    , 'video_p' => 1
 *   ) );
 *
 *  if ( '' !== $response && 'ok' == $response->status ) {
 *   printf( '<pre>%s</pre>', print_r( $response, true ) );
 *  } else if ( '' !== $response ) {
 *   echo 'ERROR: empty object';
 *  } else {
 *   printf( 'ERROR: <pre>%s</pre>', print_r( $response, true ) );
 *  }
 *
 *  Inspiration and code from https://github.com/aaroncampbell/twitter-widget-pro/blob/master/lib/wp-twitter.php, Copyright Aaron D. Campbell  ( email : wp_plugins@xavisys.com )
 *
 *
 * Copyright 2014-current  Per Soderlind  ( email : per@soderlind.no )
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * ( at your option ) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */
class WP_23_Video {

	private $_host;
	private $_consumerKey;
	private $_consumerSecret;
	private $_accessToken;
	private $_accessTokenSecret;

	/**
	 * Initialize a 23 API client
	 * See 'Setting up your application' at http://www.23video.com/api/oauth#setting-up-your-application
	 *
	 * @param string  $host              Host of the 23 Video site including protocol such as http://mysite.23video.com
	 * @param string  $consumerKey       Consumer key
	 * @param string  $consumerSecret    Consumer secret
	 * @param string  $accessToken       Access token
	 * @param string  $accessTokenSecret Access token secret
	 */
	public function __construct( $host, $consumerKey, $consumerSecret, $accessToken, $accessTokenSecret ) {

		$this->_host = untrailingslashit( $host );
		$this->_consumerKey = $consumerKey;
		$this->_consumerSecret = $consumerSecret;
		$this->_accessToken = $accessToken;
		$this->_accessTokenSecret = $accessTokenSecret;
	}

	/**
	 * Perform a GET request
	 *
	 * @param string  $endpoint        Endpoint URL endpoint starting with the root slash e.g. '/api/photo/list'
	 * @param array   $body_parameters Request parameters
	 * @return string                  A string containing the body of the response
	 */
	public function get( $endpoint, $body_parameters = array() ) {
		return $this->request( $endpoint, $body_parameters, 'GET' );
	}

	/**
	 * Perform a POST request
	 *
	 * @param string  $endpoint        Endpoint URL endpoint starting with the root slash e.g. '/api/photo/list'
	 * @param array   $body_parameters Request parameters
	 * @return string                  A string containing the body of the response
	 */
	public function post( $endpoint, $body_parameters = array() ) {
		return $this->request( $endpoint, $body_parameters, 'POST' );
	}

	/**
	 * Perform a request
	 *
	 * @param string  $endpoint        Endpoint URL endpoint starting with the root slash e.g. '/api/photo/list'
	 * @param array   $body_parameters Request parameters
	 * @param string  $method          Set http method, default = GET
	 * @return string                  A string containing the body of the response
	 */
	public function request( $endpoint, $body_parameters = array(), $method = 'GET' ) {

		$parameters = $this->_get_request_defaults();
		$parameters['body'] = wp_parse_args( $body_parameters, $parameters['body'] );
		$request_url = esc_url(  $this->_host . untrailingslashit( $endpoint ) );

		switch ( $method ) {
		case 'GET':
			$this->sign_request( $parameters, $request_url, 'GET' );
			$request_url = $request_url . '?' . WP_23_Video_OAuth_Util::build_http_query( $parameters['body'] );
			unset( $parameters['body'] );
			$resp = wp_remote_get( $request_url, $parameters );
			break;
		default:
			$this->sign_request( $parameters, $request_url, $method );
			$parameters['method'] = $method;
			$resp = wp_remote_post( $request_url, $parameters );
		}
		// printf( 'Parameters<pre>%s</pre>', print_r( $parameters, true ) );
		// printf( 'RESP<pre>%s</pre>', print_r( $resp, true ) );


		if ( !is_wp_error( $resp ) && $resp['response']['code'] >= 200 && $resp['response']['code'] < 300 ) {
			$decoded_response = json_decode( $resp['body'], true ); // true = return an assoc array
			/**
			 * There is a problem with some versions of PHP that will cause
			 * json_decode to return the string passed to it in certain cases
			 * when the string isn't valid JSON.  This is causing me all sorts
			 * of pain.  The solution so far is to check if the return isset()
			 * which is the correct response if the string isn't JSON.  Then
			 * also check if a string is returned that has an = in it and if
			 * that's the case assume it's a string that needs to fall back to
			 * using wp_parse_args()
			 *
			 * @see https://bugs.php.net/bug.php?id=45989
			 * @see https://github.com/OpenRange/twitter-widget-pro/pull/8
			 */
			if ( ( ! isset( $decoded_response ) && ! empty( $resp['body'] ) ) || ( is_string( $decoded_response ) && false !== strpos( $resp['body'], '=' ) ) )
				$decoded_response = wp_parse_args( $resp['body'] );

			if ( 'ok' !== $decoded_response['status'] ) {
				return new WP_Error( $decoded_response['code'], $decoded_response['message'] );
			} else {
				return $decoded_response;
			}

		} else {
			if ( is_wp_error( $resp ) )
				return $resp;
			return new WP_Error( $resp['response']['code'], 'Could not recognize the response from 23' );
		}
	}

	/**
	 * Get the default parameters
	 *
	 * @return array Array containing default parameters
	 */
	private function _get_request_defaults() {
		$params = array(
			'sslverify' => apply_filters( 'video23connect_sslverify', false ),
			'body'      => array(
				'oauth_version'      => '1.0',
				'oauth_nonce'        => wp_create_nonce( 'video23connect' ),
				'oauth_timestamp'    => time(),
				'oauth_consumer_key' => $this->_consumer_key,
				'oauth_token'        => $this->_accessToken,
				'oauth_consumer_key' => $this->_consumerKey,
				'format'             => 'json',
				'raw'                => 'raw'
			),
		);

		return $params;
	}

	/**
	 * Create OAuth HMAC-SHA1 signature
	 *
	 * @param array   $parameters  Request parameters
	 * @param string  $request_url
	 * @param string  $method      http method, default = GET
	 */
	public function sign_request( &$parameters, $request_url, $method = 'GET' ) {
		$parameters['body']['oauth_signature_method'] = 'HMAC-SHA1';
		$parameters['body']['oauth_signature'] = $this->build_signature( $parameters['body'], $request_url, $method );
	}

	/**
	 * Sorte and concatenated the request parameters into a normalized string.
	 *
	 * @param array   $parameters request parameters
	 * @return string             The request parameters, sorted and concatenated into a normalized string
	 */
	public function get_signable_parameters( $parameters ) {
		// Remove oauth_signature if present
		// Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
		if ( isset( $parameters['oauth_signature'] ) ) {
			unset( $parameters['oauth_signature'] );
		}

		return WP_23_Video_OAuth_Util::build_http_query( $parameters );
	}

	/**
	 * Build an OAuth signature
	 *
	 * @param array   $parameters
	 * @param string  $request_url [description]
	 * @param string  $method      [description]
	 * @return [type]              [description]
	 */
	public function build_signature( $parameters, $request_url, $method = 'GET' ) {
		$parts = array(
			$method,
			$request_url,
			$this->get_signable_parameters( $parameters )
		);

		$parts = WP_23_Video_OAuth_Util::urlencode_rfc3986( $parts );
		$base_string = implode( '&', $parts );
		$key_parts = array(
			$this->_consumerSecret,
			$this->_accessTokenSecret,
		);

		$key_parts = WP_23_Video_OAuth_Util::urlencode_rfc3986( $key_parts );
		$key = implode( '&', $key_parts );

		return base64_encode( hash_hmac( 'sha1', $base_string, $key, true ) );
	}

}

/**
 * Class with oauth helper functions
 * The code is from https://github.com/aaroncampbell/twitter-widget-pro/blob/master/lib/oauth-util.php
 */
class WP_23_Video_OAuth_Util {

	public static function build_http_query( $params ) {
		if ( ! $params )
			return '';

		// Urlencode both keys and values
		$keys = self::urlencode_rfc3986( array_keys( $params ) );
		$values = self::urlencode_rfc3986( array_values( $params ) );
		$params = array_combine( $keys, $values );

		// Parameters are sorted by name, using lexicographical byte value ordering.
		// Ref: Spec: 9.1.1 (1)
		uksort( $params, 'strcmp' );

		$pairs = array();
		foreach ( $params as $parameter => $value ) {
			if ( is_array( $value ) ) {
				// If two or more parameters share the same name, they are sorted by
				// their value Ref: Spec: 9.1.1 (1)
				natsort( $value );
				foreach ( $value as $duplicate_value ) {
					$pairs[] = $parameter . '=' . $duplicate_value;
				}
			} else {
				$pairs[] = $parameter . '=' . $value;
			}
		}
		// For each parameter, the name is separated from the corresponding value by
		// an '=' character (ASCII code 61) Each name-value pair is separated by an
		// '&' character (ASCII code 38)
		return implode( '&', $pairs );
	}

	public static function urlencode_rfc3986( $input ) {
		if ( is_array( $input ) )
			return array_map( array( self, 'urlencode_rfc3986' ), $input );
		else if ( is_scalar( $input ) )
				return str_replace( '+', ' ', str_replace( '%7E', '~', rawurlencode( $input ) ) );
			else
				return '';
	}

}
