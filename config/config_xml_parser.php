<?php
require_once dirname(__FILE__).'/XML_constants.php';
require_once dirname(__FILE__).'/config_model.php';
require_once dirname(__FILE__).'/config.php';
require_once dirname(__FILE__).'/../email/SG_OC_Email_Alert.php';

class ConfigXMLParser{
	private $logger = null;
	private $app_config = null;
	private $alert_sender = null;
	
	public function __construct(){
		$this->app_config = Config::get_instance()->get_configuration();
		$this->logger = $this->app_config['logger'];
		$this->alert_sender = new SG_OC_Email_Alert();
	}

	/**
	 * This function parses XML config file and returns object of @link ClinicalData
	 * 
	 * @param $xml_file
	 * @return ClinicalData or false on parse error
	 */
	public function parse($xml_file){
		$xml = simplexml_load_file($xml_file);
		$clinical_data = new ClinicalData();
		$comman_msg = 'For XML config file:'.$xml_file;
		
		$basic_info = (array) $xml->ClinicalData->attributes();
		$basic_info = $basic_info['@attributes'];

		if(!array_key_exists(XMLConst::StudyOID, $basic_info) || $basic_info[XMLConst::StudyOID] == ''){
			$this->logger->error(XMLConst::StudyOID.' not defined in ClinicalData Tag');
			$this->alert_sender->send_email_alert(XMLConst::StudyOID.' not defined in ClinicalData Tag. '.$comman_msg);
			return false;
		}
		$clinical_data->set_study_OID($basic_info[XMLConst::StudyOID]);
		
		if(!array_key_exists(XMLConst::OCURI, $basic_info) || $basic_info[XMLConst::OCURI] == ''){
			$this->logger->error(XMLConst::OCURI.' not defined in ClinicalData Tag');
			$this->alert_sender->send_email_alert(XMLConst::OCURI .' not defined in ClinicalData Tag. '.$comman_msg);
			return false;
		}
		$clinical_data->set_OC_URI($basic_info[XMLConst::OCURI]);
		
		if(!array_key_exists(XMLConst::OCUser, $basic_info) || $basic_info[XMLConst::OCUser] == ''){
			$this->logger->error(XMLConst::OCUser.' not defined in ClinicalData Tag');
			$this->alert_sender->send_email_alert(XMLConst::OCUser.' not defined in ClinicalData Tag. '.$comman_msg);
			return false;
		}
		$clinical_data->set_OC_username($basic_info[XMLConst::OCUser]);
		
		if(!array_key_exists(XMLConst::OCPass, $basic_info) || $basic_info[XMLConst::OCPass] == ''){
			$this->logger->error(XMLConst::OCPass.' not defined in ClinicalData Tag');
			$this->alert_sender->send_email_alert(XMLConst::OCPass.' not defined in ClinicalData Tag. '.$comman_msg);
			return false;
		}
		$clinical_data->set_OC_password($basic_info[XMLConst::OCPass]);
		
		if(!array_key_exists(XMLConst::SGURI, $basic_info) || $basic_info[XMLConst::SGURI] == ''){
			$this->logger->error(XMLConst::SGURI.' not defined in ClinicalData Tag');
			$this->alert_sender->send_email_alert(XMLConst::SGURI.' not defined in ClinicalData Tag. '.$comman_msg);
			return false;
		}
		$clinical_data->set_SG_URI($basic_info[XMLConst::SGURI]);
		
		if(!array_key_exists(XMLConst::SGUser, $basic_info) || $basic_info[XMLConst::SGUser] == ''){
			$this->logger->error(XMLConst::SGUser.' not defined in ClinicalData Tag');
			$this->alert_sender->send_email_alert(XMLConst::SGUser.' not defined in ClinicalData Tag. '.$comman_msg);
			return false;
		}
		$clinical_data->set_SG_username($basic_info[XMLConst::SGUser]);
		
		if(!array_key_exists(XMLConst::SGPass, $basic_info) || $basic_info[XMLConst::SGPass] == ''){
			$this->logger->error(XMLConst::SGPass.' not defined in ClinicalData Tag');
			$this->alert_sender->send_email_alert(XMLConst::SGPass.' not defined in ClinicalData Tag '.$comman_msg);
			return false;
		}
		$clinical_data->set_SG_password($basic_info[XMLConst::SGPass]);
		
		if(!array_key_exists(XMLConst::SurveyID, $basic_info) || $basic_info[XMLConst::SurveyID] == ''){
			$this->logger->error(XMLConst::SurveyID.' not defined in ClinicalData Tag');
			$this->alert_sender->send_email_alert(XMLConst::SurveyID.' not defined in ClinicalData Tag. '.$comman_msg);
			return false;
		}
		$clinical_data->set_survey_ID($basic_info[XMLConst::SurveyID]);
		
		$study_event_data_list = array();
		
		foreach ($xml->ClinicalData->StudyEventData as $SE_event){
			$study_event_data = new StudyEventData();
			
			$eventList = array();
			
			if(! empty($SE_event->StudyEvents)) {
				foreach ($SE_event->StudyEvents->StudyEvent as $study_event){
					$study_event_ele_attr = (array) $study_event->attributes();
					$study_event_ele_attr = $study_event_ele_attr['@attributes'];
					
					$eventList = array_merge($eventList, array(
							$study_event_ele_attr[XMLConst::EventName] => $study_event_ele_attr[XMLConst::EventOID] 
					)); 
				}
			} 
			
			if(empty($eventList)){
				$this->logger->error('Expected at least one event defined in XML configuration');
				$this->alert_sender->send_email_alert('Expected at least one event defined in XML configuration. '.$comman_msg);
				return false;
			}
			
			$study_event_data->set_study_events($eventList);
			
			$form_element_list = $SE_event->FormData;
			
			$form_list = array();
			foreach ($form_element_list as $form_element){
				$form = new FormData();
				
				$form_ele_attr = (array) $form_element->attributes();
				$form_ele_attr = $form_ele_attr['@attributes'];
				
				$form->set_form_OID($form_ele_attr[XMLConst::FormOID]);
				
				
				$item_groups = array(); //set of ItemGroupData
					
				foreach($form_element->ItemGroupData as $item_group_ele){
					$item_group = new ItemGroupData();
						
					$item_group_ele_attr = (array) $item_group_ele->attributes();
					$item_group_ele_attr = $item_group_ele_attr['@attributes'];
					
					$item_group->set_item_group_OID($item_group_ele_attr[XMLConst::ItemGroupOID]);
					$item_group->set_item_group_repeat_key($item_group_ele_attr[XMLConst::ItemGroupRepeatKey]);
					$item_group->set_transaction_type($item_group_ele_attr[XMLConst::TransactionType]);
				
					$items = array(); //set of Item	
					foreach ($item_group_ele->ItemData as $item_ele){
						$item = new ItemData();
				
						$item_ele_attr = (array) $item_ele->attributes();
						$item_ele_attr = $item_ele_attr['@attributes'];
						
						$item->set_OC_item_OID($item_ele_attr[XMLConst::OCItemOID]);
						$item->set_SG_question_ID($item_ele_attr[XMLConst::SGQuestionID]);
						$item->set_SG_option_ID($item_ele_attr[XMLConst::SGOptionID]);
						$item->set_data_type($item_ele_attr[XMLConst::DataType]);
						
						if(array_key_exists(XMLConst::SG_DATE_Format, $item_ele_attr)){
							$item->set_date_format($item_ele_attr[XMLConst::SG_DATE_Format]);
						}
						
						array_push($items, $item);
					}//item for
						
					$item_group->set_items($items);
					array_push($item_groups, $item_group);
				}//itemgroup for
				
				$form->set_item_groups($item_groups);
				array_push($form_list, $form);
			}
			
			$study_event_data->set_form_list($form_list);
			array_push($study_event_data_list, $study_event_data);
		}
		
		$clinical_data->set_study_event_data_list($study_event_data_list);
		return $clinical_data;
	}
}

