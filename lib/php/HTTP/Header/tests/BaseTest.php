<?php

require_once '../../../test_helper.php';

class BaseTest extends PHPUnit_Framework_TestCase {

	public function testTTL() {
		$ttl = HTTP_Header_Base::intervalToTTL("access plus 25 seconds");
		$this->assertEquals(25, $ttl);

		$ttl = HTTP_Header_Base::intervalToTTL("access plus 1 days");
		$this->assertEquals((60*60*24), $ttl);

		$ttl = HTTP_Header_Base::intervalToTTL("access plus 3 weeks");
		$this->assertEquals((60*60*24*7*3), $ttl);

		$ttl = HTTP_Header_Base::intervalToTTL("access plus 2 weeks 4 days 2 hours 1 minutes");
		$this->assertEquals((60*60*24*7*2) + (60*60*24*4) + (60*60*2) + (60*1), $ttl);
	}

}

?>