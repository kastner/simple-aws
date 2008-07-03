<?php

/**
 * NOTICE: Deleting queues, which happens in a few places in these unit tests,
 * must be followed by a timeout to make sure that successive list requests do
 * not return the banished queues.  This might make some tests extremely slow
 * during timeouts.
 */

require_once '../../../test_helper.php';

ini_set("display_errors", true);
ini_set("error_reporting", E_ALL);

class SQSTest extends PHPUnit_Framework_TestCase {

	static $test_queues = array("unittest-test-queue-one",
								"unittest-test-queue-two");

	static $test_messages = array("Sample message right here, folks!");

	/** Deletes can take up to 60 seconds, use this timeout after deletes during test. */
	static $service_timeout = 65;


	public function testSetupTestQueues() {
		$rand = rand(1, 1000);
		foreach (self::$test_queues as &$name) {
			$name = "$name-$rand";
		}
	}

	public function testDeleteExistingQueues() {
		$manager = new Services_Amazon_SQS_QueueManager();

		$list = $manager->listQueues('unittest-');
		$this->assertEquals(true, is_array($list));

		foreach ($list as $queue) {
			$manager->deleteQueue($queue);
		}
		if (count($list) > 0) {
			sleep(self::$service_timeout);
		}
	}

	public function testListQueues() {
		$manager = new Services_Amazon_SQS_QueueManager();

		$list = $manager->listQueues();
		$this->assertEquals(true, is_array($list));

		$list = $manager->listQueues('unittest-');
		$this->assertEquals(true, is_array($list));
		$this->assertEquals(0, count($list));
	}

	public function testCreateQueue() {
		$manager = new Services_Amazon_SQS_QueueManager();

		$success = $manager->createQueue(self::$test_queues[0]);
		$this->assertEquals(true, $success);

		$success = $manager->createQueue(self::$test_queues[1]);
		$this->assertEquals(true, $success);

		sleep(self::$service_timeout);

		$list = $manager->listQueues('unittest-');

		$this->assertEquals(true, is_array($list));
		$this->assertEquals(2, count($list));
	}

	public function testSendAndReceiveMessages() {
		$manager = new Services_Amazon_SQS_QueueManager();

		// Get available queue URLs.
		$list = $manager->listQueues('unittest-');

		// Push a bunch of messages into the first queue.
		$queue = new Services_Amazon_SQS_Queue($list[0]);

		$result = $queue->push(self::$test_messages[0]);
		$this->assertEquals(true, $result);

		$timestamp = date("H:i:s");
		for ($i = 0; $i < count(10); $i++) {
			$message = "Message:$timestamp:" . rand(1,1000);
			$result = $queue->push($message);
			$this->assertEquals(true, $result);
		}

		sleep(3);

		// Pop a selection of messages (shorten timeout).
		$messages = $queue->pop(10, 5);
		$this->assertEquals(true, is_array($messages));
		$this->assertGreaterThan(0, count($messages));
		$this->assertEquals(true, is_array($messages[0]));
		$this->assertArrayHasKey("message", $messages[0]);
		$this->assertArrayHasKey("handle", $messages[0]);

		sleep(10);

		// Pop a single message.
		$messages = $queue->pop();
		$this->assertEquals(true, is_array($messages));
		$this->assertEquals(1, count($messages));

		// Delete one message.
		$response = $queue->delete($messages[0]['handle']);
		$this->assertEquals(true, $response);

		// Delete a fake message.
		$response = $queue->delete("invalidresponsehandle");
		$this->assertEquals(false, $response);

		// Check the second queue for messages.
		$queue = new Services_Amazon_SQS_Queue($list[1]);
		$messages = $queue->pop();
		$this->assertEquals(0, count($messages));

	}


	public function testGetAndSetAttributes() {
		$manager = new Services_Amazon_SQS_QueueManager();

		$queues = $manager->listQueues('unittest-');

		$attrs = $manager->getQueueAttributes($queues[0]);
		$this->assertEquals(true, is_array($attrs));
		$this->assertArrayHasKey('VisibilityTimeout', $attrs);
		$this->assertRegExp('/^\d+$/', $attrs['VisibilityTimeout']);
		$this->assertEquals(30, $attrs['VisibilityTimeout']);
		$this->assertArrayHasKey('ApproximateNumberOfMessages', $attrs);
		$this->assertRegExp('/^\d+$/', $attrs['ApproximateNumberOfMessages']);

		$response = $manager->setQueueAttribute($queues[0], 'InvalidAttributeName', 1);
		$this->assertEquals(false, $response);

		$response = $manager->setQueueAttribute($queues[0], 'VisibilityTimeout', 10000);
		$this->assertEquals(false, $response);

		$response = $manager->setQueueAttribute($queues[0], 'VisibilityTimeout', 200);
		$this->assertEquals(true, $response);

		sleep(self::$service_timeout);

		$attrs = $manager->getQueueAttributes($queues[0]);
		$this->assertArrayHasKey('VisibilityTimeout', $attrs);
		$this->assertRegExp('/^\d+$/', $attrs['VisibilityTimeout']);
		$this->assertEquals(200, $attrs['VisibilityTimeout']);
	}

	public function testDeleteQueue() {
		$manager = new Services_Amazon_SQS_QueueManager();

		$list = $manager->listQueues('unittest-');
		$this->assertEquals(true, is_array($list));
		$this->assertEquals(2, count($list));

		$manager->deleteQueue($list[0]);
		$manager->deleteQueue($list[1]);

		sleep(self::$service_timeout);

		$list = $manager->listQueues('unittest-');
		$this->assertEquals(true, is_array($list));
		$this->assertEquals(0, count($list));
	}

}

?>