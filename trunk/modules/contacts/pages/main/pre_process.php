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
//  Path: /modules/contacts/pages/main/pre_process.php
//
/**************   page specific initialization  *************************/
$contact_js  = '';
$js_pmt_array= '';
$js_actions  = '';
$criteria    = array();
if ($_POST['crm_date']) $_POST['crm_date'] = gen_db_date($_POST['crm_date']);
if ($_POST['due_date']) $_POST['due_date'] = gen_db_date($_POST['due_date']);
$type        = isset($_GET['type']) ? $_GET['type'] : 'c'; // default to customer

history_filter('contacts'.$type, $defaults = array('sf'=>'', 'so'=>'asc')); // load the filters
$default_f0 = defined('CONTACTS_F0_'.strtoupper($type)) ? constant('CONTACTS_F0_'.strtoupper($type)) : DEFAULT_F0_SETTING;
$_SESSION['f0'] = (isset($_SESSION['f0'])) ? $_SESSION['f0'] : $default_f0;
if($_SERVER['REQUEST_METHOD'] == 'POST') $_SESSION['f0'] = (isset($_POST['f0'])) ? $_REQUEST['f0'] : false; // show inactive checkbox
$temp = '\contacts\classes\type\\'.$type;
$cInfo = new $temp;
/**************   Check user security   *****************************/

