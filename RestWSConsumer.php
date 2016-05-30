<?php
require_once dirname(__FILE__).'/config/config.php';
require_once dirname(__FILE__).'/openclinica/ConsumeImportDataWS.php';
require_once dirname(__FILE__).'/config/config_model.php';
require_once dirname(__FILE__).'/UtilFunction.php';
require_once dirname(__FILE__).'/SG_OC_DAO.php';
require_once dirname(__FILE__).'/email/SG_OC_Email_Alert.php';

/**
 * This class mediates between SurveyGizmo and OpenClinica Web services
 *
 * @author nitin
 *        
 */
class RestWSConsumer {
	private $config;
	private $question_map;
	private $question_to_group_map;
	private $group_to_crf_map;
	private $form_to_itemgroup_map;
	private $study_event_map;
	private $responses;
	private $logger = null;
	private $sg_oc_DAO = null;
	private $alert_sender = null;
	
	CONST SURVEY_RESPONSE_URL_SEG = 'surveyresponse';
	
	public function __construct() {
		$this->config = Config::get_instance()->get_configuration();
		$this->logger = $this->config['logger'];
		$this->sg_oc_DAO = new SG_OC_DAO();
		$this->alert_sender = new SG_OC_Email_Alert();
	}
	
	/**
	 * This function used to update responses collected in responses attribute of this class.
	 * Each response in responses array uniquely identifies recent survey submitted by subject for given event.
	 * 
	 * @param unknown $original_resps        	
	 * @param unknown $response
	 * @return date_submitted field of latest response       	
	 */
	private function update_response_array(&$original_resps, $response) {
		$isNewToInsert = true;
		foreach ($original_resps as $i => $res){
			if (strcmp($res['subject_id'], $response ['subject_id'] ) == 0 && strcmp ( $res ['event_id'], $response ['event_id'] ) == 0 && strcmp ( $res ['event_num'], $response ['event_num']) == 0) {
				if (strtotime($res ['date_submitted'] ) < strtotime($response ['date_submitted'])) {
					unset($original_resps [$i]);
					$isNewToInsert = true;
				} else {
					$isNewToInsert = false;
				}
				break;
			}
		}
		
		if($isNewToInsert) {
			$original_resps[$response['resp_id']] = $response;
		}
		
		$last_response_submit_ts = $original_resps[$response ['resp_id']]['date_submitted'];
		return $last_response_submit_ts;
	}
	
