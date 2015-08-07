<?php
require_once dirname(__FILE__).'/config/config_xml_parser.php';
require_once dirname(__FILE__).'/config/config_model.php'; 
require_once dirname(__FILE__).'/config/config.php';
require_once dirname(__FILE__).'/RestWSConsumer.php';

/**
 * This is bootstrap class of application
 * 
 * @author nitin
 *
 */
class APP{
	public $config = null;
	public $rest_ws_consumer = null;
	private $logger = null;
	
	public function __construct(){
		$this->config = Config::get_instance()->get_configuration();
		$this->logger = $this->config['logger']; 
		$this->rest_ws_consumer = new RestWSConsumer();
	}
	
	/**
	 * For provided survey XML definitions, pull survey responses from SG and push to OC.
	 */
	public function load_surveys_to_CRF(){
		$this->logger->info('===== SG-OC Connector invoked ====');
		
		if(file_exists($this->config['CONFIG_XML_FILES_PATH']) === false){
			$this->logger->error('Incorrect configuration. Please configure correct xml directory path in config/config.php');
			return;
		}
		
		$files = glob($this->config['CONFIG_XML_FILES_PATH'].'/*.xml');
		if(empty($files)){
			$this->logger->warn('XML config files are not found under directory: '.$this->config['CONFIG_XML_FILES_PATH']);
			return;
		}
		
		$xml_parser = new ConfigXMLParser();	
		foreach ($files as $xml_file){
			$this->logger->info('Parsing XML config file: '. $xml_file);
			$clinical_data = $xml_parser->parse($xml_file);
			
			if($clinical_data === false)
				continue;
			
			$this->logger->info('Parsing of survey XML file completed');
			$status = $this->rest_ws_consumer->post_data_to_CRF($clinical_data, $clinical_data->get_survey_ID());
			//$this->print_result($status, $clinical_data->get_survey_ID());
		}
			$this->logger->info('===== SG-OC Connector terminated ====');
	}
	
	private function print_result($status, $surveyID){
		$spaces = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		echo $spaces.'Survey ID: '.$surveyID; 
		echo '<br/>------------------------------------------------<br/>';
		echo ' SSID'.$spaces.$spaces.'|'.' Success'.$spaces.'| error 		<br/>';
		echo '------------------------------------------------<br/>';
		
		foreach ($status as $subjectId => $poststatus){
			echo $subjectId . '&nbsp;&nbsp;| '. ($poststatus['success']== true ? 'true' : 'false').$spaces.$spaces. '| '.$poststatus['error'] .'<br/>';
		}
		
		echo '<br/><br/>';
	}
}

$app = new APP();
$app->load_surveys_to_CRF();
