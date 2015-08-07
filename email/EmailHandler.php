<?php
require_once dirname(__FILE__).'/../lib/PHPMailer/PHPMailerAutoload.php';
require_once dirname(__FILE__).'/../config/config.php';

class EmailHandler {
	private $mail = null;
	private $app_config = null;
	private $logger = null;
	private static $instance;
	
	protected function __construct(){
		$this->mail = new PHPMailer (true);
		$this->app_config = Config::get_instance()->get_configuration();
		$this->logger = $this->app_config['logger'];
		$this->config();
	}
	
	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 * 
	 * @return void
	 */
	private function __clone(){
	}
	
	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	private function __wakeup(){
	}
	
	/**
	 * This function return singleton instance of this class
	 */
	public static function get_instance() {
		if (static::$instance === NULL) {
			static::$instance = new static ();
		}
		return static::$instance;
	}
	
	/**
	 * This function configures PHPMailer
	 */
	private function config() {
		$this->logger->info('Configuring mail handler');
		$this->mail->isSMTP();
		$this->mail->SMTPDebug = 2; //enable SMTP Debug
		$this->mail->Host = $this->app_config['email_SMTP_server'];
		$this->mail->SMTPAuth = $this->app_config['email_SMTP_server_auth'];
		$this->mail->Username = $this->app_config['email_SMTP_username'];
		$this->mail->Password = $this->app_config['email_SMTP_userpassword'];
		$this->mail->Timeout = $this->app_config['email_SMTP_server_timeout'];
		
		if($this->app_config ['email_SMTP_Security'] !== '')
			$this->mail->SMTPSecure = $this->app_config['email_SMTP_Security'];
		
		$this->mail->Port = $this->app_config['email_SMTP_port'];
		$this->mail->isHTML(true);
		$this->mail->From = $this->app_config['email_SMTP_username'];
		$this->mail->FromName = $this->app_config['email_FromName'];
	}
	
	/**
	 * This function used to send email
	 *
	 * Note: InputParam should be inputed as
	 * $email_param = array(
	 * 'From' => ?,
	 * 'Subject' => ?,
	 * 'Body' => ?,
	 * 'AltBody' => ?,
	 * 'To_recepients' => array(),
	 * 'CC_recepients' => array(),
	 * 'BCC_recepients'=> array()
	 * )
	 * 
	 * @param $email_param
	 * @return true if email sending success otherwise false
	 */
	public function build_and_send_email($email_param = array()) {
		if(array_key_exists('From', $email_param )) {
			$this->mail->From = $email_param['From'];
			$this->mail->FromName = '';
		}
		
		if(array_key_exists ( 'FromName', $email_param )) {
			$this->mail->FromName = $email_param['FromName'];
		}
		
		if(array_key_exists('Subject', $email_param)) {
			$this->mail->Subject = $email_param['Subject'];
		}else{
			$this->logger->debug('Email has no subject mentioned.');
		}
		
		if(array_key_exists('Body', $email_param)) {
			$this->mail->Body = $email_param['Body'];
		}else{
			$this->logger->warn('Email has no body set.');
		}
		
		if(array_key_exists('AltBody', $email_param)) {
			$this->mail->AltBody = $email_param['AltBody'];
		}
		
		if(array_key_exists ('To_recepients', $email_param)){
			if(empty($email_param['To_recepients'])){
				$this->logger->warn('It is unable to send email without any recepients');
				return false;
			}
			
			foreach($email_param['To_recepients'] as $to_address){
				$this->mail->addAddress($to_address);
			}
		}
		
		if(array_key_exists('CC_recepients', $email_param)){
			foreach ($email_param['CC_recepients'] as $cc_address){
				$this->mail->addCC ( $cc_address );
			}
		}
		
		if(array_key_exists('BCC_recepients', $email_param)){
			foreach($email_param ['BCC_recepients'] as $bcc_address){
				$this->mail->addBCC($bcc_address);
			}
		}
		try {
			if(! $this->mail->send()){
				$this->logger->error('Email could not be sent. Mailer Error: ' . $this->mail->ErrorInfo);
        		return false;
      		} else {
      			$to_email_list = '';
      			foreach($email_param['To_recepients']  as $to_email){
      				$to_email_list .= $to_email_list === ''?'':', ';
      				$to_email_list .= $to_email;
      			}
      			  
        		$this->logger->info('Alert email has been sent to '.$to_email_list);
        		return true;
      		}
    	} catch (phpmailerException $e){
        	$this->logger->error($e->errorMessage());
        	return false; 
      	} catch (Exception $e) {
        	$this->logger->error($e->getMessage());
        	return false;
      	}
  }
}