	/**
	 * This function makes REST call to SG and collect recent responses in responses attribute of this class for a given survey  
	 * 
	 * @param $clinical_data
	 * @return true if responses are valid else false
	 */
	private function retrieve_survey_response($clinical_data) {
		$survey_ID = $clinical_data->get_survey_ID();
		$this->responses = array();
		$url = $this->get_SG_URL($clinical_data, $this->get_filters($survey_ID ));
		$this->logger->trace('Reading survey responses for survey (ID: ' . $survey_ID .'), using URI: '.$url );
		$json_resp = file_get_contents($url);
		$response = json_decode($json_resp);
		
		if ($response->result_ok === false) {
			$email_msg = 'Reading survey response failed for survey (ID: ' . $survey_ID . '). Error: ' . $response->message;
			$err_log = 'Reading survey response failed for survey (ID: ' . $survey_ID . '). Error : ' . $response->message . ', Error code: ' . $response->code;
			$this->logger->error($err_log);
			$this->alert_sender->send_email_alert($email_msg);
			if ($response->code === 105) {
				$this->logger->warn('check whether survey (ID: ' . $survey_ID.') exist');
			}
			return false;
		}

		if($response->total_count === '0'){
			$this->logger->info('No new responses found for survey (ID: ' .$survey_ID.')');
			return false;
		}
		
		$responses = array ();
		$last_response_submit_ts = null;
		foreach($response->data as $i => $response) {
			$answers = array();
			if ($response->status === 'Complete') {
				$temp_resp = array (
						'resp_id' => $response->responseID,
						'subject_id' => $response->{'[url("ssid")]'},
						'event_id' => $response->{'[url("eid")]'},
						'event_num' => $response->{'[url("enum")]'},
						'date_submitted' => $response->datesubmitted 
				);
				
				if ($temp_resp ['subject_id'] == '' || $temp_resp ['event_id'] == '' || $temp_resp ['event_num'] == '') {
					$this->logger->warn('Survey response (ID: ' . $temp_resp ['resp_id'] . ') seems to be anonymous. Ignoring this response.');
					continue;
				}

				
				if(!array_key_exists($temp_resp['event_id'], $this->study_event_map)){
					$log_msg = 'Study event OID mapping not defined for event name: '.$temp_resp['event_id'];
					$this->logger->error($log_msg);
					$email_msg = $log_msg.' This occurred in survey (ID:'. $survey_ID.') for response (ID:'.$temp_resp['resp_id'].').'
							.' Please check your XML config file.';
					$this->alert_sender->send_email_alert($email_msg);
					return false;
				}
				
				//Get event name to event OID mapping so can be used for OC push
				$temp_resp['event_id'] = $this->study_event_map[$temp_resp['event_id']];
				
				$response_prop = get_object_vars ( $response );
				foreach ( $response_prop as $prop_name => $prop_value ) {
					if ($prop_value === '')
						continue;
					
					if (strncmp($prop_name, '[question', 9 ) === 0) {
						$question_key = explode ( ",", $prop_name );
						$question_id = UtilFunction::getTextBetweenBrackets ( $question_key [0] );
						$option_id = 0;
						
						// pipes are used to represent repeating group questions where questions from a group represent a pipe
						$pipe_id = 0;
						if (count ( $question_key ) === 2) {
							$question_key_part2 = UtilFunction::getTextBetweenBrackets ( $question_key [1] );
							$question_key [1] = trim ( $question_key [1] );
							if (strncmp ( $question_key [1], 'option', 6 ) === 0) {
								$option_id = $question_key_part2;
							}	
						}
						if (strpos($option_id, 'other') !== false) {
							$question_id .= '(' . substr ( $option_id, 1, strpos ( $option_id, 'other' ) - 2 ) . ')';
						}
						
						if (array_key_exists($question_id, $answers)) {
							// question re-apeared, which means the user could have multiple choice answer
							if ($pipe_id === 0) {
								$answers[$question_id]['answer']['text_ans'] .= ', ' . $prop_value;
							} else {
								if (array_key_exists ( $pipe_id, $answers [$question_id] ['answer'] ['pipe'] ))
									$answers[$question_id]['answer']['pipe'][$pipe_id] .= ', ' . $prop_value;
								else
									$answers [$question_id]['answer']['pipe'][$pipe_id] = $prop_value;
							}
						} else {
							// 1st time answer to this question seen
							if (array_key_exists($question_id, $this->question_map)){
								$answers [$question_id] = array (
										'question_id' => $question_id,
										'answer' => array () 
								);
								
								if ($pipe_id === 0) {
									$answers[$question_id]['answer']['text_ans'] = $prop_value;
								} else {
									$answers[$question_id]['answer']['pipe'][$pipe_id] = $prop_value;
								}
							}
						}
					}
				}
				
				$temp_resp['answers'] = $answers;
				$last_response_submit_ts = $this->update_response_array($responses, $temp_resp);
			}
		}
		
		$this->responses = $responses;
		
		if($last_response_submit_ts !== null)
			$this->sg_oc_DAO->save_pull_ts($survey_ID, $last_response_submit_ts);
		
		return true;
	}

	/**
	 * This function return SG REST call url
	 * 
	 * @param $clinical_data
	 * @param $filter
	 * @return SG REST call url
	 */
	private function get_SG_URL($clinical_data, $filter = array()) {
		$user_auth_param = 'api_token=' . $clinical_data->get_SG_username () . '&api_token_secret=' . $clinical_data->get_SG_password ();
		$url = $clinical_data->get_SG_URI () . '/' . $clinical_data->get_survey_ID () . '/' . self::SURVEY_RESPONSE_URL_SEG . '?' . $user_auth_param;
		
		$filter_sub_url = '';
		
		foreach ( $filter as $i => $f ) {
			$filter_sub_url .= $filter_sub_url !== '' ? '&' : '';
			$filter_sub_url .= 'filter[field][' . $i . ']=' . $f ['field'] . '&filter[operator][' . $i . ']=' . $f ['operator'] . '&filter[value][' . $i . ']=' . $f ['value'];
		}
		
		if ($filter_sub_url !== '') {
			$url .= '&' . $filter_sub_url;
		}
		return $url;
	}
	
