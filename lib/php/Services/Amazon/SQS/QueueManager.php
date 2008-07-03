<?php

/**
 * Interface for creating, managing, and deleting Simple Queue Service (SQS) queues.
 */
class Services_Amazon_SQS_QueueManager extends Services_Amazon_SQS_Client {

	/**
	 * Get a list of queues.
	 *
	 * @return array Queues
	 */
	public function listQueues($prefix=null)
	{
		$params = array();
		$params['Action'] = 'ListQueues';
		if ($prefix) {
			$params['QueueNamePrefix'] = $prefix;
		}

		$response = $this->_sendRequest($params);

		$matches = null;
		preg_match_all("@<QueueUrl>(.*?)</QueueUrl>@", $response->getBody(), $matches);
		return $matches[1];
	}


	/**
	 * Create a new queue.
	 *
	 * @param string Queue name
	 * @param integer Timeout for message visibility
	 * @return bool Success
	 */
	public function createQueue($queue_name, $timeout=null)
	{
		if (!$this->_isValidQueueName($queue_name)) {
			$this->_error_message = "Invalid queue name provided: $queue_name";
			return false;
		}
		if ($timeout !== null && !$this->_isValidVisibilityTimeout($timeout)) {
			$this->_error_message = "Invalid queue visibility timeout provided: $timeout";
			return false;
		}

		$params = array();
		$params['Action']    = 'CreateQueue';
		$params['QueueName'] = $queue_name;
		$params['DefaultVisibilityTimeout'] = ($timeout !== null) ? $timeout : 30;

		$response = $this->_sendRequest($params);
		return $this->_isOk($response);
	}


	/**
	 * Delete a queue.  All existing messages in the queue will be lost.
	 *
	 * @param string Queue URL
	 * @return bool Success
	 */
	public function deleteQueue($queue_url)
	{
		$params = array();
		$params['Action'] = 'DeleteQueue';

		$response = $this->_sendRequest($params, $queue_url);
		return $this->_isOk($response);
	}


	/**
	 * Get associative array of one or more attributes.
	 *
	 * Allowed "attribute" names: All | ApproximateNumberOfMessages | VisibilityTimeout
	 *
	 * @param string Queue URL
	 * @param string Name of attribute to get, or "All" to get all attributes.
	 * @return array Associative array of available attributes
	 */
	public function getQueueAttributes($queue_url, $attribute=null)
	{
		$attributes = array();

		$params = array();
		$params['Action'] = "GetQueueAttributes";
		$params['AttributeName'] = ($attribute != null) ? $attribute : 'All';

		$response = $this->_sendRequest($params, $queue_url);

		$matches = null;
		preg_match_all("@<Attribute><Name>(.*?)</Name><Value>(.*?)</Value></Attribute>@", $response->getBody(), $matches);
		for ($i = 0; $i < count($matches[1]); $i++) {
			$attributes[$matches[1][$i]] = $matches[2][$i];
		}

		return $attributes;
	}


	/**
	 * Set a queue attribute.
	 *
	 * @param string Queue URL
	 * @param string Attribute name
	 * @param mixed Attribute value
	 * @return bool Success
	 */
	public function setQueueAttribute($queue_url, $attribute, $value)
	{
		if (!$this->_isValidAttribute($attribute, $value)) {
			return false;
		}

		$params = array();
		$params['Action'] = 'SetQueueAttributes';
		$params['Attribute.Name'] = $attribute;
		$params['Attribute.Value'] = $value;

		$response = $this->_sendRequest($params, $queue_url);
		return $this->_isOk($response);
	}

}

?>