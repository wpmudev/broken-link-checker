<?php

/*
Plugin Name: Basic HTTP
Description: Check all links that have the HTTP/HTTPS protocol.
Version: 1.0
Author: Janis Elsts

ModuleID: http
ModuleCategory: checker
ModuleContext: on-demand
ModuleLazyInit: true
ModuleClassName: blcHttpChecker
ModulePriority: -1
*/

require_once BLC_DIRECTORY . '/includes/token-bucket.php';

//TODO: Rewrite sub-classes as transports, not stand-alone checkers
class blcHttpChecker extends blcChecker {
	/* @var blcChecker */
	var $implementation = null;

	/** @var  blcTokenBucketList */
	private $token_bucket_list;

	function init() {
		parent::init();

		$conf                    = blc_get_configuration();
		$this->token_bucket_list = new blcTokenBucketList(
			$conf->get( 'http_throttle_rate', 3 ),
			$conf->get( 'http_throttle_period', 15 ),
			$conf->get( 'http_throttle_min_interval', 2 )
		);

		if ( function_exists( 'curl_init' ) || is_callable( 'curl_init' ) ) {
			$this->implementation = new blcCurlHttp(
				$this->module_id,
				$this->cached_header,
				$this->plugin_conf,
				$this->module_manager
			);
		} else {
			//try and use wp request method
			$this->implementation = new blcWPHttp(
				$this->module_id,
				$this->cached_header,
				$this->plugin_conf,
				$this->module_manager
			);
		}
	}

	function can_check( $url, $parsed ) {
		if ( isset( $this->implementation ) ) {
			return $this->implementation->can_check( $url, $parsed );
		} else {
			return false;
		}
	}

	function check( $url, $use_get = false ) {
		global $blclog;

		//Throttle requests based on the domain name.
		$domain = @parse_url( $url, PHP_URL_HOST );
		if ( $domain ) {
			$this->token_bucket_list->takeToken( $domain );
		}

		$blclog->debug( 'HTTP module checking "' . $url . '"' );
		return $this->implementation->check( $url, $use_get );
	}
}


/**
 * Base class for checkers that deal with HTTP(S) URLs.
 *
 * @package Broken Link Checker
 * @access public
 */
class blcHttpCheckerBase extends blcChecker {

	function clean_url( $url ) {
		$url = html_entity_decode( $url );

		$ltrm = preg_quote( json_decode( '"\u200E"' ), '/' );
		$url  = preg_replace(
			array(
				'/([\?&]PHPSESSID=\w+)$/i', //remove session ID
				'/(#[^\/]*)$/',             //and anchors/fragments
				'/&amp;/',                  //convert improper HTML entities
				'/([\?&]sid=\w+)$/i',       //remove another flavour of session ID
				'/' . $ltrm . '/',          //remove Left-to-Right marks that can show up when copying from Word.
			),
			array( '', '', '&', '', '' ),
			$url
		);
		$url  = trim( $url );

		return $url;
	}

	function is_error_code( $http_code ) {
		/*"Good" response codes are anything in the 2XX range (e.g "200 OK") and redirects  - the 3XX range.
		  HTTP 401 Unauthorized is a special case that is considered OK as well. Other errors - the 4XX range -
		  are treated as such. */
		$good_code = ( ( $http_code >= 200 ) && ( $http_code < 400 ) ) || ( 401 === $http_code );
		return ! $good_code;
	}

	/**
	 * This checker only accepts HTTP(s) links.
	 *
	 * @param string $url
	 * @param array|bool $parsed
	 * @return bool
	 */
	function can_check( $url, $parsed ) {
		if ( ! isset( $parsed['scheme'] ) ) {
			return false;
		}

		return in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ) );
	}

	/**
	 * Takes an URL and replaces spaces and some other non-alphanumeric characters with their urlencoded equivalents.
	 *
	 * @param string $url
	 * @return string
	 */
	function urlencodefix( $url ) {
		//TODO: Remove/fix this. Probably not a good idea to "fix" invalid URLs like that.
		return preg_replace_callback(
			'|[^a-z0-9\+\-\/\\#:.,;=?!&%@()$\|*~_]|i',
			function( $str ) {
				return rawurlencode( $str[0] );
			},
			$url
		);
	}

}