	/**
	 * This function return sub url containing filters applied on survey REST call
	 *  
	 * @param $survey_id
	 * @return filter Sub URL 
	 */
	private function get_filters($survey_id) {
		$filter = array (
				0 => array (
						'field' => 'status',
						'operator' => '=',
						'value' => 'Complete' 
				) 
		);
		
		$last_pull_ts = $this->sg_oc_DAO->get_last_pull_ts($survey_id);
		
		if ($last_pull_ts !== null) {
			array_push ( $filter, array (
					'field' => 'datesubmitted',
					'operator' => '>',
					'value' => $last_pull_ts 
			) );
		}
		
		return $filter;
	}
	
	/**
	 * This function is used to push responses into OC which are collected in responses attribute of this class.
	 * 
	 * @param $config_param
	 * @param $clinical_data        	
	 * @return 
	 */
	private function upload_on_OC($config_param, $clinical_data) {
		$OC_push_status = array ();
		$OC_push_errors = array();
		$survey_ID = $clinical_data->get_survey_ID();
		foreach ( $this->responses as $i => $response ) {
			$OC_data_importer = new ImportDataWSConsumer();
			
			if ($response ['subject_id'] == '' || $response ['event_id'] == '' || $response ['event_num'] == '') {
				$this->logger->warn ('Survey response (ID: ' . $response ['resp_id'] . ') seems to be anonymous. Ignoring this response.');
				continue;
			}
			
			$config_param['subjectKey'] = $response['subject_id'];
			$this->logger->info('Survey is submitted by study subject (ID: ' . $response ['subject_id'] . ') on event (StudyEventOID: ' . $response ['event_id'] . ', EventRepeatKey : ' . $response ['event_num'] . ')');
			
			$is_configured_correctly = $OC_data_importer->config($config_param);
			
			if($is_configured_correctly === false)
				continue;
				
			$is_ODM_filled = $this->fill_ODM_elements($response, $clinical_data, $OC_data_importer);
			
			if($is_ODM_filled == false){
				continue;
			}
			
			$push_status = $OC_data_importer->push_ODM_to_OC();	
			
			if($push_status === false){
				$err_msg = 'OC push failed unexpectedly, This happens usually due to incorrect configuration of OC credentials or OC URI or both. Plase check XML config.'; 
				$this->logger->error($err_msg);
				$this->alert_sender->send_email_alert($err_msg);
				return $OC_push_status;
			}
			
			$OC_push_status = array_merge($OC_push_status, array (
					$response ['subject_id'] => $push_status 
			) );
			
			$this->logger->info('Survey (ID:'.$survey_ID.') Response (ID:'.$response ['resp_id'].')  OC push status: '.($push_status['success']?'true':'false'));
			if($push_status['success'] === false){
				$this->logger->error('OC push error: '.$push_status['error']);
				array_push($OC_push_errors, array(
					'resp_id' => $response['resp_id'],
					'subject_id' => $response['subject_id'],
					'error' => $push_status['error'] 
				));
			}
		}
		
		$this->send_OC_push_error_alerts($OC_push_errors, $survey_ID);
		
		return $OC_push_status;
	}
	
	/**
	 * This function used to send email alerts for push errors returned by OC for a given survey
	 *  
	 * @param $OC_push_errors
	 * @param $survey_ID
	 */
	private function send_OC_push_error_alerts($OC_push_errors, $survey_ID){
		if(empty($OC_push_errors))
			return;
				
		$this->logger->info('Sending OC push error alerts');
		$email_msg =''; 
		$rows = '';
		$i=1;
		foreach ($OC_push_errors as $OC_push_err){
			$rows .= '<tr><td>'. $i++ .'</td><td>'.$OC_push_err['resp_id'].'</td><td>'.$OC_push_err['subject_id'].'</td><td>'.$OC_push_err['error'].'</td></tr>';
		}
		
		$email_msg .= '<table style = "color:red;" border="1"><caption>Survey ID:'.$survey_ID.'</caption><th>NO.</th><th>Response ID</th><th>Subject ID</th><th>Push Error</th>'.$rows;
		$email_msg .= '</table></br><br/>';
		$this->alert_sender->send_OC_push_err_alert($email_msg);
	}
	
