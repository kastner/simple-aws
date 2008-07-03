<?php

class Services_Amazon_Account {

	private static $instance;

	private $_aws_access_key = '';
	private $_aws_secret_key = '';
	private $_aws_account_id = '';

	private $_aws_ini_file = '/etc/amazon/aws.ini';


	private function __construct() {
		if (!is_readable($this->_aws_ini_file)) {
			throw new Exception("Cannot open AWS INI file at {$this->_aws_ini_file}.");
		}
		$settings = parse_ini_file($this->_aws_ini_file);
		$this->_aws_access_key = $settings['aws_access_key'];
		$this->_aws_secret_key = $settings['aws_secret_key'];
		$this->_aws_account_id = $settings['aws_account_id'];
	}


	static public function getInstance() {
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}
		return self::$instance;
	}


	public function getAccessKey() {
		return $this->_aws_access_key;
	}


	public function getSecretKey() {
		return $this->_aws_secret_key;
	}


	public function getAccountID() {
		return $this->_aws_account_id;
	}

}

?>