<?php
require_once dirname(__FILE__).'/config/config.php';
require_once dirname(__FILE__).'/config/constants.php';
require_once dirname(__FILE__).'/email/SG_OC_Email_Alert.php';

class SG_OC_DAO {
	private $conn = null;
	private $config = null;
	private $logger = null;
	private $alert_sender = null;
	
	public function __construct(){
		$this->config = Config::get_instance()->get_configuration();
		$this->logger = $this->config['logger'];
		$this->alert_sender = new SG_OC_Email_Alert();
		$this->create_DB_Connection ();
	}
	
	/**
	 * Create database connection
	 */
	public function create_DB_Connection() {
		$host = 'host='.$this->config['db_servername'];
		$port = 'port='.$this->config['db_port'];
		$dbname = 'dbname='.$this->config['db_name'];
		$credentials = 'user='.$this->config['db_username'].' password='.$this->config ['db_password'];
	
		$this->conn = @pg_connect("$host $port $dbname $credentials");
	
		if(!$this->conn){
			$this->logger->error('Unable to open database connection.');
			$this->alert_sender->send_email_alert('Unable to open database connection.');
			die();
		} else {
			$this->logger->info('Successfully connected to Database: '.$this->config ['db_name']);
		}
	}
	
	/**
	 * This function save current pull timestamp of a given survey
	 * 
	 * @param $survey_id
	 * @param $pull_timestamp
	 */
	public function save_pull_ts($survey_id, $pull_timestamp){
		$this->logger->trace('Saving current pull timestamp '.$pull_timestamp);
		$update_query = 'UPDATE '.TB_SG_PULL . ' SET last_pull_ts = \''.$pull_timestamp.'\' WHERE survey_id = '.$survey_id;
		$ret = pg_query($update_query);
	
		if(!$ret){
			$this->logger->error('Error occurred while updating sg_pull timestamp.'.pg_last_error($this->conn));
			$this->alert_sender->send_email_alert('Error occurred while updating sg_pull timestamp.'.pg_last_error($this->conn));
			die();
		}else{
			if(pg_affected_rows($ret) === 1){
				$this->logger->info('Updated sg_pull timestamp to :'.$pull_timestamp.' for survey (ID:'.$survey_id.')');
				return;
			}
		}
	
		$sql_query = 'INSERT INTO ' . TB_SG_PULL . ' (survey_id, last_pull_ts)'
				.' VALUES ('.$survey_id.', \''. $pull_timestamp.'\')';
	
		$this->logger->debug('Executing DB query: '.$sql_query);
	
		$ret = pg_query($this->conn, $sql_query);
	
		if(!$ret){
			$this->logger->error('Error occurred while saving sg_pull timestamp. '.pg_last_error($this->conn));
			$this->alert_sender->send_email_alert('Error occurred while saving sg_pull timestamp. '.pg_last_error($this->conn));
			die();
		}else{
			$this->logger->info('Inserted sg_pull timestamp:'.$pull_timestamp.' for survey (ID:'.$survey_id.')');
		}
	}
	
	
	/**
	 * This function return last pull timestamp of given survey
	 * 
	 * @return last_pull_timestamp and null if no entry in table TB_SG_PULL found
	 */
	public function get_last_pull_ts($survey_ID){
		$sql_query = 'SELECT last_pull_ts FROM '.TB_SG_PULL .' WHERE survey_id ='.$survey_ID;
		$ret = pg_query($this->conn, $sql_query);
	
		if(!$ret){
			$this->logger->error('Error occurred while reading sg_pull timestamp. Error: '.pg_last_error($this->conn));
			$this->alert_sender->send_email_alert('Error occurred while reading sg_pull timestamp. Error: '.pg_last_error($this->conn));
			die();
		}
	
		$num_rows = pg_num_rows($ret);
		if($num_rows !== 1)
			return null;
	
		$row = pg_fetch_assoc($ret);
		$timestamp = date('Y-m-d+H:i:s', strtotime($row['last_pull_ts']));
		$this->logger->info('Last sg_pull_ts for survey(ID:' .$survey_ID .') was ' . $timestamp);
		return $timestamp;
	}
}
