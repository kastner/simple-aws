<?php
/**
 * Object-oriented access to an HTTP response.
 */

class HTTP_Response {

	private $_status = array();
	private $_headers = array();
	private $_headers_raw = '';
	private $_body = '';
	private $_failed = false;


	/**
	 * Create an object out of a raw HTTP response (headers and body).
	 *
	 * @param String Raw headers and body of HTTP response
	 */
	public function __construct($raw) {
		if (!$raw) {
			// No response headers or body?
			$this->_failed = true;
			return;
		} else if ($raw == 1) {
			// Curl may respond with "1" if the response is directed
			// to a file, such as a download.
			$this->_status['status_code'] = '200';
			$this->_status['status'] = 'OK';
			return;
		}

		// Split headers and body of response.
		$parts = explode("\r\n\r\n", $raw, 2);
		if (isset($parts[0])) {
			$this->_headers_raw = $parts[0];
			$this->_parseHeaders($parts[0]);
		}
		if (isset($parts[1])) {
			$this->_body = $parts[1];
		}

		return;
	}


	/**
	 * Return the HTTP status code for the response.
	 *
	 * @return mixed Integer status code, or false if request has failed
	 */
	public function getStatusCode() {
		return (!$this->_failed) ? $this->_status['status_code'] : false;
	}


	/**
	 * Return the HTTP status message for the response.
	 *
	 * @return mixed Status message, or false if request has failed
	 */
	public function getStatus() {
		return (!$this->_failed) ? $this->_status['status'] : false;
	}


	/**
	 * Return value of a specific HTTP header.
	 *
	 * @param string HTTP header name
	 * @return mixed Value of header, or false if request has failed
	 */
	public function getHeader($name) {
		if ($this->_failed || !isset($this->_headers[$name])) {
			return false;
		} else {
			return $this->_headers[$name];
		}
	}


	/**
	 * Return the body of an HTTP response
	 *
	 * @return mixed Body of HTTP response (string), or false if request has failed
	 */
	public function getBody() {
		return (!$this->_failed) ? $this->_body : false;
	}


	/**
	 * Return boolean flag identifying whether the response is a success.
	 *
	 * @return bool True if response has failed, false otherwise.
	 */
	public function failed() {
		return $this->_failed;
	}


	/**
	 * Parse HTTP headers into object's properties.
	 *
	 * @param string HTTP response headers
	 */
	private function _parseHeaders($headers) {
		$headers = explode("\r\n", $headers);

		// Pull off the HTTP status
		$status = array_shift($headers);
		list($version, $status_code, $status) = explode(' ', $status, 3);

		$this->_status['version']     = $version;
		$this->_status['status_code'] = $status_code;
		$this->_status['status']      = $status;

		foreach ($headers as $header) {
			list($key, $value) = explode(': ', $header, 2);
			// This will fail if multiple headers occur with the same name.
			$this->_headers[$key] = $value;
		}
	}


	/**
	 * Echo entire response object for debugging.
	 */
	public function dump() {
		echo "Status:\n";
		echo $this->_status['version'] . ' '
			. $this->_status['status_code'] . ' '
			. $this->_status['status'] . "\n\n";
		echo "Raw Headers:\n";
		echo $this->_headers_raw;
		echo "\n\n";
		echo "Headers:\n";
		print_r($this->_headers);
		echo"\n";
		echo "Body:\n";
		echo $this->_body;
		echo"\n";
	}

}

?>