	/**
	 * This function fills all ODM elements  
	 * 
	 * @param $response
	 * @param $clinical_data
	 * @param $OC_data_importer
	 * @return true if ODM created successfully or false if any error occurred
	 */
	private function fill_ODM_elements($response, $clinical_data, &$OC_data_importer) {
		$answers = $response['answers'];
		$studyEventOID = $response['event_id'];
		$studyEventRepeatKey = $response['event_num'];
		
		$OC_data_importer->add_study_event ( array (
				'studyEventOID' => $studyEventOID,
				'studyEventRepeatKey' => $studyEventRepeatKey 
		) );
		
		$form_item_groups = array ();
		foreach ( $answers as $question_ID => $ans ) {
			$ans_group_OID = $this->question_to_group_map[$question_ID]['groupOID'];
			$ans_form_OID = $this->question_to_group_map[$question_ID]['formOID'];
			
			if (array_key_exists($ans_form_OID, $form_item_groups) === false) {
				$form_item_groups[$ans_form_OID] = array ();
			}
			
			if (array_key_exists($ans_group_OID, $form_item_groups [$ans_form_OID]) === false) {
				$form_item_groups [$ans_form_OID][$ans_group_OID] = array (
						'OID' => $ans_group_OID,
						'itemData' => array () 
				);
			}
			
			if (array_key_exists('pipe', $ans ['answer'])) {
				foreach ($ans['answer']['pipe'] as $pipe_id => $q_ans) {
					if(array_key_exists ( $pipe_id, $form_item_groups [$ans_form_OID] [$ans_group_OID] ['itemData'] ) === false) {
						foreach($this->form_to_itemgroup_map [$ans_form_OID] [$ans_group_OID] as $item_OID ) {
							$form_item_groups [$ans_form_OID] [$ans_group_OID] ['itemData'] [$pipe_id] [$item_OID] = '';
						}
					}
					
					$ans_item_OID = $this->question_map [$question_ID] ['itemOID'];
					if (strcmp($this->question_map [$question_ID] ['dataType'], 'DATE' ) === 0) {
						$q_ans = UtilFunction::get_ISO_8606_format_date ( $q_ans,  $this->question_map [$question_ID] ['dateFormat']);
						if($q_ans === false){
							$err_msg = 'Unable to parse date: '.$q_ans . ' using format: '.$this->question_map [$question_ID]['dateFormat'];
							$this->logger->error($err_msg);
							$email_msg ='Error occurred while parsing date in survey(ID:'.$clinical_data->get_survey_ID().') for response(ID:'.$response['resp_id'].'). Error:'.$err_msg;
							$this->alert_sender->send_email_alert($email_msg);
							return false;
						}
					}
					$form_item_groups[$ans_form_OID][$ans_group_OID]['itemData'][$pipe_id][$ans_item_OID] = $q_ans;
				}
			} 

			else {
				$pipe_id = 0; // This item belongs to non repeating group, therefore default pipe 0
				
				if (array_key_exists($pipe_id, $form_item_groups [$ans_form_OID] [$ans_group_OID] ['itemData'] ) === false) {
					foreach ( $this->form_to_itemgroup_map [$ans_form_OID] [$ans_group_OID] as $item_OID ) {
						$form_item_groups [$ans_form_OID] [$ans_group_OID] ['itemData'] [$pipe_id] [$item_OID] = '';
					}
				}
				
				$ans_item_OID = $this->question_map [$question_ID] ['itemOID'];
				$q_ans = $ans ['answer'] ['text_ans'];
				if (strcmp($this->question_map [$question_ID] ['dataType'], 'DATE' ) === 0) {
					$q_ans = UtilFunction::get_ISO_8606_format_date ( $q_ans,  $this->question_map [$question_ID] ['dateFormat']);
					if($q_ans === false){
						$err_msg = 'Unable to parse date: '.$q_ans . ' using format: '.$this->question_map [$question_ID]['dateFormat'];
						$this->logger->error($err_msg);
						$email_msg ='Error occurred while parsing date in survey(ID:'.$clinical_data->get_survey_ID().') for response(ID:'.$response['resp_id'].'). Error:'.$err_msg;
						$this->alert_sender->send_email_alert($email_msg);
						return false;
					}
				}
				$form_item_groups[$ans_form_OID][$ans_group_OID]['itemData'][$pipe_id][$ans_item_OID] = $q_ans;
			}
		}
			
		$this->add_unanswered_items($form_item_groups);
			
		foreach($form_item_groups as $form_OID => $item_groups){
			$OC_data_importer->add_crf($studyEventOID, array (
					'formOID' => $form_OID,
					'itemGroups' => $item_groups
			));
		}
		
		return true;
	}
	