class blcCurlHttp extends blcHttpCheckerBase {

	var $last_headers = '';

	function check( $url, $use_get = false ) {
		global $blclog;
		$blclog->info( __CLASS__ . ' Checking link', $url );

		$this->last_headers = '';

		$url = $this->clean_url( $url );
		$blclog->debug( __CLASS__ . ' Clean URL:', $url );

		$result = array(
			'broken'  => false,
			'timeout' => false,
			'warning' => false,
		);
		$log    = '';

		//Get the BLC configuration. It's used below to set the right timeout values and such.
		$conf = blc_get_configuration();

		//Init curl.
		$ch              = curl_init();
		$request_headers = array();
		curl_setopt( $ch, CURLOPT_URL, $this->urlencodefix( $url ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		//Masquerade as a recent version of Chrome
		$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36';
		curl_setopt( $ch, CURLOPT_USERAGENT, $ua );

		//Close the connection after the request (disables keep-alive). The plugin rate-limits requests,
		//so it's likely we'd overrun the keep-alive timeout anyway.
		curl_setopt( $ch, CURLOPT_FORBID_REUSE, true );
		$request_headers[] = 'Connection: close';

		//Add a semi-plausible referer header to avoid tripping up some bot traps
		curl_setopt( $ch, CURLOPT_REFERER, home_url() );

		//Redirects don't work when safe mode or open_basedir is enabled.
		if ( ! blcUtility::is_safe_mode() && ! blcUtility::is_open_basedir() ) {
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		} else {
			$log .= "[Warning] Could't follow the redirect URL (if any) because safemode or open base dir enabled\n";
		}

		//Set maximum redirects
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );

		//Set the timeout
		curl_setopt( $ch, CURLOPT_TIMEOUT, $conf->options['timeout'] );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $conf->options['timeout'] );

		//Set the proxy configuration. The user can provide this in wp-config.php
		if ( defined( 'WP_PROXY_HOST' ) ) {
			curl_setopt( $ch, CURLOPT_PROXY, WP_PROXY_HOST );
		}
		if ( defined( 'WP_PROXY_PORT' ) ) {
			curl_setopt( $ch, CURLOPT_PROXYPORT, WP_PROXY_PORT );
		}
		if ( defined( 'WP_PROXY_USERNAME' ) ) {
			$auth = WP_PROXY_USERNAME;
			if ( defined( 'WP_PROXY_PASSWORD' ) ) {
				$auth .= ':' . WP_PROXY_PASSWORD;
			}
			curl_setopt( $ch, CURLOPT_PROXYUSERPWD, $auth );
		}

		//Make CURL return a valid result even if it gets a 404 or other error.
		curl_setopt( $ch, CURLOPT_FAILONERROR, false );

		$nobody = ! $use_get; //Whether to send a HEAD request (the default) or a GET request

		$parts = @parse_url( $url );
		if ( 'https' === $parts['scheme'] ) {
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); //Required to make HTTPS URLs work.
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			//$nobody = false; //Can't use HEAD with HTTPS.
		}

		if ( $nobody ) {
			//If possible, use HEAD requests for speed.
			curl_setopt( $ch, CURLOPT_NOBODY, true );
		} else {
			//If we must use GET at least limit the amount of downloaded data.
			$request_headers[] = 'Range: bytes=0-2048'; //2 KB
		}

		//Set request headers.
		if ( ! empty( $request_headers ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $request_headers );
		}

		//Register a callback function which will process the HTTP header(s).
		//It can be called multiple times if the remote server performs a redirect.
		curl_setopt( $ch, CURLOPT_HEADERFUNCTION, array( $this, 'read_header' ) );

		//Record request headers.
		if ( defined( 'CURLINFO_HEADER_OUT' ) ) {
			curl_setopt( $ch, CURLINFO_HEADER_OUT, true );
		}

		// Apply filter for additional options
		curl_setopt_array( $ch, apply_filters( 'broken-link-checker-curl-options', array() ) );

		//Execute the request
		$start_time                = microtime_float();
		$content                   = curl_exec( $ch );
		$measured_request_duration = microtime_float() - $start_time;
		$blclog->debug( sprintf( 'HTTP request took %.3f seconds', $measured_request_duration ) );

