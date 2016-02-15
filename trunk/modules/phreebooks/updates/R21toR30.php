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
//  Path: /modules/phreebooks/install/updates/R21toR30.php
//

// This script updates Release 2.1 to Phreedom Framework Release 3.0, it is included as part of the update script

// *************************** IMPORTANT UPDATE INFORMATION *********************************//

//********************************* END OF IMPORTANT ****************************************//
if (!$admin->DataBase->field_exists(TABLE_JOURNAL_MAIN, 'closed_date')) {
  // reconfigure table configuration to new format, remove this constant list whic is no longer needed
  $toBeRemoved = array(
	'AR_PAYMENT_TERMS',
	'AP_DEFAULT_TERMS',
	'MODULE_PAYMENT_INSTALLED',
	'MODULE_ZENCART_INSTALLED',
	'PDF_APP',
	'SEND_EMAILS',
	'ENTRY_EMAIL_ADDRESS_CHECK',
	'EMAIL_ARCHIVE',
	'EMAIL_FRIENDLY_ERRORS',
	'EMAIL_SEND_MUST_BE_STORE',
	'CONTACT_US_LIST',
	'CONTACT_US_STORE_NAME_ADDRESS',
	'CC_OWNER_MIN_LENGTH',
	'CC_NUMBER_MIN_LENGTH',
	'CC_ENABLED_VISA',
	'CC_ENABLED_MC',
	'CC_ENABLED_AMEX',
	'CC_ENABLED_DISCOVER',
	'CC_ENABLED_DINERS_CLUB',
	'CC_ENABLED_JCB',
	'CC_ENABLED_AUSTRALIAN_BANKCARD',
	'MODULE_PRICE_SHEET_QTY_STATUS',
	'MODULE_PRICE_SHEET_QTY_SORT_ORDER',
	'MODULE_PRICE_SHEETS_INSTALLED',
	'MODULE_SHIPPING_INSTALLED',
	'USE_DEFAULT_LANGUAGE_CURRENCY',
  );
  foreach ($toBeRemoved as $value) {
    $admin->DataBase->exec("delete from " . TABLE_CONFIGURATION . " where configuration_key = '" . $value . "'");
  }
  // change True's and False's to 1's and 0s
  $admin->DataBase->query("update " . TABLE_CONFIGURATION . " set configuration_value = '0'
    where configuration_value in ('false', 'False', 'FALSE', 'no', 'No', 'NO')");
  $admin->DataBase->query("update " . TABLE_CONFIGURATION . " set configuration_value = '1'
    where configuration_value in ('true', 'True', 'TRUE', 'yes', 'Yes', 'YES')");
  // increase field length to description
  $admin->DataBase->query("ALTER TABLE " . TABLE_JOURNAL_MAIN . " ADD `closed_date` DATE NOT NULL AFTER `closed`");
}

// convert reports
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/reportwriter.php');
$result = $admin->DataBase->query("select * from " . TABLE_REPORTS);
$count  = 0;
while (!$result->EOF) {
  	$skip_report = false;
  	$report = PrepReport($result->fields['id']);
  	if (!$params = import_text_params($report)) throw new \core\classes\userException(sprintf(PB_CONVERT_SAVE_ERROR, $result->fields['description']));
  	// fix some fields
  	$params->custom = $result->fields['standard_report'] ? 's' : 'c';
  	if (!$skip_report) {
		if (!$success = save_report($params)) throw new \core\classes\userException(sprintf(PB_CONVERT_SAVE_ERROR, $params->title));
		$count++;
  	}
  	$result->MoveNext();
}
// Copy the PhreeBooks images
$dir_source = DIR_FS_MY_FILES . $_SESSION['user']->company . '/images';
$dir_dest   = PF_DIR_MY_REPORTS . 'images';
$d = dir($dir_source);
while (FALSE !== ($filename = $d->read())) {
  if ($filename == '.' || $entry == '..') continue;
  @copy($dir_source . '/' . $filename, $dir_dest . '/' . $filename);
}
$d->close();
if ($count) \core\classes\messageStack::add(sprintf(PB_CONVERT_SUCCESS, $count), 'success');

$result = $admin->DataBase->query("select id, doc_title from " . TABLE_PHREEFORM . " where doc_ext in ('rpt','frm')");
$new_reports = array();
while (!$result->EOF) {
  $new_reports[$result->fields['id']] = $result->fields['doc_title'];
  $result->MoveNext();
}
$result = $admin->DataBase->query("select id, params from " . TABLE_USERS_PROFILES . " where dashboard_id = 'favorite_reports'");
while (!$result->EOF) {
  if ($result->fields['params']) {
    $new_params = array();
    $params     = unserialize($result->fields['params']);
    if (is_array($params)) foreach ($params as $description) {
      if ($key = array_search($description, $new_reports)) $new_params[$key] = $description;
    }
    $params = (sizeof($new_params) > 0) ? serialize($new_params) : '';
    $admin->DataBase->query("update " . TABLE_USERS_PROFILES . " set params = '" . $params . "' where id = " . $result->fields['id']);
  }
  $result->MoveNext();
}

// delete some extra fields in the configuration tables no longer needed
if ($admin->DataBase->field_exists(TABLE_CONFIGURATION, 'set_function')) {
  $admin->DataBase->query("ALTER TABLE " . TABLE_CONFIGURATION . " DROP configuration_title, DROP configuration_description,
    DROP configuration_group_id, DROP sort_order, DROP last_modified, DROP date_added, DROP use_function,
    DROP set_function");
}

if (!$admin->DataBase->field_exists(TABLE_CONTACTS, 'tax_id')) {
  $admin->DataBase->query("ALTER TABLE " . TABLE_CONTACTS . " ADD tax_id INT(11) NOT NULL DEFAULT '0' AFTER price_sheet");
}

if (!$admin->DataBase->field_exists(TABLE_SHIPPING_LOG, 'deliver_late')) {
  $admin->DataBase->query("ALTER TABLE " . TABLE_SHIPPING_LOG . " ADD deliver_late ENUM('0','T','L') NOT NULL DEFAULT '0' AFTER actual_date");
}

if (!$admin->DataBase->field_exists(TABLE_INVENTORY, 'quantity_on_allocation')) {
  $admin->DataBase->query("ALTER TABLE " . TABLE_INVENTORY . " ADD quantity_on_allocation FLOAT NOT NULL DEFAULT '0' AFTER quantity_on_sales_order");
}

?>