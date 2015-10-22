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
//  Path: /modules/phreepos/ajax/save_pos.php
//
$security_level = \core\classes\user::validate(SECURITY_ID_PHREEPOS);
define('JOURNAL_ID',19);
/**************  include page specific files    *********************/
require_once(DIR_FS_MODULES . 'inventory/defaults.php');
require_once(DIR_FS_MODULES . 'phreeform/defaults.php');
require_once(DIR_FS_MODULES . 'phreebooks/functions/phreebooks.php');
require_once(DIR_FS_MODULES . 'phreeform/functions/phreeform.php');
/**************   page specific initialization  *************************/
$order           = new \phreepos\classes\journal\journal_19();
define('DEF_INV_GL_ACCT',AR_DEF_GL_SALES_ACCT);//@todo
$auto_print      = false;
$total_discount  = 0;
$total_fixed     = 0;
$post_success    = false;
$tills           = new \phreepos\classes\tills();
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_MODULES . 'phreepos/custom/ajax/save_main.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/

	\core\classes\user::validate_security($security_level, 2); // security check
	$tills->get_till_info($_POST['till_id']);
	// load bill to and ship to information
	$order->short_name          = db_prepare_input(($_POST['search'] <> TEXT_SEARCH) ? $_POST['search'] : '');
	$order->bill_add_update     = isset($_POST['bill_add_update']) ? $_POST['bill_add_update'] : 0;
	$order->bill_acct_id        = db_prepare_input($_POST['bill_acct_id']);
	$order->bill_address_id     = db_prepare_input($_POST['bill_address_id']);
	$order->bill_primary_name   = db_prepare_input(($_POST['bill_primary_name']   <> TEXT_NAME_OR_COMPANY)   ? $_POST['bill_primary_name'] : '', true);
	$order->bill_contact        = db_prepare_input(($_POST['bill_contact']        <> TEXT_ATTENTION)        ? $_POST['bill_contact'] : '', ADDRESS_BOOK_CONTACT_REQUIRED);
	$order->bill_address1       = db_prepare_input(($_POST['bill_address1']       <> TEXT_ADDRESS1)       ? $_POST['bill_address1'] : '', ADDRESS_BOOK_ADDRESS1_REQUIRED);
	$order->bill_address2       = db_prepare_input(($_POST['bill_address2']       <> TEXT_ADDRESS2)       ? $_POST['bill_address2'] : '', ADDRESS_BOOK_ADDRESS2_REQUIRED);
	$order->bill_city_town      = db_prepare_input(($_POST['bill_city_town']      <> TEXT_CITY_TOWN)      ? $_POST['bill_city_town'] : '', ADDRESS_BOOK_CITY_TOWN_REQUIRED);
	$order->bill_state_province = db_prepare_input(($_POST['bill_state_province'] <> TEXT_STATE_PROVINCE) ? $_POST['bill_state_province'] : '', ADDRESS_BOOK_STATE_PROVINCE_REQUIRED);
	$order->bill_postal_code    = db_prepare_input(($_POST['bill_postal_code']    <> TEXT_POSTAL_CODE)    ? $_POST['bill_postal_code'] : '', ADDRESS_BOOK_POSTAL_CODE_REQUIRED);
	$order->bill_country_code   = db_prepare_input($_POST['bill_country_code']);
	$order->bill_telephone1     = db_prepare_input(($_POST['bill_telephone1']     <> TEXT_TELEPHONE)     ? $_POST['bill_telephone1'] : '', ADDRESS_BOOK_TELEPHONE1_REQUIRED);
	$order->bill_email          = db_prepare_input(($_POST['bill_email'] <> TEXT_EMAIL) ? $_POST['bill_email'] : '', ADDRESS_BOOK_EMAIL_REQUIRED);
	// load journal main data
	$order->id                  = ''; // all POS are new
	$order->journal_id          = JOURNAL_ID;
	$order->post_date           = date('Y-m-d');
	$order->period              = CURRENT_ACCOUNTING_PERIOD;
	$order->save_payment        = '1'; // save payment (if encryption enabled)
	$order->purchase_invoice_id = '';  // Assume new POS
	$order->store_id            = $tills->store_id;
	if ($order->store_id == '') $order->store_id = 0;
	$order->description         = TEXT_POINT_OF_SALE;
	$order->admin_id            = $_SESSION['admin_id'];
	$order->rep_id              = db_prepare_input($_POST['rep_id']);
	$order->gl_acct_id          = $tills->gl_acct_id;
	$order->item_count          = db_prepare_input($_POST['item_count']);
	// currency values (convert to DEFAULT_CURRENCY to store in db)
	$order->currencies_code     = db_prepare_input($_POST['currencies_code']);
	$order->currencies_value    = db_prepare_input($_POST['currencies_value']);
	$order->subtotal            = $admin->currencies->clean_value(db_prepare_input($_POST['subtotal']),  $order->currencies_code) / $order->currencies_value; // don't need unless for verification
	$order->disc_gl_acct_id     = db_prepare_input($_POST['disc_gl_acct_id']);
	$order->discount            = $admin->currencies->clean_value(db_prepare_input($_POST['discount']),  $order->currencies_code) / $order->currencies_value;
	//$order->disc_percent        = ($order->subtotal) ? (1-(($order->subtotal-$order->discount)/$order->subtotal)) : 0;
	$order->sales_tax           = $admin->currencies->clean_value(db_prepare_input($_POST['sales_tax']), $order->currencies_code) / $order->currencies_value;
	$order->total_amount        = $admin->currencies->clean_value(db_prepare_input($_POST['total']),     $order->currencies_code) / $order->currencies_value;
	$order->pmt_recvd           = $admin->currencies->clean_value(db_prepare_input($_POST['pmt_recvd']), $order->currencies_code) / $order->currencies_value;
	$order->bal_due             = $admin->currencies->clean_value(db_prepare_input($_POST['bal_due']),   $order->currencies_code) / $order->currencies_value;
	// load item row data
	$x = 1;
	while (isset($_POST['pstd_' . $x])) { // while there are item rows to read in
	  	if (!$_POST['pstd_' . $x]) {
	    	$x++;
	    	continue;
	  	}
	  	$full_price  = $admin->currencies->clean_value(db_prepare_input($_POST['full_' . $x]), $order->currencies_code) / $order->currencies_value;
	  	$fixed_price = $admin->currencies->clean_value(db_prepare_input($_POST['fixed_price_' . $x]), $order->currencies_code) / $order->currencies_value;
	  	$price       = $admin->currencies->clean_value(db_prepare_input($_POST['price_' . $x]), $order->currencies_code) / $order->currencies_value;
		$wtprice     = $admin->currencies->clean_value(db_prepare_input($_POST['wtprice_' . $x]), $order->currencies_code) / $order->currencies_value;
		$qty		   = $admin->currencies->clean_value(db_prepare_input($_POST['pstd_' . $x]), $order->currencies_code);
	  	$disc        = db_prepare_input($_POST['disc_' . $x]);
	  	$sku         = db_prepare_input($_POST['sku_' . $x]);
	  	if ($fixed_price == 0 ) $fixed_price = $price;
		// Error check some input fields
	  	if ($_POST['acct_' . $x] == "") throw new \core\classes\userException(TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . TEXT_GL_ACCOUNT);
	  	//check if discount per row doens't exceed the max
	  	if($tills->max_discount <> ''){
	  		$wt_total_fixed += $fixed_price * ($wtprice / $price)* $qty;
	  		$total_fixed += $fixed_price * $qty;
	  		if( $price < $fixed_price ){ //the price in lower than the price set in the pricesheet
	  			$total_discount += ($fixed_price * $qty) - ($price * $qty );
	  			if($disc >= $tills->max_discount)  throw new \core\classes\userException(sprintf(EXCEED_MAX_DISCOUNT_SKU, $tills->max_discount, $sku ));
	  		}
	  	}

	    $order->item_rows[] = array(
		  'id'        => db_prepare_input($_POST['id_' . $x]),
	      'sku'       => ($_POST['sku_' . $x] == TEXT_SEARCH) ? '' : $sku,
		  'pstd'      => $qty,
		  'desc'      => db_prepare_input($_POST['desc_' . $x]),
	      'total'     => $admin->currencies->clean_value(db_prepare_input($_POST['total_' . $x]), $order->currencies_code) / $order->currencies_value,
		  'full'      => $full_price,
		  'acct'      => db_prepare_input($_POST['acct_' . $x]),
		  'tax'       => db_prepare_input($_POST['tax_' . $x]),
	      'serial'    => db_prepare_input($_POST['serial_' . $x]),
/*rest is not used
		  'price'     => $price,
		  'weight'    => db_prepare_input($_POST['weight_' . $x]),
		  'stock'     => db_prepare_input($_POST['stock_' . $x]),
		  'inactive'  => db_prepare_input($_POST['inactive_' . $x]),
		  'lead_time' => db_prepare_input($_POST['lead_' . $x]),*/
	    );
		$x++;
	}//print($total_discount.'+'.$order->discount);
	//check if the total discount doesn;t exceed the max
	if($tills->max_discount <> ''){
		//calculate the discount percent used by all rows, use basis set in the phreepos admin ( subtotal or total.)
		if(PHREEPOS_DISCOUNT_OF){//total
		//print( round((1-(($wt_total_fixed - ($total_discount + $order->discount) )/$wt_total_fixed))* 100,1) .'>='.  round($tills->max_discount,1));
			if( round((1-(($wt_total_fixed - ($total_discount + $order->discount) )/$wt_total_fixed))* 100,1) >=  round($tills->max_discount,1)){
	  			throw new \core\classes\userException(sprintf(EXCEED_MAX_DISCOUNT, $tills->max_discount));
	  		}
		}else{//subtotal
			if( round((1-(($total_fixed - ($total_discount + $order->discount) )/$total_fixed))* 100,1) >=  round($tills->max_discount,1)){
	  			throw new \core\classes\userException(sprintf(EXCEED_MAX_DISCOUNT, $tills->max_discount));
	  		}
		}
	}
	// load the payments
	$x   = 1;
	$tot_paid = 0;
	while (isset($_POST['meth_' . $x])) { // while there are item rows to read in
	  	if (!$_POST['meth_' . $x]) {
	    	$x++;
			continue;
	  	}
	  	$pmt_meth = $_POST['meth_' . $x];
	  	$pmt_amt  = $admin->currencies->clean_value(db_prepare_input($_POST['pmt_' . $x]), $order->currencies_code) / $order->currencies_value;
	  	$tot_paid += $pmt_amt;
	  	$order->pmt_rows[] = array(
		  'meth' => db_prepare_input($_POST['meth_' . $x]),
		  'pmt'  => $pmt_amt,
		  'f0'   => db_prepare_input($_POST['f0_' . $x]),
		  'f1'   => db_prepare_input($_POST['f1_' . $x]),
		  'f2'   => db_prepare_input($_POST['f2_' . $x]),
		  'f3'   => db_prepare_input($_POST['f3_' . $x]),
		  'f4'   => db_prepare_input($_POST['f4_' . $x]),
	  	  'f5'   => db_prepare_input($_POST['f5_' . $x]),
	  	  'f6'   => db_prepare_input($_POST['f6_' . $x]),
	  	);
	  	// initialize payment methods
	  	// preset some post variables to fake out the payment methods,
	  	// the following lines should be in place because the payment module uses them to return journal lines.
	  	$_POST[$pmt_meth . '_field_0'] = $_POST['f0_' . $x];
	  	$_POST[$pmt_meth . '_field_1'] = $_POST['f1_' . $x];
	  	$_POST[$pmt_meth . '_field_2'] = $_POST['f2_' . $x];
	  	$_POST[$pmt_meth . '_field_3'] = $_POST['f3_' . $x];
	  	$_POST[$pmt_meth . '_field_4'] = $_POST['f4_' . $x];
	  	$_POST[$pmt_meth . '_field_5'] = $_POST['f5_' . $x];
	  	$_POST[$pmt_meth . '_field_6'] = $_POST['f6_' . $x];
	  	$x++;
	}
	$order->shipper_code = $pmt_meth;  // store last payment method in shipper_code field
    // adding the rounding of line
    $order->rounding_amt 		= $admin->currencies->clean_value(db_prepare_input($_POST['rounded_of']), $order->currencies_code);
    $order->rounding_gl_acct_id = $tills->rounding_gl_acct_id;
	// check for errors (address fields)
	if (PHREEPOS_REQUIRE_ADDRESS) {
		if (!$order->bill_acct_id && !$order->bill_add_update) {
			throw new \core\classes\userException(POS_ERROR_CONTACT_REQUIRED);
	  	} else {
		    if ($order->bill_primary_name   === false) throw new \core\classes\userException(TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . TEXT_NAME_OR_COMPANY);
	    	if ($order->bill_contact        === false) throw new \core\classes\userException(TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . TEXT_ATTENTION);
	    	if ($order->bill_address1       === false) throw new \core\classes\userException(TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . TEXT_ADDRESS1);
	    	if ($order->bill_address2       === false) throw new \core\classes\userException(TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . TEXT_ADDRESS2);
	    	if ($order->bill_city_town      === false) throw new \core\classes\userException(TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . TEXT_CITY_TOWN);
	    	if ($order->bill_state_province === false) throw new \core\classes\userException(TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . TEXT_STATE_PROVINCE);
	    	if ($order->bill_postal_code    === false) throw new \core\classes\userException(TEXT_A_REQUIRED_FIELD_HAS_BEEN_LEFT_BLANK_FIELD . ': ' . TEXT_POSTAL_CODE);
	  	}
	}
	// Payment errors
	if ($admin->currencies->clean_value(db_prepare_input($_POST['bal_due']),  $order->currencies_code) / $order->currencies_value <> $admin->currencies->clean_value(0)) {
	  	throw new \core\classes\userException("The total payment was not equal to the order total!<br/> $tot_paid  +  $order->rounding_amt + $order->total_amount");
	}
	if(substr($_REQUEST['action'],0,5) == 'print') {
		$order->printed = true;
	}else{
		$order->printed = FALSE;
	}
	// End of error checking, process the order
	// Post the order
	if (!$order->item_rows) throw new \core\classes\userException( GL_ERROR_NO_ITEMS);
	$order->post_ordr($_REQUEST['action']);	// Post the order class to the db
	gen_add_audit_log(TEXT_POINT_OF_SALE . ' - ' . ($_POST['id'] ? TEXT_EDIT : TEXT_ADD), $order->purchase_invoice_id, $order->total_amount);

	if($order->printed){
		//print
		$result = $admin->DataBase->query("select id from " . TABLE_PHREEFORM . " where doc_group = '{$order->popup_form_type}' and doc_ext = 'frm'");
	    if ($result->fetch(\PDO::FETCH_NUM) == 0) throw new \core\classes\userException("No form was found for this type ({$order->popup_form_type}). ");
		if ($result->fetch(\PDO::FETCH_NUM) > 1) {
		   	if(DEBUG) $massage .= "More than one form was found for this type ({$order->popup_form_type}). Using the first form found.";
		}
		$rID    = $result->fields['id']; // only one form available, use it
	  	$report = get_report_details($rID);
	  	$title  = $report->title;
	  	$report->datedefault = 'a';
	  	$report->xfilterlist[0]->fieldname = 'journal_main.id';
	  	$report->xfilterlist[0]->default   = 'EQUAL';
	  	$report->xfilterlist[0]->min_val   = $order->id;
	  	$output = BuildForm($report, $delivery_method = 'S'); // force return with report
	  	if ($output === true) {
	  		if(DEBUG) $massage .='direct printing fault.';
	  	} else if (!is_array($output) ){// if it is a array then it is not a sequential report
	    	// fetch the receipt and prepare to print
	  		$receipt_data = str_replace("\r", "", addslashes($output)); // for javascript multi-line
	  		foreach (explode("\n",$receipt_data) as $value){
	  			if(!empty($value)){
		  				$xml .= "<receipt_data>\n";
		  				$xml .= "\t" . xmlEntry("line", $value);
		  				$xml .= "</receipt_data>\n";
		  			}
	  		}
	  	}
	}
						$xml .= "\t" . xmlEntry("action",			$_REQUEST['action']);
						$xml .= "\t" . xmlEntry("open_cash_drawer", $order->opendrawer);
						$xml .= "\t" . xmlEntry("order_id",		 	$order->id);
if ($massage)  	 		$xml .= "\t" . xmlEntry("massage", 			$massage);
echo createXmlHeader() . $xml . createXmlFooter();
ob_end_flush();
session_write_close();
die;
?>