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
//  Path: /modules/phreebooks/pages/popup_convert/pre_process.php
//
$security_level = \core\classes\user::validate(0, true);
/**************  include page specific files    *********************/

/**************   page specific initialization  *************************/
$id     = (isset($_GET['oID'])    ? $_GET['oID']    : $_POST['id']);
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/popup_convert/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }

/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  case 'save':
  	try{
	    $selection = $_POST['conv_type'];
		$so_num    = $_POST['so_num'];
		$inv_num   = $_POST['inv_num'];
		$order     = new \core\classes\journal($id);
		switch ($order->journal_id) {
		  	case  3:
		    	$order->journal_id   = 04;
				$search_gl_type      = 'poo';
				$purchase_invoice_id = $so_num;
				break;
		  	default:
		  	case  9:
				if ($selection == 'inv') { // invoice
			  		$order->journal_id   = 12;
			  		$search_gl_type      = 'soo';
			  		$purchase_invoice_id = $inv_num;
				} else { // sales order
			  		$order->journal_id   = 10;
			  		$search_gl_type      = 'soo';
			  		$purchase_invoice_id = $so_num;
				}
				break;
		}
		// change some values to make it look like a new sales order/invoice
		$order->id            = '';
		$order->post_date     = date('Y-m-d'); // make post date today
		$order->period        = \core\classes\DateTime::period_of_date($order->post_date);
		$order->terminal_date = $order->post_date;
		$order->journal_main_array['id']          = $order->id;
		$order->journal_main_array['journal_id']  = $order->journal_id;
		$order->journal_main_array['post_date']   = $order->post_date;
		$order->journal_main_array['period']      = $order->period;
		$order->journal_main_array['description'] = sprintf(TEXT_ARGS_ENTRY, $journal_types_list[$order->journal_id]['text']);
		for ($i = 0; $i < sizeof($order->journal_rows); $i++) {
		  	$order->journal_rows[$i]['id']                = '';
		  	$order->journal_rows[$i]['so_po_item_ref_id'] = '';
		  	$order->journal_rows[$i]['post_date']         = $order->post_date;
		  	if ($order->journal_rows[$i]['gl_type'] == $search_gl_type) $order->journal_rows[$i]['gl_type'] = $order->gl_type;
		}
		// ***************************** START TRANSACTION *******************************
		$admin->DataBase->beginTransaction();
		if ($purchase_invoice_id) {
		  	$order->journal_main_array['purchase_invoice_id'] = $purchase_invoice_id;
		  	$order->purchase_invoice_id = $purchase_invoice_id;
		} else {
		  	$order->purchase_invoice_id = '';
		  	$order->validate_purchase_invoice_id();
		}
		$order->Post('insert');
	    if ($order->purchase_invoice_id == '') {
		  	$order->increment_purchase_invoice_id();
		}
		gen_add_audit_log($journal_types_list[$order->journal_id]['text'] . ' - ' . TEXT_ADD, $order->purchase_invoice_id, $order->total_amount);
		// set the closed flag on the quote
		$result = $admin->DataBase->query("update " . TABLE_JOURNAL_MAIN . " set closed = '1' where id = " . $id);
		$admin->DataBase->commit();	// finished successfully
		// ***************************** END TRANSACTION *******************************
  	}catch(Exception $e){
  		$admin->DataBase->rollBack();
  		\core\classes\messageStack::add($e->getMessage());
  	}
	break;
  default:
}

/*****************   prepare to display templates  *************************/
$result       = $admin->DataBase->query("select journal_id from " . TABLE_JOURNAL_MAIN . " where id = " . $id);
$jID          = $result->fields['journal_id'];
$account_type = ($jID == 3 ? 'v' : 'c');

$include_header   = false;
$include_footer   = false;
$include_template = 'template_main.php';
define('PAGE_TITLE', $jID == 3 ? TEXT_CONVERT_TO_PURCHASE_ORDER : TEXT_CONVERT_TO_SALES_ORDER);

?>