		$info = curl_getinfo( $ch );

		// var_dump( $info ); die();
		//Store the results
		$result['http_code']        = intval( $info['http_code'] );
		$result['final_url']        = $info['url'];
		$result['request_duration'] = $info['total_time'];
		$result['redirect_count']   = $info['redirect_count'];

		//CURL doesn't return a request duration when a timeout happens, so we measure it ourselves.
		//It is useful to see how long the plugin waited for the server to respond before assuming it timed out.
		if ( empty( $result['request_duration'] ) ) {
			$result['request_duration'] = $measured_request_duration;
		}

		//Determine if the link counts as "broken"
		if ( 0 === absint( $result['http_code'] ) ) {
			$result['broken'] = true;

			$error_code = curl_errno( $ch );
			$log       .= sprintf( "%s [Error #%d]\n", curl_error( $ch ), $error_code );

			//We only handle a couple of CURL error codes; most are highly esoteric.
			//libcurl "CURLE_" constants can't be used here because some of them have
			//different names or values in PHP.
			switch ( $error_code ) {
				case 6: //CURLE_COULDNT_RESOLVE_HOST
					$result['status_code'] = BLC_LINK_STATUS_WARNING;
					$result['status_text'] = __( 'Server Not Found', 'broken-link-checker' );
					$result['error_code']  = 'couldnt_resolve_host';
					break;

				case 28: //CURLE_OPERATION_TIMEDOUT
					$result['timeout'] = true;
					break;

				case 7: //CURLE_COULDNT_CONNECT
					//More often than not, this error code indicates that the connection attempt
					//timed out. This heuristic tries to distinguish between connections that fail
					//due to timeouts and those that fail due to other causes.
					if ( $result['request_duration'] >= 0.9 * $conf->options['timeout'] ) {
						$result['timeout'] = true;
					} else {
						$result['status_code'] = BLC_LINK_STATUS_WARNING;
						$result['status_text'] = __( 'Connection Failed', 'broken-link-checker' );
						$result['error_code']  = 'connection_failed';
					}
					break;

				default:
					$result['status_code'] = BLC_LINK_STATUS_WARNING;
					$result['status_text'] = __( 'Unknown Error', 'broken-link-checker' );
			}
		} elseif ( 999 === $result['http_code'] ) {
			$result['status_code'] = BLC_LINK_STATUS_WARNING;
			$result['status_text'] = __( 'Unknown Error', 'broken-link-checker' );
			$result['warning'] = true;
		} else {
			$result['broken'] = $this->is_error_code( $result['http_code'] );
		}

		// Apply filter before curl closes
		apply_filters( 'broken-link-checker-curl-before-close', $ch, $content, $this->last_headers );

		curl_close( $ch );

		$blclog->info(
			sprintf(
				'HTTP response: %d, duration: %.2f seconds, status text: "%s"',
				$result['http_code'],
				$result['request_duration'],
				isset( $result['status_text'] ) ? $result['status_text'] : 'N/A'
			)
		);

		$use_get = apply_filters( 'blc_use_get_checker', false, $result );

		if ( $nobody && !$result['timeout'] && !$use_get && ($result['broken'] || $result['redirect_count'] == 1)){
			//The site in question might be expecting GET instead of HEAD, so lets retry the request
			//using the GET verb...but not in cases of timeout, or where we've already done it.
			return $this->check( $url, true );

			//Note : normally a server that doesn't allow HEAD requests on a specific resource *should*
			//return "405 Method Not Allowed". Unfortunately, there are sites that return 404 or
			//another, even more general, error code instead. So just checking for 405 wouldn't be enough.
		}

		//When safe_mode or open_basedir is enabled CURL will be forbidden from following redirects,
		//so redirect_count will be 0 for all URLs. As a workaround, set it to 1 when the HTTP
		//response codes indicates a redirect but redirect_count is zero.
		//Note to self : Extracting the Location header might also be helpful.
		if ( ( 0 === absint( $result['redirect_count'] ) ) && ( in_array( $result['http_code'], array( 301, 302, 303, 307 ) ) ) ) {
			$result['redirect_count'] = 1;
		}

