<?php
require_once dirname(__FILE__).'/OpenClinicaSoapWebService.php';
require_once dirname(__FILE__).'/OpenClinicaODMFunctions.php';
require_once dirname(__FILE__).'/../config/config.php';

/**
 * This class uses importdata webservice of OC to push CRF's data
 *
 * Note: For pushing every new response to OC, create separate instance of this class and then use it.
 *
 * @author nitin
 *        
 */
class ImportDataWSConsumer {
	private $ODM_Param = array();
	private $logger = null;
	private $studyEventData = array();
	
	public function __construct() {
		$config = Config::get_instance()->get_configuration();
		$this->logger = $config['logger'];
		
		$this->ODM_Param = array (
				'ocWsInstanceURL' => null,
				'ocUserName' => null,
				'ocPassword' => null,
				'studyOID' => null,
				'metaDataVersionOID' => null,
				'subjectKey' => null 
		);
	}
	
	/**
	 * This function add common ODM parameters
	 *
	 * @param $ODM_Param
	 * @return true for correct configuration or false if any parameter missing
	 */
	public function config($ODM_Param) {
		if (empty($ODM_Param)){
			$this->logger->error("All ODM parameter missing.");
			return false;
		}
		
		foreach ($this->ODM_Param as $name => $v){
			if (!array_key_exists($name, $ODM_Param)){
				$this->logger->error("ODM parameter missing: " . $name );
				return false;
			} else {
				$this->ODM_Param[$name] = $ODM_Param[$name];
			}
		}
		
		return true;
	}
	
	/**
	 * This function used to add CRF to studyevent identified by $studyEventOID
	 *
	 * Note: Input Parameter $crf should be inputed as 
	 * $crf = array(
	 * 'formOID' => ?,
	 * 'itemGroups' => ?
	 * );
	 * 
	 * @param $studyEventOID
	 * @param $crf
	 */
	public function add_crf($studyEventOID, $crf) {
		if(!array_key_exists($studyEventOID, $this->studyEventData)) {
			$this->logger->debug('StudyEvent (OID:'.$studyEventOID.') is not found, hence cannot add CRF into it.');
		}
		array_push($this->studyEventData [$studyEventOID] ['crfList'], $crf);
	}
	
	/**
	 * This function used to add study event
	 *
	 * Note: Input Parameter $studyEventParam should be inputed as
	 * $studyEventParam = array(
	 * 	'studyEventOID' => ?,
	 * 	'studyEventRepeatKey' => ?
	 * );
	 * 
	 * @param $studyEventParam
	 */
	public function add_study_event($studyEventParam) {
		if(!array_key_exists($studyEventParam ['studyEventOID'], $this->studyEventData)){
			$studyEvent = array (
					$studyEventParam ['studyEventOID'] => array(
							'studyEventOID' => $studyEventParam['studyEventOID'],
							'studyEventRepeatKey' => $studyEventParam['studyEventRepeatKey'],
							'crfList' => array() 
					) 
			);
			
			$this->studyEventData = array_merge($this->studyEventData, $studyEvent);
		}
	}
	
	/**
	 * This function used to build ODM studyEventData element, using added studyevents and crfs
	 *
	 * @return $ocODMstudyEventData
	 */
	private function build_study_events() {
		$ocODMstudyEventData = array();
		foreach($this->studyEventData as $studyEvent){
			
			$ocODMForms = array ();
			
			foreach($studyEvent ['crfList'] as $crf){
				$ocForm = new ocODMformData ( $crf ['formOID'], $this->build_OC_ItemGroup_rows($crf['itemGroups']));
				array_push($ocODMForms, $ocForm);
			}
			
			$ocODMstudyEvent = new ocODMstudyEventData($studyEvent['studyEventOID'], $studyEvent['studyEventRepeatKey'], $ocODMForms);
			
			array_push($ocODMstudyEventData, $ocODMstudyEvent);
		}
		
		return $ocODMstudyEventData;
	}
	
	/**
	 * This function used to build $ocItemGroupRows
	 *
	 * @param $itemGroups
	 * @return multitype:
	 */
	private function build_OC_ItemGroup_rows($itemGroups) {
		$itemGroupRows = array ();
		
		foreach($itemGroups as $itemGroup) {
			$ocODMitems = null;
			
			$groupRepeatKey = 1;
			
			foreach ( $itemGroup['itemData'] as $itemData ) {
				$form_field = $itemData;
				$no_of_fields = 0;
				foreach($form_field as $itemOID => $itemValue){
					$ocODMitems [$no_of_fields ++] = new ocODMitemData($itemOID, $itemValue);
				}
				array_push($itemGroupRows, new ocODMitemGroupData($itemGroup ['OID'], $groupRepeatKey ++, $ocODMitems));
			}
		}
		
		return $itemGroupRows;
	}
	
	/**
	 * This function build ODM
	 *
	 * @return multitype:ocODMclinicalData
	 */
	private function build_ODM() {
		$odm = array (
				new ocODMclinicalData($this->ODM_Param ['studyOID'], $this->ODM_Param ['metaDataVersionOID'], array (
						new ocODMsubjectData($this->ODM_Param ['subjectKey'], $this->build_study_events()) 
				)) 
		);
		
		return $odm;
	}
	
	/**
	 * This function pushes ODM on OC and return push status
	 *
	 * @return status of push or false if any worse codition occurred
	 */
	public function push_ODM_to_OC() {
		$ODM = $this->build_ODM();
		$ws_instance = new OpenClinicaSoapWebService($this->ODM_Param ['ocWsInstanceURL'], $this->ODM_Param ['ocUserName'], $this->ODM_Param ['ocPassword']);
		$import_status = $ws_instance->dataImport(ocODMtoXML($ODM ));
		
		if($import_status === false){
			return false;
		}
		
		$body = $import_status->children('SOAP-ENV', true)->Body->children('http://openclinica.org/ws/data/v1');
		$importDataResponse = $body->importDataResponse;
		if($importDataResponse === false) {
			$this->logger->error('importDataResponse object is not present in SOAP response obtained!');
			return false;
		}
		$result = $importDataResponse->result;
		if($result === false) {
			$this->logger->error('result object is not present in SOAP response obtained!');
			return false;
		}
		$success = (string) $result;
		$status = array ();
		if(strcmp($success, 'Success') === 0) {
			$status['success'] = true;
			$status['error'] = '--';
		}
		if(strcmp($success, 'Fail') === 0) {
			$status['success'] = false;
			$status['error'] = (string) $importDataResponse->error;
		}
		return $status;
	}
}