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
//  Path: /modules/phreedom/pages/import_export/pre_process.php
//
$security_level = \core\classes\user::validate(SECURITY_ID_IMPORT_EXPORT);
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_WORKING . 'functions/phreedom.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
/**************   page specific initialization  *************************/
$subject = $_POST['subject'];
if (substr($_REQUEST['action'], 0, 3) == 'go_') {
  $subject = substr($_REQUEST['action'], 3);
  $_REQUEST['action']  = 'module';
} elseif (substr($_REQUEST['action'], 0, 11) == 'sample_xml_') {
  $db_table = substr($_REQUEST['action'], 11);
  $_REQUEST['action']   = 'sample_xml';
} elseif (substr($_REQUEST['action'], 0, 11) == 'sample_csv_') {
  $db_table = substr($_REQUEST['action'], 11);
  $_REQUEST['action']   = 'sample_csv';
} elseif (substr($_REQUEST['action'], 0, 13) == 'import_table_') {
  $db_table = substr($_REQUEST['action'], 13);
  $_REQUEST['action']   = 'import_table';
} elseif (substr($_REQUEST['action'], 0, 13) == 'export_table_') {
  $db_table = substr($_REQUEST['action'], 13);
  $_REQUEST['action']   = 'export_table';
}
$coa_types = load_coa_types();
$glEntry   = new \core\classes\journal();
$glEntry->journal_id = JOURNAL_ID;
// retrieve the original beginning_balances
$sql = "select c.id, beginning_balance, c.description, c.account_type
	from " . TABLE_CHART_OF_ACCOUNTS . " c inner join " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " h on c.id = h.account_id
	where h.period = 1 order by c.id";
$result = $admin->DataBase->query($sql);
$glEntry->beg_bal = array();
while (!$result->EOF) {
  $glEntry->beg_bal[$result->fields['id']] = array(
	'desc'      => $result->fields['description'],
	'type'      => $result->fields['account_type'],
	'type_desc' => $coa_types[$result->fields['account_type']]['text'],
	'beg_bal'   => $result->fields['beginning_balance'],
  );
  $glEntry->affected_accounts[$result->fields['id']] = true; // build list of affected accounts to update chart history
  $result->MoveNext();
}

$page_list = array();
$dir = @scandir(DIR_FS_MODULES);
if($dir === false) throw new \core\classes\userException("couldn't read or find directory ". DIR_FS_MODULES);
foreach ($dir as $file) {
  if (is_dir(DIR_FS_MODULES . $file) && $file <> '.' && $file <> '..') {
	if (file_exists(DIR_FS_MODULES . $file . '/' . $file . '.xml')) {
	  $page_list[$file] = array(
	    'title'     => constant('MODULE_' . strtoupper($file) . '_TITLE'),
		'structure' => load_module_xml($file),
	  );
	}
  }
}