/***************   hook for custom security  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/contacts/main/extra_security.php';
if (file_exists($custom_path)) { include($custom_path); }
$security_level = \core\classes\user::validate($cInfo->security_token); // in this case it must be done after the class is defined for
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
require_once(DIR_FS_WORKING . 'functions/contacts.php');
$fields = new \contacts\classes\fields();
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/main/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/

switch ($_REQUEST['action']) {
    case 'new':
        \core\classes\user::validate_security($security_level, 2);
	    break;
    case 'save':
    	try{
			$id = (int)db_prepare_input($_POST['id']);  // if present, then its an edit
		    $id ? \core\classes\user::validate_security($security_level, 3) : \core\classes\user::validate_security($security_level, 2);
			// error check
			$cInfo->data_complete();
			// start saving data
		  	$cInfo->save_contact();
		  	$cInfo->save_addres();
		  	if ($type <> 'i' && ($_POST['i_short_name'] || $_POST['address']['im']['primary_name'])) { // is null
		  		$crmInfo = new \contacts\classes\type\i;
	        	$crmInfo->auto_field  = $cInfo->type=='v' ? 'next_vend_id_num' : 'next_cust_id_num';
	        	$crmInfo->dept_rep_id = $cInfo->id;
		  		// error check contact
				$crmInfo->data_complete();
	        	$crmInfo->save_contact();
	      		$crmInfo->save_addres();
		  	}
		  	// payment fields
		  	if (ENABLE_ENCRYPTION && $_POST['payment_cc_name'] && $_POST['payment_cc_number']) { // save payment info
				$cc_info = array(
				  'name'    => db_prepare_input($_POST['payment_cc_name']),
				  'number'  => db_prepare_input($_POST['payment_cc_number']),
				  'exp_mon' => db_prepare_input($_POST['payment_exp_month']),
				  'exp_year'=> db_prepare_input($_POST['payment_exp_year']),
				  'cvv2'    => db_prepare_input($_POST['payment_cc_cvv2']),
				);
				$enc_value = \core\classes\encryption::encrypt_cc($cc_info);
				$payment_array = array(
				  'hint'      => $enc_value['hint'],
				  'module'    => 'contacts',
				  'enc_value' => $enc_value['encoded'],
				  'ref_1'     => $cInfo->id,
				  'ref_2'     => $cInfo->address[$type.'m']['address_id'],
				  'exp_date'  => $enc_value['exp_date'],
				);
				db_perform(TABLE_DATA_SECURITY, $payment_array, $_POST['payment_id'] ? 'update' : 'insert', 'id = '.$_POST['payment_id']);
		  	}
		  	// Check attachments
		  	$result = $admin->DataBase->query("select attachments from ".TABLE_CONTACTS." where id = $id");
		  	$attachments = $result->fields['attachments'] ? unserialize($result->fields['attachments']) : array();
		  	$image_id = 0;
		  	while ($image_id < 100) { // up to 100 images
		    	if (isset($_POST['rm_attach_'.$image_id])) {
				  	@unlink(CONTACTS_DIR_ATTACHMENTS . 'contacts_'.$cInfo->id.'_'.$image_id.'.zip');
				  	unset($attachments[$image_id]);
		    	}
		    	$image_id++;
		  	}
		  	if (is_uploaded_file($_FILES['file_name']['tmp_name'])) { // find an image slot to use
		    	$image_id = 0;
		    	while (true) {
				    if (!file_exists(CONTACTS_DIR_ATTACHMENTS.'contacts_'.$cInfo->id.'_'.$image_id.'.zip')) break;
				    $image_id++;
			    }
		    	saveUploadZip('file_name', CONTACTS_DIR_ATTACHMENTS, 'contacts_'.$cInfo->id.'_'.$image_id.'.zip');
			    $attachments[$image_id] = $_FILES['file_name']['name'];
			}
		  	$sql_data_array = array('attachments' => sizeof($attachments)>0 ? serialize($attachments) : '');
		  	db_perform(TABLE_CONTACTS, $sql_data_array, 'update', 'id = '.$cInfo->id);
		  	// check for crm notes
		  	if ($_POST['crm_action'] <> '' || $_POST['crm_note'] <> '') {
				$sql_data_array = array(
				  'contact_id' => $cInfo->id,
				  'log_date'   => $_POST['crm_date'],
				  'entered_by' => $_POST['crm_rep_id'],
				  'action'     => $_POST['crm_action'],
				  'notes'      => db_prepare_input($_POST['crm_note']),
				);
				db_perform(TABLE_CONTACTS_LOG, $sql_data_array, 'insert');
		  	}
		  	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
		}catch(Exception $e){
			$messageStack->add($e->getMessage());
			$_REQUEST['action'] = 'edit';
		}

		break;

    case 'edit':
    case 'properties':
   	    $cInfo->getContact();
        break;

    case 'delete':
    case 'crm_delete':
	    \core\classes\user::validate_security($security_level, 4);
	    $short_name = gen_get_contact_name($cInfo->id);
	   	$cInfo->delete();
	    gen_add_audit_log(TEXT_CONTACTS.'-'.TEXT_DELETE.'-'.$cInfo->title, $short_name);
	    break;

    case 'download':
   	    $cID   = db_prepare_input($_POST['id']);
  	    $imgID = db_prepare_input($_POST['rowSeq']);
	    $filename = 'contacts_'.$cID.'_'.$imgID.'.zip';
	    if (file_exists(CONTACTS_DIR_ATTACHMENTS . $filename)) {
	       $backup = new \phreedom\classes\backup();
	       $backup->download(CONTACTS_DIR_ATTACHMENTS, $filename, true);
	    }
	    ob_end_flush();
  		session_write_close();
        die;

    case 'dn_attach': // download from list, assume the first document only
        $cID   = db_prepare_input($_POST['rowSeq']);
  	    $result = $admin->DataBase->query("select attachments from ".TABLE_CONTACTS." where id = $cID");
  	    $attachments = unserialize($result->fields['attachments']);
  	    foreach ($attachments as $key => $value) {
		   	$filename = 'contacts_'.$cID.'_'.$key.'.zip';
		   	if (file_exists(CONTACTS_DIR_ATTACHMENTS . $filename)) {
		      	$backup = new \phreedom\classes\backup();
		      	$backup->download(CONTACTS_DIR_ATTACHMENTS, $filename, true);
		      	ob_end_flush();
  				session_write_close();
		      	die;
		   	}
  	    }
 	case 'reset':
 		$_SESSION['f0'] = $default_f0;
		break;
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
$include_header = true;
$include_footer = true;

switch ($_REQUEST['action']) {
  case 'properties':
		$include_header   = false;
		$include_footer   = false;
		// now fall through just like edit
  case 'edit':
  case 'update':
  case 'new':
		for ($i = 1; $i < 13; $i++) {
		  $j = ($i < 10) ? '0' . $i : $i;
		  $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => $j . '-' . strftime('%B',mktime(0,0,0,$i,1,2000)));
		}
		$today = getdate();
		for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
		  	$year = strftime('%Y',mktime(0,0,0,1,1,$i));
			$expires_year[] = array('id' => $year, 'text' => $year);
		}
		// load the tax rates
		$tax_rates       = ord_calculate_tax_drop_down($type, true);
		$sales_rep_array = gen_get_rep_ids($type);
		$result = $admin->DataBase->query("select id, contact_first, contact_last, gl_type_account from ".TABLE_CONTACTS." where type='e'");
		$reps       = array();
		while(!$result->EOF) {
			$reps[$result->fields['id']] = $result->fields['contact_first'] . ' ' . $result->fields['contact_last'];
	  		$result->MoveNext();
		}
	    $include_template = 'template_detail.php';
		define('PAGE_TITLE', ($_REQUEST['action'] == 'new') ? $cInfo->page_title_new : $cInfo->page_title_edit ." - ({$cInfo->short_name}) {$cInfo->address[m][0]->primary_name}");
		break;
  default:
		$heading_array = array('c.short_name' => constant('ACT_' . strtoupper($type) . '_SHORT_NAME'));
	    if ($type == 'e') {
			$heading_array['c.contact_last,c.contact_first'] = TEXT_EMPLOYEE_NAME;
		} else {
			$heading_array['a.primary_name'] = TEXT_NAME_OR_COMPANY;
		}
		$heading_array['address1']       = TEXT_ADDRESS1;
		$heading_array['city_town']      = TEXT_CITY_TOWN;
		$heading_array['state_province'] = TEXT_STATE_PROVINCE;
		$heading_array['postal_code']    = TEXT_POSTAL_CODE;
		$heading_array['telephone1']     = TEXT_TELEPHONE;
		$result      = html_heading_bar($heading_array);
		$list_header = $result['html_code'];
		$disp_order  = $result['disp_order'];
		// build the list for the page selected
	    $criteria[] = "a.type = '" . $type . "m'";
	    if (isset($_REQUEST['search_text']) && $_REQUEST['search_text'] <> '') {
	      $search_fields = array('a.primary_name', 'a.contact', 'a.telephone1', 'a.telephone2', 'a.address1',
		  	'a.address2', 'a.city_town', 'a.postal_code', 'c.short_name');
		  // hook for inserting new search fields to the query criteria.
		  if (is_array($extra_search_fields)) $search_fields = array_merge($search_fields, $extra_search_fields);
		  $criteria[] = '(' . implode(' like \'%' . $_REQUEST['search_text'] . '%\' or ', $search_fields) . ' like \'%' . $_REQUEST['search_text'] . '%\')';
		}
		if (!$_SESSION['f0']) $criteria[] = "(c.inactive = '0' or c.inactive = '')"; // inactive flag

		$search = (sizeof($criteria) > 0) ? (' where ' . implode(' and ', $criteria)) : '';
		$field_list = array('c.id', 'c.inactive', 'c.short_name', 'c.contact_first', 'c.contact_last',
			'a.telephone1', 'c.attachments', 'c.first_date', 'c.last_update', 'c.last_date_1', 'c.last_date_2',
			'a.primary_name', 'a.address1', 'a.city_town', 'a.state_province', 'a.postal_code');
		// hook to add new fields to the query return results
		if (is_array($extra_query_list_fields) > 0) $field_list = array_merge($field_list, $extra_query_list_fields);
	    $query_raw = "select SQL_CALC_FOUND_ROWS " . implode(', ', $field_list)  . "
			from " . TABLE_CONTACTS . " c left join " . TABLE_ADDRESS_BOOK . " a on c.id = a.ref_id " . $search . " order by $disp_order";
	    $query_result = $admin->DataBase->query($query_raw, (MAX_DISPLAY_SEARCH_RESULTS * ($_REQUEST['list'] - 1)).", ".  MAX_DISPLAY_SEARCH_RESULTS);
    	$query_split  = new \core\classes\splitPageResults($_REQUEST['list'], '');
    	history_save('contacts'.$type);
	    $include_template = 'template_main.php'; // include display template (required)
	    switch ($type) {
	    	case 'b':define('PAGE_TITLE', sprintf(TEXT_MANAGER_ARGS, TEXT_BRANCH));	break;
	    	case 'c':define('PAGE_TITLE', sprintf(TEXT_MANAGER_ARGS, TEXT_CUSTOMER));	break;
	    	case 'e':define('PAGE_TITLE', sprintf(TEXT_MANAGER_ARGS, TEXT_EMPLOYEE));	break;
	    	case 'i':define('PAGE_TITLE', TEXT_CRM); break;
	    	case 'j':define('PAGE_TITLE', sprintf(TEXT_MANAGER_ARGS, TEXT_PROJECT));	break;
	    	case 'v':define('PAGE_TITLE', sprintf(TEXT_MANAGER_ARGS, TEXT_VENDOR));	break;
	    }

}

?>