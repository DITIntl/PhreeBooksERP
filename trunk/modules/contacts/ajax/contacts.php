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
//  Path: /modules/contacts/ajax/contacts.php
//
/**************   Check user security   *****************************/
$security_level = \core\classes\user::validate();
/**************  include page specific files    *********************/
require_once(DIR_FS_MODULES . 'contacts/defaults.php');
require_once(DIR_FS_MODULES . 'contacts/classes/contacts.php');
/**************   page specific initialization  *************************/
$xml     = NULL;
$message = array();

switch ($_REQUEST['action']) {
	case 'get_address':
		$id   = $_GET['aID'];
		$type = $_GET['type'];
		$xml .= xmlEntry('type', $type);
		$result = $admin->DataBase->query("select * from ".TABLE_ADDRESS_BOOK." where address_id = $id");
		if ($result->fetch(\PDO::FETCH_NUM) < 1) {
			$message[] = sprintf('The record could not be found! Looking for id = %s', $id);
		} else {
			$xml .= '<Address>'.chr(10);
			foreach ($result as $key => $value) $xml .= xmlEntry($key, $value);
			$xml .= '</Address>'.chr(10);
		}
		// if it's a CRM entry, we need some primary information
		$result = $admin->DataBase->query("select id, short_name, contact_first, contact_middle, contact_last, account_number, gov_id_number
			from ".TABLE_CONTACTS." where id = ".$result['ref_id']." limit 1");
		$xml .= xmlEntry('contact_id',    $result['id']);
		$xml .= xmlEntry('short_name',    $result['short_name']);
		$xml .= xmlEntry('contact_middle',$result['contact_middle']);
		$xml .= xmlEntry('contact_first', $result['contact_first']);
		$xml .= xmlEntry('contact_last',  $result['contact_last']);
		$xml .= xmlEntry('account_number',$result['account_number']);
		$xml .= xmlEntry('gov_id_number', $result['gov_id_number']);
		break;

	case 'rm_address':
		$id     = $_GET['aID'];
		$result = $admin->DataBase->query("select ref_id, type from ".TABLE_ADDRESS_BOOK." where address_id = $id");
		if ($result['type'] == 'im') { // it's a contact record, also delete record
			$short_name = gen_get_contact_name($id);
			$contact = new \contacts\classes\contacts();
			if ($contact->delete($result['ref_id'])) {
	  			gen_add_audit_log(TEXT_CONTACTS . '-' . TEXT_DELETE . '-' . $contact->title, $short_name);
				$message[] = 'The record was successfully deleted!';
			} else {
				$message[] = ACT_ERROR_CANNOT_DELETE;
			}
		} else { // just delete the address
			$admin->DataBase->exec('delete from '.TABLE_ADDRESS_BOOK." where address_id = $id");
		}
		$message[] = 'The record was successfully deleted!';
		$xml .= xmlEntry('address_id', $id);
		break;

	case 'get_payment':
		$id = $_GET['pID'];
		$result = $admin->DataBase->query("select id, hint, enc_value from ".TABLE_DATA_SECURITY." where id = $id limit 1");
		if ($result->fetch(\PDO::FETCH_NUM) < 1) {
			$message[] = sprintf('The record could not be found! Looking for id = %s', $id);
		} else {
			$data = \core\classes\encryption::decrypt($_SESSION['ENCRYPTION_VALUE'], $result['enc_value']);
			$fields = explode(':', $data);
			if (strlen($fields[3]) == 2) $fields[3] = '20'.$fields[3]; // make sure year is 4 digits
			$xml .= "<PaymentMethod>\n";
			$xml .= xmlEntry("payment_id",   $result['id']);
			$xml .= xmlEntry("payment_hint", $result['hint']);
			for ($i = 0; $i < sizeof($fields); $i++) $xml .= xmlEntry("field_" . $i, $fields[$i]);
			$xml .= "</PaymentMethod>\n";
		}
		break;

	case 'rm_payment':
		$id = $_GET['pID'];
		$admin->DataBase->exec("delete from ".TABLE_DATA_SECURITY." where id = $id");
		$xml .= xmlEntry('payment_id', $id);
		$message[] = 'The record was successfully deleted!';
		break;

	case 'rm_crm':
		$id = $_GET['nID'];
		$admin->DataBase->exec("delete from ".TABLE_CONTACTS_LOG." where log_id = $id");
		$xml .= xmlEntry('crm_id', $id);
		$message[] = 'The record was successfully deleted!';
		break;

	default:
		ob_end_flush();
  		session_write_close();
		die;
}

if (sizeof($message) > 0) $xml .= xmlEntry('message', implode("\n",$message));
echo createXmlHeader() . $xml . createXmlFooter();
ob_end_flush();
session_write_close();
die;
?>