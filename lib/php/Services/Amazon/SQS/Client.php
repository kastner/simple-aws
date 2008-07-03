<?php

/**
 * Interface for creating, managing, and deleting Simple Queue Service (SQS)
 * queues.
 *
 * Portions of this code were taken from the Amazon SQS PHP5 Library which is
 * distributed under the Apache 2.0 license (http://aws.amazon.com/apache2.0).
 */
class Services_Amazon_SQS_Client {

	private $_aws_access_key;
	private $_aws_secret_key;

	const SQS_SERVER      = "queue.amazonaws.com";
	const SQS_API_VERSION = "2008-01-01";
	const SQS_SIGNATURE_VERSION = "1";
	const USER_AGENT      = "Simple-AWS-SQSClient/0.1.0";

	protected $_error_code = '';
	protected $_error_message = '';


	public function __construct()
	{
		// Load account keys.
		$account = Services_Amazon_Account::getInstance();
		$this->_aws_access_key = $account->getAccessKey();
		$this->_aws_secret_key = $account->getSecretKey();
		return true;
	}


	/**
	 * Return current error string (if exists).
	 *
	 * @return string Error message.
	 */
	public function getError()
	{
		$msg = '';
		if ($this->_error_code) {
			$msg .= '[' . $this->_error_code . '] ';
		}
		$msg .= $this->_error_message;
		return $msg;
	}


	/**
	 * Add authentication related and version parameters
	 */
	protected function _addRequiredParameters(array $parameters)
	{
		$parameters['AWSAccessKeyId'] = $this->_aws_access_key;
		$parameters['SignatureVersion'] = self::SQS_SIGNATURE_VERSION;
		$parameters['Timestamp'] = $this->_getFormattedTimestamp();
		$parameters['Version'] = self::SQS_API_VERSION;

		$parameters['Signature'] = $this->_signParameters($parameters, $this->_aws_secret_key);

		return $parameters;
	}


	/**
	 * Convert paremeters to URL encoded query string
	 */
	protected function _getParametersAsString(array $parameters)
	{
		$queryParameters = array();
		foreach ($parameters as $key => $value) {
			$queryParameters[] = $key . '=' . urlencode($value);
		}
		return implode('&', $queryParameters);
	}


	/**
	 * Computes RFC 2104-compliant HMAC signature for request parameters
	 * Implements AWS Signature, as per following spec:
	 *
	 * Sorts all parameters (including SignatureVersion and excluding Signature,
	 * the value of which is being created), ignoring case.
	 *
	 * Iterate over the sorted list and append the parameter name (in original case)
	 * and then its value. It will not URL-encode the parameter values before
	 * constructing this string.  There are no separators.
	 */
	protected function _signParameters(array $parameters, $secret_key)
	{
		$data = '';

		uksort($parameters, 'strcasecmp');
		unset ($parameters['Signature']);

		foreach ($parameters as $key => $value) {
			$data .= $key . $value;
		}

		return $this->_sign($data, $secret_key);
	}


	/**
	 * Computes RFC 2104-compliant HMAC signature.
	 */
	private function _sign($data, $secret_key)
	{
		return base64_encode (
			pack("H*", sha1((str_pad($secret_key, 64, chr(0x00))
			^(str_repeat(chr(0x5c), 64))) .
			pack("H*", sha1((str_pad($secret_key, 64, chr(0x00))
			^(str_repeat(chr(0x36), 64))) . $data))))
		);
	}


	/**
	 * Formats date as ISO 8601 timestamp
	 */
	private function _getFormattedTimestamp()
	{
		return gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time());
	}


	/**
	 * Send HTTP request to the queue service using CURL.
	 *
	 * The supplied params array should contain only the specific parameters for
	 * the request type, not including account, signature, or timestamp related
	 * params.  These are added on automatically.
	 *
	 * @param array Array of request parameter for the API call
	 * @param string Queue URL (if available)
	 * @return Mixed HTTP_Response object or false if request failed.
	 */
	protected function _sendRequest($params=null, $queue_url=null)
	{
		$params = $this->_addRequiredParameters($params);

		$url = ($queue_url) ? $queue_url : ('http://' . self::SQS_SERVER . '/');
		$url .= '?' . $this->_getParametersAsString($params);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, true); // include response header
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		$curl_response = curl_exec($ch);

		// Useful debug info.
		//$curl_info = curl_getinfo($ch);

		// Capture curl errors in the object.
		if ($curl_response === false) {
			$this->_error_message = 'Error in request to AWS service: ' . curl_error($ch);
		}

		curl_close($ch);

		return new HTTP_Response($curl_response);
	}


	/**
	 * @param Object HTTP_Response object
	 * @return bool
	 */
	protected function _isOk(HTTP_Response $response)
	{
		if ($response->failed()) {
			return false;
		} else if (preg_match('@<Error>.*?<Code>(.*?)</Code>.*?<Message>(.*?)</Message>.*?</Error>@s', $response->getBody(), $matches)) {
			$this->_error_code = $matches[1];
			$this->_error_message = $matches[2];
			return false;
		} else {
			return true;
		}
	}


	/**
	 * Validate a queue name against rules provided by Amazon.
	 *
	 * Constraints: Maximum 80 characters; alphanumeric characters, hyphens (-),
	 * and underscores (_) are allowed.
	 *
	 * @param string Queue name
	 * @return bool
	 */
	protected function _isValidQueueName($name)
	{
		if (preg_match('/^[A-Za-z0-9\-\_]{1,80}$/', $name)) {
			return true;
		} else {
			$this->_error_message = "Invalid queue name provided. Queue names must contain "
									."1-80 alphanumeric characters, dashes, or underscores.";
			return false;
		}
	}


	/**
	 * Validate visibility timeout.
	 *
	 * Constraints: 0-7200 seconds.
	 *
	 * @param integer Seconds
	 * @return bool
	 */
	protected function _isValidVisibilityTimeout($timeout)
	{
		if ($timeout >=0 && $timeout <= 7200) {
			return true;
		} else {
			$this->_error_message = "Timeout specified falls outside the allowable range (0-7200).";
			return false;
		}
	}


	/**
	 * Validate the name and value of an attribute.
	 *
	 * @param string Name of attribute
	 * @param mixed Value of attribute
	 * @return bool Valid attribute
	 */
	protected function _isValidAttribute($name, $value)
	{
		if ($name == 'VisibilityTimeout') {
			return $this->_isValidVisibilityTimeout($value);
		} else {
			$this->_error_message = "Invalid attribute name provided.";
			return false;
		}
	}

}

?>