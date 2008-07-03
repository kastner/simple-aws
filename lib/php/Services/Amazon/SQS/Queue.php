<?php

/**
 * Interface for sending and receiving messages in Simple Queue Service (SQS) queues.
 */
class Services_Amazon_SQS_Queue extends Services_Amazon_SQS_Client {


	private $_queue_url;


	/**
	 * Initialize a queue object.
	 *
	 * @param string Queue URL
	 */
	public function __construct($queue_url)
	{
		$this->_queue_url = $queue_url;
		parent::__construct();
	}


	/**
	 * Push a message onto the end of the queue.
	 *
	 * @param string Message
	 * @return mixed Message ID or boolean false if message was not accepted.
	 */
	public function push($message)
	{
		if (!$this->_isValidMessage($message)) {
			return false;
		}

		$params = array();
		$params['Action'] = 'SendMessage';
		$params['MessageBody'] = $message;

		$response = $this->_sendRequest($params, $this->_queue_url);
		return $this->_isOk($response);
	}


	/**
	 * Pop a message from the front of the queue.
	 *
	 * @param integer Number of messages to pop from the queue (defaults to 1)
	 * @param integer Number of seconds that received messages should be hidden from
	 * view in the queue.  This parameter is optional, and by default the queue service
	 * will use the timeout specified on the queue.
	 * @return mixed Message (string) or boolean false if request fails
	 */
	public function pop($count=1, $timeout=null)
	{
		if ($timeout != null && !$this->_isValidVisibilityTimeout($timeout)) {
			return false;
		}

		// Normalize count if it's outside of Amazon's constraints.
		if ($count < 1) {
			$count = 1;
		} else if ($count > 10) {
			$count = 10;
		}

		$params = array();
		$params['Action'] = 'ReceiveMessage';
		$params['MaxNumberOfMessages'] = $count;
		if ($timeout) {
			$params['VisibilityTimeout'] = $timeout;
		}

		$response = $this->_sendRequest($params, $this->_queue_url);

		if (!$this->_isOk($response)) {
			return false;
		}

		$messages = array();
		$matches = null;
		$node_match = null;

		preg_match_all("@<Message>(.*?)</Message>@", $response->getBody(), $matches);

		foreach ($matches[1] as $message_node) {
			$item = array();
			preg_match("@<ReceiptHandle>(.*?)</ReceiptHandle>@", $message_node, $node_match);
			$item['handle'] = $node_match[1];
			preg_match("@<Body>(.*?)</Body>@", $message_node, $node_match);
			$item['message'] = $node_match[1];
			$messages[] = $item;
		}

		return $messages;
	}


	/**
	 * Delete a message from the queue.
	 *
	 * @param string Message handle
	 * @return bool Success
	 */
	public function delete($handle)
	{
		$params = array();
		$params['Action'] = 'DeleteMessage';
		$params['ReceiptHandle'] = $handle;

		$response = $this->_sendRequest($params, $this->_queue_url);
		return $this->_isOk($response);
	}


	/**
	 * Ensure that message body fits basic constraints.
	 *
	 * @param string Message body
	 * @return bool OK
	 */
	private function _isValidMessage($message)
	{
		if (strlen($message) > 8000) {
			$this->_error_message = "Message exceeds maximum length of 8KB.";
			return false;
		} else {
			return true;
		}
	}
}

?>