		//Build the log from HTTP code and headers.
		$log .= '=== ';
		if ( $result['http_code'] ) {
			$log .= sprintf( __( 'HTTP code : %d', 'broken-link-checker' ), $result['http_code'] );
		} else {
			$log .= __( '(No response)', 'broken-link-checker' );
		}
		$log .= " ===\n\n";

		$log .= "Response headers\n" . str_repeat( '=', 16 ) . "\n";
		$log .= htmlentities( $this->last_headers );

		if ( isset( $info['request_header'] ) ) {
			$log .= "Request headers\n" . str_repeat( '=', 16 ) . "\n";
			$log .= htmlentities( $info['request_header'] );
		}

		if ( ! $nobody && ( false !== $content ) && $result['broken'] ) {
			$log .= "Response HTML\n" . str_repeat( '=', 16 ) . "\n";
			$log .= htmlentities( substr( $content, 0, 2048 ) );
		}

		if ( ! empty( $result['broken'] ) && ! empty( $result['timeout'] ) ) {
			$log .= "\n(" . __( "Most likely the connection timed out or the domain doesn't exist.", 'broken-link-checker' ) . ')';
		}

		$result['log'] = $log;

		//The hash should contain info about all pieces of data that pertain to determining if the
		//link is working.
		$result['result_hash'] = implode(
			'|',
			array(
				$result['http_code'],
				! empty( $result['broken'] ) ? 'broken' : '0',
				! empty( $result['timeout'] ) ? 'timeout' : '0',
				blcLink::remove_query_string( $result['final_url'] ),
			)
		);

		return $result;
	}

	function read_header( /** @noinspection PhpUnusedParameterInspection */ $ch, $header ) {
		$this->last_headers .= $header;
		return strlen( $header );
	}

}

class blcWPHttp extends blcHttpCheckerBase {

	function check( $url ) {

		// $url = $this->clean_url( $url );
		//Note : Snoopy doesn't work too well with HTTPS URLs.

		$result = array(
			'broken'  => false,
			'timeout' => false,
		);
		$log    = '';

		//Get the timeout setting from the BLC configuration.
		$conf    = blc_get_configuration();
		$timeout = $conf->options['timeout'];

		$start_time = microtime_float();

		//Fetch the URL with Snoopy
		$snoopy               = new WP_Http;
		$request_args         = array(
			'timeout'    => $timeout,
			'user-agent' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)', //masquerade as IE 7
			'aa' => 1024 * 5,
		);
		$request = wp_safe_remote_get( $this->urlencodefix( $url ), $request_args );

		//request timeout results in WP ERROR
		if ( is_wp_error( $request ) ) {
			$result['http_code'] = 0;
			$result['timeout']   = true;
			$result['message']   = $request::get_error_message();
		} else {
			$http_resp           = $request['http_response'];
			$result['http_code'] = $request['response']['status']; //HTTP status code
			$result['message']   = $request['response']['message'];
		}

		//Build the log
		$log .= '=== ';
		if ( $result['http_code'] ) {
			$log .= sprintf( __( 'HTTP code : %d', 'broken-link-checker' ), $result['http_code'] );
		} else {
			$log .= __( '(No response)', 'broken-link-checker' );
		}
		$log .= " ===\n\n";

		if ( $result['message'] ) {
			$log .= $result['message'] . "\n";
		}

		if ( is_wp_error( $request ) ) {
			$log              .= __( 'Request timed out.', 'broken-link-checker' ) . "\n";
			$result['timeout'] = true;
		}

		//Determine if the link counts as "broken"
		$result['broken'] = $this->is_error_code( $result['http_code'] ) || $result['timeout'];

		$log          .= '<em>(' . __( 'Using WP HTTP', 'broken-link-checker' ) . ')</em>';
		$result['log'] = $log;

		$result['final_url'] = $url;

		//The hash should contain info about all pieces of data that pertain to determining if the
		//link is working.
		$result['result_hash'] = implode(
			'|',
			array(
				$result['http_code'],
				$result['broken'] ? 'broken' : '0',
				$result['timeout'] ? 'timeout' : '0',
				blcLink::remove_query_string( $result['final_url'] ),
			)
		);

		return $result;
	}

}
