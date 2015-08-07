<?php
/**
 * 
 * @author nitin
 *
 */
class ClinicalData{
	private $study_OID = null;
	
	private $OC_username = null;
	private $OC_password = null;
	private $OC_URI = null;
	
	private $SG_username = null;
	private $SG_password = null;
	private $SG_URI = null;

	private $survey_ID = null;
	
	private $study_event_data_list = array();
	
	public function set_study_OID($study_OID){
		$this->study_OID = $study_OID;
	}
	
	public function get_study_OID(){
		return $this->study_OID;
	}
	
	public function set_OC_username($OC_username){
		$this->OC_username = $OC_username;
	}
	
	public function get_OC_username(){
		return $this->OC_username;
	}
	
	/**
	 * 
	 * @param plain text $OC_password
	 */
	public function set_OC_password($OC_password){
		$this->OC_password =sha1($OC_password);
	}
	
	public function get_OC_password(){
		return $this->OC_password;
	}
	
	public function get_OC_URI(){
		return $this->OC_URI;
	}
	
	public function set_OC_URI($OC_URI){
		$this->OC_URI = $OC_URI;
	}
	
	public function get_SG_URI(){
		return $this->SG_URI;
	}
	
	public function set_SG_URI($SG_URI){
		$this->SG_URI = $SG_URI;
	}
	
	public function get_SG_username(){
		return $this->SG_username;
	}
	
	public function set_SG_username($SG_username){
		$this->SG_username = $SG_username;
	}
	
	public function get_SG_password(){
		return $this->SG_password;
	}
	
	public function set_SG_password($SG_password){
		$this->SG_password = $SG_password;
	}
	
	public function get_survey_ID(){
		return $this->survey_ID;
	}
	
	public function set_survey_ID($survey_ID){
		$this->survey_ID = $survey_ID;
	}
	
	public function set_study_event_data_list($study_event_data_list){
		$this->study_event_data_list = $study_event_data_list;
	}
	
	public function get_study_event_data_list(){
		return $this->study_event_data_list;
	}
	
}

/**
 * 
 * @author nitin
 *
 */
class StudyEventData{
	
	/**
	 * This is map of study event name to study event OID
	 */
	private $study_events = array();
	private $form_list = array();
	
	public function get_form_list(){
		return $this->form_list;
	}
	
	public function set_form_list($form_list){
		$this->form_list = $form_list;
	}
	
	public function get_study_events(){
		return $this->study_events;
	}
	
	public function set_study_events($study_events){
		$this->study_events = $study_events;
	}
}



/**
 * 
 * @author nitin
 *
 */
class FormData{
	private $form_OID = null;
	private $item_groups = array();
	
	public function get_form_OID(){
		return $this->form_OID;
	}
	
	public function set_form_OID($form_OID){
		$this->form_OID = $form_OID;
	}
	
	public function get_item_groups(){
		return $this->item_groups;
	}
	
	public function set_item_groups($item_groups){
		$this->item_groups = $item_groups;
	}
}


/**
 * 
 * @author nitin
 *
 */
class ItemGroupData{
	private $item_group_OID = null;
	private $item_group_repeat_key = null;
	private $transaction_type = null;
	private $items = array();
	
	public function set_item_group_OID($item_group_OID){
		$this->item_group_OID = $item_group_OID;
	}
	
	public function get_item_group_OID(){
		return $this->item_group_OID;
	}
	
	public function set_item_group_repeat_key($item_group_repeat_key){
		$this->item_group_repeat_key = $item_group_repeat_key;
	}
	
	public function get_item_group_repeat_key(){
		return $this->item_group_repeat_key;
	}
	
	public function set_transaction_type($transaction_type){
		$this->transaction_type = $transaction_type;
	}
	
	public function get_transaction_type(){
		return $this->transaction_type;
	}
	
	public function set_items($items){
		$this->items = $items;
	}
	
	public function get_items(){
		return $this->items;
	}
}

/**
 * 
 * @author nitin
 *
 */
class ItemData{
	private $OC_item_OID = null;
	private $SG_question_ID = null;
	private $SG_option_ID = null;
	private $data_type = null;
	
	/**
	 * Used to store date format if data_type id DATE
	 * @var String  
	 */
	private $date_format = null;
	
	public function set_OC_item_OID($OC_item_OID){
		$this->OC_item_OID = $OC_item_OID;
	}
	
	public function get_OC_item_OID(){
		return $this->OC_item_OID;
	}
	
	public function set_SG_question_ID($SG_question_ID){
		$this->SG_question_ID = $SG_question_ID;
	}
	
	public function get_SG_question_ID(){
		return $this->SG_question_ID;
	}
	
	public function set_SG_option_ID($SG_option_ID){
		$this->SG_option_ID = $SG_option_ID;
	}
	
	public function get_SG_option_ID(){
		return $this->SG_option_ID;
	}
	
	public function set_data_type($data_type){
		$this->data_type = $data_type;
	}
	
	public function get_data_type(){
		return $this->data_type;
	}
	
	public function set_date_format($date_format){
		$this->date_format = $date_format;
	}
	
	public function get_date_format(){
		return $this->date_format;
	}
}