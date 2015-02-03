<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2015 PhreeSoft      (www.PhreeSoft.com)       |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/contacts/pages/popup_accts/pre_process.php
//
$security_level = \core\classes\user::validate(0, true);
/**************  include page specific files    *********************/
require(DIR_FS_WORKING . 'functions/contacts.php');
/**************   page specific initialization  *************************/
define('JOURNAL_ID',(int)$_GET['jID']);
$account_type = (isset($_GET['type']) ? $_GET['type'] : 'c');	// current types are c (customer) and v (vendor)
switch ($account_type) {
  default:
  case 'c': $terms_type = 'AR'; break;
  case 'v': $terms_type = 'AP'; break;
}
$fill = isset($_GET['fill']) ? $_GET['fill'] : 'bill';
history_filter('contacts_popup');
$temp = '\contacts\classes\type\\'.$account_type;
$cInfo = new $temp;
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/popup_accts/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'go_first':    $_REQUEST['list'] = 1;       break;
  case 'go_previous': $_REQUEST['list'] = max($_REQUEST['list']-1, 1); break;
  case 'go_next':     $_REQUEST['list']++;         break;
  case 'go_last':     $_REQUEST['list'] = 99999;   break;
  case 'search':
  case 'search_reset':
  case 'go_page':
  default:
}
/*****************   prepare to display templates  *************************/
// generate address arrays for javascript
$js_arrays = gen_build_company_arrays();
// build the list header
$heading_array = array(
	'primary_name'             => TEXT_NAME_OR_COMPANY,
	'address1'                 => TEXT_ADDRESS1,
	'city_town,state_province' => TEXT_CITY_TOWN,
	'state_province,city_town' => TEXT_STATE_PROVINCE,
	'postal_code'              => TEXT_POSTAL_CODE,
	'telephone1'               => TEXT_TELEPHONE,
);
switch (JOURNAL_ID) {
	case  6:
	case 12: $extra_headings = array(TEXT_OPEN_ORDERS); break;
	case  7:
	case 13: $extra_headings = array(TEXT_OPEN_INVOICES); break;
	default: $extra_headings = array('&nbsp;'); break;
}
$result      = html_heading_bar($heading_array, $extra_headings);
$list_header = $result['html_code'];
$disp_order  = $result['disp_order'];
// build the list for the page selected
if (isset($_REQUEST['search_text']) && $_REQUEST['search_text'] <> '') {
  $search_fields = array('c.short_name', 'a.primary_name', 'a.contact', 'a.telephone1', 'a.telephone2',
  	'a.address1', 'a.address2', 'a.city_town', 'a.postal_code', 'c.account_number');
  // hook for inserting new search fields to the query criteria.
  if (is_array($extra_search_fields)) $search_fields = array_merge($search_fields, $extra_search_fields);
  $search = " and (" . implode(" like %{$_REQUEST['search_text']}%' or ", $search_fields) . " like '%{$_REQUEST['search_text']}%)";
} else {
  $search = '';
}

$field_list = array('a.address_id', 'c.id', 'a.ref_id', 'a.type', 'a.primary_name', 'a.contact', 'a.address1',
	'a.address2', 'a.city_town', 'a.state_province', 'a.postal_code', 'a.country_code', 'c.short_name',
	'a.telephone1', 'a.email', 'c.first_date', 'c.last_update', 'c.gl_type_account', 'c.special_terms',
	'c.last_date_1', 'c.last_date_2', 'c.inactive');

// hook to add new fields to the query return results
if (is_array($extra_query_list_fields) > 0) $field_list = array_merge($field_list, $extra_query_list_fields);

$query_raw = "select SQL_CALC_FOUND_ROWS " . implode(', ', $field_list)  . "
	from " . TABLE_CONTACTS . " c left join " . TABLE_ADDRESS_BOOK . " a on c.id = a.ref_id
	where a.type = '{$account_type}m'" . $search . " order by $disp_order";

$query_result = $admin->DataBase->query($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
$query_split  = new \core\classes\splitPageResults($_REQUEST['list'], '');
history_save('contacts_popup');
$include_header   = false;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', TEXT_CONTACT_SEARCH);

?>