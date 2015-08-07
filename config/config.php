<?php
require_once dirname ( __FILE__ ) .'/../lib/apache-log4php-2.3.0/src/main/php/Logger.php';
class Config {
	
	/**
	 * singleton instance of this class
	 */
	private static $instance;
	
	private static $logger = null;
	
	/**
	 * Configuration parameter
	 */
	private $param = array (
			'CONFIG_XML_FILES_PATH' => './config/xml',
			'db_servername' => 'localhost',
			'db_name' => 'sgoc',
			'db_port' => 5432,
			'db_username' => 'sgoc',
			'db_password' => 'goodpw',
			'email_SMTP_server_auth' => false,
			'email_SMTP_server' => 'localhost',
			'email_SMTP_username' => 'name@email.com',
			'email_SMTP_userpassword' => '',
			'email_SMTP_server_timeout' => 10, 
			'email_SMTP_port' => 25,
			'email_SMTP_Security' => '',
			'email_FromName' => 'Sysadmin@email.com',
			
			// Add your alert email addresses to following array
			'alert_email_list' => array (
					'user@email.com.'
			) 
	);
	
	/**
	 * Configure logger
	 */
	private function get_logger() {
		if (static::$logger === null) {
			Logger::configure ( dirname ( __FILE__ ) . '/log4php.xml' );
			static::$logger = Logger::getLogger ( 'SG_OC' );
		}
		return static::$logger;
	}
	protected function __construct() {
	}
	
	/**
	 * Return array of configuartion parameters
	 */
	public function get_configuration() {
		$this->param ['logger'] = $this->get_logger ();
		return $this->param;
	}
	public static function get_instance() {
		if (static::$instance === NULL) {
			static::$instance = new static ();
		}
		return static::$instance;
	}
	
	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 * 
	 * @return void
	 */
	private function __clone() {
	}
	
	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	private function __wakeup() {
	}
}
