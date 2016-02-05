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
//  Path: /modules/inventory/pages/admin/pre_process.php
//
$security_level = \core\classes\user::validate(SECURITY_ID_CONFIGURATION);
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
require_once(DIR_FS_MODULES . 'phreedom/functions/phreedom.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
require_once(DIR_FS_WORKING . 'functions/inventory.php');
/**************   page specific initialization  *************************/
$cog_type = explode(',', COG_ITEM_TYPES);
$tabs     = new \inventory\classes\tabs();
$fields   = new \inventory\classes\fields();

/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'save':
	\core\classes\user::validate_security($security_level, 3); // security check
	// save general tab
	foreach ($admin->classes['inventory']->keys as $key => $default) {
	  $field = strtolower($key);
      if (isset($_POST[$field])) $admin->DataBase->write_configure($key, $_POST[$field]);
    }
	gen_redirect(html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL'));
	\core\classes\messageStack::add(INVENTORY_CONFIG_SAVED,'success');
    break;
  case 'delete':
	\core\classes\user::validate_security($security_level, 4); // security check
    $subject = $_POST['subject'];
    $id      = $_POST['rowSeq'];
	if (!$subject || !$id) break;
    $$subject->btn_delete($id);
	break;
  case 'inv_hist_test':
  case 'inv_hist_fix'://@todo check 4.0 names of methods
	\core\classes\user::validate_security($security_level, 3); // security check
	$cnt = 0;
	$repair = array();
	$journal_repost = array();
	$owed_array = array();
	$precision = 1 / pow(10, $currencies->currencies[DEFAULT_CURRENCY]['decimal_precise'] + 1);
	validate_security($security_level, 3); // security check
	$result = $admin->DataBase->query("SELECT sku, qty FROM " . TABLE_INVENTORY_COGS_OWED);
	while (!$result->EOF) {
	  	$owed_array[$result->fields['sku']] += $result->fields['qty'];
		$cnt++;
	  	$result->MoveNext();
	}
	$journal_repost = $owed_array;
	$result = $admin->DataBase->query("SELECT b.sku, b.totals, c.remaining, (c.Startbalance + b.totals) as balance FROM ( SELECT sku, SUM(totals) AS totals FROM ( 
		SELECT ref_id, sku, CASE WHEN f.gl_type = 'soo' THEN 0 WHEN f.gl_type = 'poo' THEN 0 WHEN g.journal_id = '7' THEN - f.qty WHEN g.journal_id = '12' THEN - f.qty WHEN g.journal_id = '19' THEN - f.qty ElSE f.qty END AS totals FROM ".TABLE_JOURNAL_ITEM." AS f JOIN ".TABLE_JOURNAL_MAIN." as g ON f.ref_id = g.id WHERE f.sku <> '') AS a GROUP BY a.sku ) AS b JOIN ( 
		SELECT sku, SUM(d.remaining) AS remaining , sum(d.Startbalance) AS Startbalance FROM ( 
		SELECT sku, remaining, CASE WHEN ref_id = '0' THEN qty ELSE 0 END AS Startbalance FROM " . TABLE_INVENTORY_HISTORY . ") AS d GROUP BY d.sku ) AS c ON b.sku = c.sku WHERE (c.Startbalance + b.totals) != c.remaining");
	// find inventory items where qty in journals are different from that in inventory history.
	while (!$result->EOF) {
		$remaining = $result->fields['remaining'];
		$totals    = $result->fields['totals'];
		if (($remaining - $totals) < $precision) break;
		if (!isset($journal_repost[$result->fields['sku']])) {
			$repair[$result->fields['sku']] += $totals;
			$journal_repost[$result->fields['sku']] = $result->fields['sku'];
			\core\classes\messageStack::add(sprintf("The qty's for sku %s are not the same as the journal history would indicate. On stock are %s but should be %s", $result->fields['sku'], $remaining, $totals), 'error');
//			print(sprintf("The qty's for sku %s are not the same as the journal history would indicate. On stock are %s but should be %s", $result->fields['sku'], $remaining, $totals));
//			ob_flush();
			$cnt++;
		}
		$result->MoveNext();
	}
	// fetch the inventory items that we track COGS and get qty on hand
	$result = $admin->DataBase->query("SELECT sku, quantity_on_hand FROM " . TABLE_INVENTORY . " WHERE inventory_type in ('" . implode("', '", $cog_type) . "') AND last_journal_date <> '0000-00-00 00:00:00' ORDER BY sku");
	// for each item, find the history remaining Qty's
	while (!$result->EOF) {
		$on_hand = round($result->fields['quantity_on_hand'], $currencies->currencies[DEFAULT_CURRENCY]['decimal_precise']);
	    $inv_hist 	= $admin->DataBase->query("SELECT SUM(remaining) AS remaining FROM " . TABLE_INVENTORY_HISTORY . " WHERE sku = '{$result->fields['sku']}'");
		$remaining  = round($inv_hist->fields['remaining'], $currencies->currencies[DEFAULT_CURRENCY]['decimal_precise']);
		$owed 		= $owed_array[$result->fields['sku']] ? $owed_array[$result->fields['sku']] : 0;
		// check with inventory history
		if ($on_hand <> ($remaining - $owed)) {
		  	$repair[$result->fields['sku']] = $remaining - $owed;
		  	if ($_REQUEST['action'] <> 'inv_hist_fix') {
		    	\core\classes\messageStack::add(sprintf(INV_TOOLS_OUT_OF_BALANCE, $result->fields['sku'], $on_hand, ($remaining - $owed)), 'error');
		    	$cnt++;
		  	}
	  	} else if ($on_hand <> $result->fields['quantity_on_hand']) { // check for quantity on hand not rounded properly
	    	$repair[$result->fields['sku']] = $on_hand;
			if ($_REQUEST['action'] <> 'inv_hist_fix') {
		  		\core\classes\messageStack::add(sprintf(INV_TOOLS_STOCK_ROUNDING_ERROR, $result->fields['sku'], $result->fields['quantity_on_hand'], $on_hand), 'error');
		  		$cnt++;
			}
	  	}
	  	$result->MoveNext();
	}
	// flag the differences
	if ($_REQUEST['action'] == 'inv_hist_fix') { // start repair
	  	$result = $admin->DataBase->query("UPDATE " . TABLE_INVENTORY_HISTORY . " SET remaining = 0 WHERE remaining < " . $precision); // remove rounding errors
	  	if (sizeof($repair) > 0) {
	    	foreach ($repair as $key => $value) {
		  		$admin->DataBase->query("UPDATE " . TABLE_INVENTORY . " SET quantity_on_hand = $value WHERE sku = '$key'");
		  		\core\classes\messageStack::add(sprintf(INV_TOOLS_BALANCE_CORRECTED, $key, $value), 'success');
			}
	  	}
	  	if (sizeof($journal_repost) > 0) {
	  		foreach ($journal_repost as $key => $value) {
	  			$result = $admin->DataBase->query("SELECT m.id FROM journal_main m join journal_item i ON m.id = i.ref_id WHERE i.sku = '{$value}' ORDER BY m.post_date, m.id");
	  			$admin->DataBase->transStart();
	  			while (!$result->EOF) {
	  				$gl_entry = new journal($result->fields['id']);
	  				$gl_entry->remove_cogs_rows(); // they will be regenerated during the re-post
	  				if (!$gl_entry->Post('edit', true)) {
	  					$admin->DataBase->transRollback();
	  					\core\classes\messageStack::add('<br /><br />Failed Re-posting the journals, try a smaller range. The record that failed was # '.$gl_entry->id,'error');
	  					break;
	  				}
	  				$result->MoveNext();
	  			}
	  			$admin->DataBase->transCommit();
	  		}
	  	}
	}
	if ($cnt == 0) \core\classes\messageStack::add(INV_TOOLS_IN_BALANCE, 'success');
	$default_tab_id = 'tools';
    break;
	break;
  case 'inv_on_order_fix':
	\core\classes\user::validate_security($security_level, 3); // security check
    // fetch the inventory items that we track COGS and get qty on SO, PO
	$cnt = 0;
	$fix = 0;
	$inv = array();
	$po  = array();
	$so  = array();
	$items = $admin->DataBase->query("select id, sku, quantity_on_order, quantity_on_sales_order from " . TABLE_INVENTORY . "
	  where inventory_type in ('" . implode("', '", $cog_type) . "') order by sku");
	while(!$items->EOF) {
	  $inv[$items['sku']] = array(
	    'id'     => $items['id'],
	    'qty_so' => $items['quantity_on_sales_order'],
		'qty_po' => $items['quantity_on_order'],
	  );
	  $items->MoveNext();
	}
	// fetch the PO's and SO's balances
	$po = inv_status_open_orders($journal_id =  4, $gl_type = 'poo');
	$so = inv_status_open_orders($journal_id = 10, $gl_type = 'soo');
	// compare the results and repair
	if (sizeof($inv) > 0) foreach ($inv as $sku => $balance) {
	  if (!isset($po[$sku])) $po[$sku] = 0;
	  if ($balance['qty_po'] <> $po[$sku]) {
	    \core\classes\messageStack::add(sprintf(INV_TOOLS_PO_ERROR, $sku, $balance['qty_po'], $po[$sku]), 'caution');
		$admin->DataBase->query("update " . TABLE_INVENTORY . " set quantity_on_order = " . $po[$sku] . " where id = " . $balance['id']);
		$fix++;
	  }
	  if (!isset($so[$sku])) $so[$sku] = 0;
	  if ($balance['qty_so'] <> $so[$sku]) {
	    \core\classes\messageStack::add(sprintf(INV_TOOLS_SO_ERROR, $sku, $balance['qty_so'], $so[$sku]), 'caution');
		$admin->DataBase->query("update " . TABLE_INVENTORY . " set quantity_on_sales_order = " . $so[$sku] . " where id = " . $balance['id']);
	    $fix++;
	  }
	  $cnt++;
	}
	\core\classes\messageStack::add(sprintf(INV_TOOLS_SO_PO_RESULT, $cnt, $fix),'success');
	gen_add_audit_log(sprintf(INV_TOOLS_AUTDIT_LOG_SO_PO,  $cnt), 'Fixed: ' . $fix);
	$default_tab_id = 'tools';
    break;
  default:
}

/*****************   prepare to display templates  *************************/
// build some general pull down arrays
$sel_yes_no = array(
 array('id' => '0', 'text' => TEXT_NO),
 array('id' => '1', 'text' => TEXT_YES),
);
$cost_methods = array(
 array('id' => 'f', 'text' => TEXT_FIFO),
 array('id' => 'l', 'text' => TEXT_LIFO),
 array('id' => 'a', 'text' => TEXT_AVERAGE),
);
$sel_item_cost = array(
 array('id' => '0',  'text' => TEXT_NO),
 array('id' => 'PO', 'text' => TEXT_PURCHASE_ORDERS),
 array('id' => 'PR', 'text' => TEXT_PURCHASES),
);
$sel_sales_tax = ord_calculate_tax_drop_down('c');
$sel_purch_tax = ord_calculate_tax_drop_down('v');
// some pre-defined gl accounts
$cog_chart = gen_coa_pull_down(2, false, true, false, $restrict_types = array(32)); // cogs types only
$inc_chart = gen_coa_pull_down(2, false, true, false, $restrict_types = array(30)); // income types only
$inv_chart = gen_coa_pull_down(2, false, true, false, $restrict_types = array(4, 34)); // inv, expenses types only

$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', TEXT_INVENTORY_ADMINISTRATION);
?>