/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_MODULES . 'phreedom/custom/pages/import_export/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'sample_xml':
  case 'sample_csv':
    $type = $_REQUEST['action']=='sample_csv' ? 'csv' : 'xml';
    header_remove();
    switch ($type) {
	  case 'xml':
	  	$output = build_sample_xml($page_list[$subject]['structure'], $db_table);
		header("Content-type: plain/txt");
		break;
	  case 'csv':
	  	$output = build_sample_csv($page_list[$subject]['structure'], $db_table);
		header("Content-type: application/csv");
		break;
	}
	header("Content-disposition: attachment; filename=sample_$db_table.$type; size=" . strlen($output));
	header('Pragma: cache');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Connection: close');
	header('Expires: ' . date('r', time()+3600));
	header('Last-Modified: ' . date('r'));
	print $output;
	exit();
  case 'import_table':
	$format = $_POST['import_format_' . $db_table];
	switch ($format) {
	  case 'xml':
		validate_upload('file_name_' . $db_table, 'text', 'xml');
    	$result = table_import_xml($page_list[$subject]['structure'], $db_table, 'file_name_' . $db_table);
	    break;
	  case 'csv':
		validate_upload('file_name_' . $db_table, 'text', 'csv');
    	$result = table_import_csv($page_list[$subject]['structure'], $db_table, 'file_name_' . $db_table);
	    break;
	}
	$_REQUEST['action'] = 'module'; // retun to module page
	break;
  case 'export_table':
	$format = $_POST['export_format_' . $db_table];
	switch ($format) {
	  case 'xml': $output = table_export_xml($page_list[$subject]['structure'], $db_table); break;
	  case 'csv': $output = table_export_csv($page_list[$subject]['structure'], $db_table); break;
	}
	if ($output) {
	  header_remove();
	  header("Content-disposition: attachment; filename=$db_table.$format; size=" . strlen($output));
	  header('Pragma: cache');
	  header('Cache-Control: public, must-revalidate, max-age=0');
	  header('Connection: close');
	  header('Expires: ' . date('r', time()+3600));
	  header('Last-Modified: ' . date('r'));
	  print $output;
	  exit();
	} else{
	  \core\classes\messageStack::add('There are no records in this database table.','caution');
	  $_REQUEST['action'] = 'module'; // retun to module page
	}
	break;
  case 'save_bb':
	\core\classes\user::validate_security($security_level, 4);
  	define('JOURNAL_ID',2);	// General Journal
	$total_amount = 0;
	$coa_values = $_POST['coa_value'];
	$index = 0;
	foreach ($glEntry->beg_bal as $coa_id => $values) {
	  if ($coa_types[$values['type']]['asset']) { // it is a debit
		$entry = $admin->currencies->clean_value($coa_values[$index]);
	  } else { // it is a credit
		$entry = -$admin->currencies->clean_value($coa_values[$index]);
	  }
	  $glEntry->beg_bal[$coa_id]['beg_bal'] = $entry;
	  $total_amount += $entry;
	  $index++;
	}
	// check to see if journal is still in balance
	$total_amount = $admin->currencies->format($total_amount);
	if ($total_amount <> 0) throw new \core\classes\userException(GL_ERROR_NO_BALANCE);
	// *************** START TRANSACTION *************************
	$admin->DataBase->transStart();
	foreach ($glEntry->beg_bal as $account => $values) {
	  $sql = "update " . TABLE_CHART_OF_ACCOUNTS_HISTORY . " set beginning_balance = {$values['beg_bal']} where period = 1 and account_id = '$account'";
	  $result = $admin->DataBase->query($sql);
	}
	$glEntry->update_chart_history_periods($period = 1); // roll the beginning balances into chart history table
	$admin->DataBase->transCommit();	// post the chart of account values
	gen_add_audit_log('Enter Beginning Balances');
	if (DEBUG) $messageStack->write_debug();
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
	// *************** END TRANSACTION *************************
	break;

  case 'import_inv':
  case 'import_po':
  case 'import_ap':
  case 'import_so':
  case 'import_ar':
  	try{
		\core\classes\user::validate_security($security_level, 4);
	    switch ($_REQUEST['action']) {
		  case 'import_inv':
			$upload_name = 'file_name_inv';
			define('JOURNAL_ID',0);
			break;
		  case 'import_po':
			$upload_name = 'file_name_po';
			define('JOURNAL_ID',4);
			define('DEF_INV_GL_ACCT',AP_DEFAULT_INVENTORY_ACCOUNT);
			break;
		  case 'import_ap':
			$upload_name = 'file_name_ap';
			define('JOURNAL_ID',6);
			define('DEF_INV_GL_ACCT',AP_DEFAULT_INVENTORY_ACCOUNT);
			break;
		  case 'import_so':
			$upload_name = 'file_name_so';
			define('JOURNAL_ID',10);
			define('DEF_INV_GL_ACCT',AR_DEFAULT_INVENTORY_ACCOUNT);
			break;
		  case 'import_ar':
			$upload_name = 'file_name_ar';
			define('JOURNAL_ID',12);
			define('DEF_INV_GL_ACCT',AR_DEFAULT_INVENTORY_ACCOUNT);
			break;
		}
		$admin->DataBase->transStart();
		// preload the chart of accounts
		$result = $admin->DataBase->query("select id from " . TABLE_CHART_OF_ACCOUNTS);
		$coa = array();
		while (!$result->EOF) {
		  $coa[] = $result->fields['id'];
		  $result->MoveNext();
		}
		$result     = $admin->DataBase->query("select start_date from " . TABLE_ACCOUNTING_PERIODS . " where period = 1");
		$first_date = $result->fields['start_date'];
		// first verify the file was uploaded ok
		validate_upload($upload_name, 'text', 'csv');
		$so_po = new \phreedom\classes\beg_bal_import();
		switch ($_REQUEST['action']) {
		  	case 'import_inv': $so_po->processInventory($upload_name);
		  	case 'import_po':
		  	case 'import_ap':
		  	case 'import_so':
		  	case 'import_ar':  $so_po->processCSV($upload_name);
		}
		\core\classes\messageStack::add(TEXT_SUCCESS . '-' . $journal_types_list[JOURNAL_ID]['text'] . '-' . TEXT_IMPORT . ': ' . sprintf(SUCCESS_IMPORT_COUNT, $so_po->line_count),'success');
		gen_add_audit_log($journal_types_list[JOURNAL_ID]['text'] . '-' . TEXT_IMPORT, $so_po->line_count);
		$admin->DataBase->transCommit();
  	}catch(Exception $e){
  		$admin->DataBase->transRollback();
  		\core\classes\messageStack::add($e->getMessage());
  	}
  	default:
}

/*****************   prepare to display templates  *************************/
$include_header   = true;
$include_footer   = true;

switch ($_REQUEST['action']) {
  case 'beg_balances':
  case 'import_inv':
  case 'import_po':
  case 'import_ap':
  case 'import_so':
  case 'import_ar':
    $include_template = 'template_beg_bal.php';
    define('PAGE_TITLE', TEXT_CHART_OF_ACCOUNTS. " - " . TEXT_BEGINNING_BALANCES);
    break;
  case 'module':
    // find the available tables based on $subject
    $include_template = 'template_modules.php';
    define('PAGE_TITLE', TEXT_IMPORT_OR_EXPORT_DATABASE_TABLES);
	break;
  default:
    $include_template = 'template_main.php';
    define('PAGE_TITLE', TEXT_IMPORT_OR_EXPORT_AND_BEGINNING_BALANCES);
}
?>