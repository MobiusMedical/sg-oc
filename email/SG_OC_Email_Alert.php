<?php

require_once dirname(__FILE__).'/EmailHandler.php';
require_once dirname(__FILE__).'/../config/config.php';

/**
 * This class contain methods, which define templates used for sending email alert 
 * 
 * @author nitin
 *
 */
class SG_OC_Email_Alert{
	
	private $email_handler = null;
	private $app_config = null;
	private $logger = null;
	
	
	const EMAIL_FOOTER_NOTE = '<br/><footer style = "color:blue"><small>Note: This is auto generated email don\'t reply to this email.<small></footer>';  
	
	public function __construct(){
		$this->email_handler = EmailHandler::get_instance();
		$this->app_config = Config::get_instance()->get_configuration();
		$this->logger = $this->app_config['logger'];
	}
	
	/**
	 * This function uses basic template and static configured email alert list for sending email alerts 
	 * 
	 * @param $msg
	 */
	public function send_email_alert($msg){
		$email = array(
				'Subject' => 'SG-OC connector alert',
				'To_recepients' => $this->app_config ['alert_email_list']
		);
		
		$body = 'Hi, <br/><p>&nbsp;&nbsp;There was an error in SG-OC connector, details are as follows.<br/><br/><span style ="color:red" ><ul><li style="color:red;">'
				. $msg . '</li></ul></span></p>';
		
		$email ['Body'] = $body . self::EMAIL_FOOTER_NOTE;
		return $this->email_handler->build_and_send_email($email);
	}
	
	public function send_OC_push_err_alert($msg){
		$email = array(
				'Subject' => 'SG-OC connector alert',
				'To_recepients' => $this->app_config ['alert_email_list']
		);
	
		$body = 'Hi, <br/><p>&nbsp;&nbsp;There was an error in SG-OC connector, details are as follows.<br/><br/></p><p>'
				. $msg . '</p>';
	
		$email ['Body'] = $body . self::EMAIL_FOOTER_NOTE;
		return $this->email_handler->build_and_send_email($email);
	}
}