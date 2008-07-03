<?php

/**
 * Methods for generating HTTP response headers.
 */
class HTTP_Header_Response extends HTTP_Header_Base {

	/**
	 * Set HTTP expires.  The "interval" parameter makes use of the same syntax
	 * as mod_expires uses for the "ExpiresDefault" directive.  See
	 * HTTP_Header_Base::intervalToTimestamp for more details on format.
	 *
	 * Any cache-related headers that were previously set will be overridden by
	 * this method.
	 *
	 * @param string Time interval
	 * @param string Path to file from which modified times will be calculated (optional).
	 * @see http://httpd.apache.org/docs/2.0/mod/mod_expires.html
	 */
	static public function expires ($interval, $path=__FILE__) {
		$timestamp = self::intervalToTimestamp($interval, $path);
		header('Expires: ' . self::getHTTPDate($timestamp));
		header('Cache-Control: max-age=' . ($timestamp - time()));
		return;
	}


	/**
	 * Set HTTP response headers to prevent the client from caching a response.
	 */
	static public function noCache () {
		// Expiration date in the past.
		header('Expires: ' . self::getHTTPDate(time() - 60*60*24*15));

		// HTTP/1.1
		header('Cache-Control: no-store, no-cache, must-revalidate');
	}


	/**
	 * Redirect to another page with appropriate HTTP headers.  This method causes
	 * script to exit.
	 *
	 * @param string URL for redirect
	 * @param integer Status code for redirect: 301, 302, or 303 (optional).
	 */
	static public function redirect ($url, $status=301) {
		self::status($status);
		header('Location: ' . $url);
		exit;
	}


	/**
	 * Output a Last-Modified date based on the file path provided.
	 *
	 * @param mixed Pass either a timestamp (in seconds) of the last modified time
	 * or a file path to test for modification time.
	 */
	static public function lastModified ($param) {
		if (is_int($param)) {
			$ts = $param;
		} else if (file_exists($param)) {
			$ts = filemtime($param);
		} else {
			throw new Exception("Invalid file path: '$param'");
			$ts = time();
		}
		header('Last-Modified: ' . self::getHTTPDate($ts));
		return;
	}


	/**
	 * Output an HTTP status code header.
	 *
	 * Note that not all status codes currently described in HTTP/1.1 are implemented
	 * in this method.  SERVER_PROTOCOL appears to echo the HTTP version provided
	 * in the request, which could allow for negotiating headers properly between
	 * HTTP specs.
	 *
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
	 * @see http://www.w3.org/Protocols/HTTP/1.0/draft-ietf-http-spec.html#Status-Codes
	 * @param integer Status code
	 */
	static public function status ($status) {
		$codes = array( '200' => 'OK',
						'301' => 'Moved Permanently',
						'302' => 'Found',
						'304' => 'Not Modified',
						'400' => 'Bad Request',
						'401' => 'Unauthorized',
						'403' => 'Forbidden',
						'404' => 'File Not Found',
						'500' => 'Internal Server Error',
						'503' => 'Service Unavailable',

						// The following status codes are only available to
						// HTTP/1.1.  It's possible we'll need to do proper
						// version checks in the future.
						'303' => 'See Other',
						'410' => 'Gone',
					);
		if (!isset($codes[$status])) {
			throw new Exception("Unsupported status code '$status' found");
			return false;
		}

		header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status . ' ' . $codes[$status]);
		return true;
	}


	/**
	 * Check modified headers to see if we should return a fresh response or a
	 * "304 Not Modified" response.  If a 304 response is appropriate, return
	 * the response and exit.
	 *
	 * Adapted from Simon Willison's blog:
	 * @see http://simonwillison.net/2003/Apr/23/conditionalGet/
	 *
	 */
	static public function handleConditionalGet ($modified_timestamp) {
		$last_modified_str = self::getHTTPDate($modified_timestamp);
		$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? preg_replace('/;.*$/', '', stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE'])) : false;

		if (!$if_modified_since) {
			return;
		} else if ($if_modified_since != $last_modified_str) {
			return; // if-modified-since is there but doesn't match
		}

		header('Last-Modified: ' . $last_modified_str);
		self::status(304);
		exit;
	}

}

?>