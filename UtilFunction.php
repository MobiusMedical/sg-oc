<?php
/**
 * This class contains utility functions 
 * 
 * @author nitin
 * 
 */
class UtilFunction{
	
	/**
	 * Return text present between opening and closing bracket
	 */
	public static function getTextBetweenBrackets($searchText){
		$open_brack_pos = strpos($searchText, '(');
		$close_brack_pos = strpos($searchText, ')');
		return substr($searchText, $open_brack_pos+1, $close_brack_pos-$open_brack_pos-1);
	}
	
	/**
	 * This function convert date to ISO 8606 formatted string
	 * @param $dateStr
	 * @param $format
	 * @return ISO 8606 formatted date or false if not able to format date
	 */
	public static function get_ISO_8606_format_date($dateStr, $format){
		$dateTime = DateTime::createFromFormat($format, $dateStr);
		$errors = DateTime::getLastErrors();
		if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
			return false;
		}
		
		return $dateTime->format('Y-m-d');
	}
}