	/**
	 * Fill up unanswered item groups to $form_item_groups
	 *
	 * @param $form_item_groups
	 */
	private function add_unanswered_items(&$form_item_groups) {
		foreach($this->form_to_itemgroup_map as $form_OID => $item_groups) {
			
			foreach ( $item_groups as $group_OID => $item_group ) {
				$initialised_item_group = array();
				foreach ($item_group as $item_OID) {
					$initialised_item_group [$item_OID] = '';
				}
				
				if(array_key_exists($form_OID, $form_item_groups) === false)
					$form_item_groups [$form_OID] = array();
				
				if (array_key_exists ( $group_OID, $form_item_groups[$form_OID] ) === false) {
					$form_item_groups [$form_OID] [$group_OID] = array (
							'OID' => $group_OID,
							'itemData' => array (
									$initialised_item_group 
							) 
					);
				}
			}
		}
	}
	
	/**
	 * This function used to build various maps required
	 * 
	 * @param $clinical_data
	 */
	private function build_maps($clinical_data) {
		$this->logger->trace ( 'Building maps' );
		$question_map = array ();
		$question_to_group_map = array ();
		$group_to_crf_map = array ();
		$form_to_itemgroup_map = array ();
		$study_event_map = array();
		
		foreach ( $clinical_data->get_study_event_data_list() as $study_event_data) {
			foreach ( $study_event_data->get_form_list() as $form ) {
				$form_to_itemgroup_map [$form->get_form_OID()] = array();
				foreach($form->get_item_groups() as $item_group) {
					$group_to_crf_map [$item_group->get_item_group_OID ()] = $form->get_form_OID ();
					$form_to_itemgroup_map [$form->get_form_OID ()][$item_group->get_item_group_OID ()] = array ();
					foreach ( $item_group->get_items() as $item ) {
						array_push ( $form_to_itemgroup_map[$form->get_form_OID()] [$item_group->get_item_group_OID()], $item->get_OC_item_OID() );
						$question_ID = $item->get_SG_question_ID();
						$option_ID = $item->get_SG_option_ID();
						if ($option_ID !== '') {
							$question_ID .= '(' . $option_ID . ')';
						}
						$question_map[$question_ID] = array (
								'itemOID' => $item->get_OC_item_OID(),
								'dataType' => $item->get_data_type() 
						);
						
						if(strcmp($question_map[$question_ID]['dataType'], 'DATE')=== 0){
							$question_map[$question_ID]['dateFormat'] = $item->get_date_format();
						}
						
						$question_to_group_map[$question_ID] = array (
								'groupOID' => $item_group->get_item_group_OID(),
								'groupRepeatKey' => $item_group->get_item_group_repeat_key(),
								'formOID' => $form->get_form_OID() 
						);
					}
				}
			}
			$study_event_map = $study_event_data->get_study_events();
		}
		
		$this->question_map = $question_map;
		$this->study_event_map = $study_event_map;
		$this->group_to_crf_map = $group_to_crf_map;
		$this->question_to_group_map = $question_to_group_map;
		$this->form_to_itemgroup_map = $form_to_itemgroup_map;
	}
	
	/**
	 * Create the basic configuration map required to fill ODM
	 * 
	 * @param $clinical_data
	 */
	private function build_config_param_map($clinical_data) {
		$this->logger->trace('Building configuration parameter map');
		
		$config_param['ocWsInstanceURL'] = $clinical_data->get_OC_URI();
		$config_param['ocUserName'] = $clinical_data->get_OC_username();
		$config_param['ocPassword'] = $clinical_data->get_OC_password();
		
		$config_param['sgURI'] = $clinical_data->get_SG_URI();
		$config_param['sgUser'] = $clinical_data->get_SG_username();
		$config_param['sgPass'] = $clinical_data->get_SG_password();
		
		$config_param['studyOID'] = $clinical_data->get_study_OID();
		$config_param['metaDataVersionOID'] = 1;
		
		return $config_param;
	}
	
	/**
	 * Post survey responses to mapped CRF(s)
	 * 
	 * @param $clinical_data     	
	 * @param $surveyID        	
	 *
	 */
	public function post_data_to_CRF($clinical_data, $surveyID) {
		$this->build_maps($clinical_data);
		$is_valid_responses = $this->retrieve_survey_response($clinical_data);
		
		$OC_push_status = array();
		if($is_valid_responses){
			$config_param = $this->build_config_param_map($clinical_data);
			$OC_push_status = $this->upload_on_OC($config_param, $clinical_data);
		}
		
		return $OC_push_status;
	}
}
