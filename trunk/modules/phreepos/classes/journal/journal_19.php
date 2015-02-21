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
//  Path: /modules/phreepos/classes/journal/journal_19.php
//
// POS Journal
namespace phreepos\classes\journal;
class journal_19 extends \core\classes\journal {
	public $id					= '';
	public $save_payment        = false;
    public $closed 				= '0';
    public $journal_id          = 19;
    public $gl_type             = GL_TYPE;
    public $currencies_code     = DEFAULT_CURRENCY;
    public $currencies_value    = '1.0';
    public $gl_disc_acct_id     = AR_DISCOUNT_SALES_ACCOUNT;
    public $bill_acct_id		= '';
    public $bill_address_id		= '';
    public $bill_add_update		= false;
    public $bill_primary_name   = TEXT_NAME_OR_COMPANY;
    public $bill_contact        = TEXT_ATTENTION;
    public $bill_address1       = TEXT_ADDRESS1;
    public $bill_address2       = TEXT_ADDRESS2;
    public $bill_city_town      = TEXT_CITY_TOWN;
    public $bill_state_province = TEXT_STATE_PROVINCE;
    public $bill_postal_code    = TEXT_POSTAL_CODE;
    public $bill_country_code   = COMPANY_COUNTRY;
    public $bill_telephone1		= '';
    public $bill_email			= '';
    public $journal_rows        = array();	// initialize ledger row(s) array
	public $opendrawer			= false;
	public $printed				= false;
	public $post_date			= '';
	public $store_id			= 0;
	public $till_id				= 0;
	public $rep_id				= 0;
	public $subtotal			= 0;
	public $disc_percent		= 0;
	public $discount			= 0;
	public $sales_tax			= 0;
	public $rounded_of			= 0;
	public $total_amount		= 0;
	public $pmt_recvd			= 0;
	public $bal_due				= 0;
	public $shipper_code		= '';
	public $so_po_ref_id		= '';

    public function __construct($id = '') {
        $this->purchase_invoice_id = 'DP' . date('Ymd');
        $this->gl_acct_id = $_SESSION['admin_prefs']['def_cash_acct'] ? $_SESSION['admin_prefs']['def_cash_acct'] : AR_SALES_RECEIPTS_ACCOUNT;
		$this->store_id	  = isset($_SESSION['admin_prefs']['def_store_id']) ? $_SESSION['admin_prefs']['def_store_id'] : 0;
        parent::__construct($id);
	}

	function post_ordr($action) {
		global $admin, $messageStack;
		$debit_total  = 0;
		$credit_total = 0;
	    $credit_total += $this->add_item_journal_rows(); // read in line items and add to journal row array
	    $credit_total += $this->add_tax_journal_rows(); // fetch tax rates for tax calculation
	    $debit_total  += $this->add_discount_journal_row(); // put discount into journal row array
	    $credit_total += $this->add_rounding_journal_rows($credit_total - $debit_total);	// fetch rounding of
	    //$this->adjust_total($credit_total - $debit_total);
	    $this->add_payment_row();
	    $debit_total  += $this->add_total_journal_row(); // put total value into ledger row array
		$this->journal_main_array = $this->build_journal_main_array(); // build ledger main record

		// ***************************** START TRANSACTION *******************************
		$messageStack->debug("\n  started order post purchase_invoice_id = " . $this->purchase_invoice_id . " and id = " . $this->id);
		$admin->DataBase->transStart();
		// *************  Pre-POST processing *************
		// add/update address book
		if ($this->bill_add_update) { // billing address
			$this->bill_acct_id = $this->add_account($this->account_type . 'b', $this->bill_acct_id, $this->bill_address_id);
			if (!$this->bill_acct_id) throw new \core\classes\userException('no customer was selected');
		}
		// ************* POST journal entry *************
		$this->validate_purchase_invoice_id();
		$this->Post($this->id ? 'edit' : 'insert',true);
		// ************* post-POST processing *************
		if (!$this->increment_purchase_invoice_id()) throw new \core\classes\userException('invoice number can not be incrementedt');
		// cycle through the payments
		foreach ($this->pmt_rows as $pay_method) {
	        $method   = $pay_method['meth'];
	        $messageStack->debug("\n encryption =".ENABLE_ENCRYPTION." save_payment ={$this->save_payment} enable_encryption={$admin->classes['payment']->methods[$method]->enable_encryption}");
	        if (ENABLE_ENCRYPTION && $this->save_payment && $admin->classes['payment']->methods[$method]->enable_encryption !== false) {
	            $this->encrypt_payment($pay_method);
	        }
	        $admin->classes['payment']->methods[$method]->before_process();
	    }
		$messageStack->debug("\n  committed order post purchase_invoice_id = {$this->purchase_invoice_id} and id = {$this->id}\n\n");
		$admin->DataBase->transCommit();
		// ***************************** END TRANSACTION *******************************
		$messageStack->add('Successfully posted ' . TEXT_POINT_OF_SALE . ' Ref # ' . $this->purchase_invoice_id, 'success');
		return true;
	}

  	function add_total_journal_row() {
  		$this->journal_rows[] = array(
			'gl_type'          => 'ttl',
			'debit_amount'     => $this->total_amount,
			'description'      => TEXT_POINT_OF_SALE . '-' . TEXT_TOTAL,
			'gl_account'       => $this->gl_acct_id,
			'post_date'        => $this->post_date,
  		);
  	}

  	function add_payment_row() {
		global $payment_modules, $messageStack;
	  	$total = 0;
	  	for ($i = 0; $i < count($this->pmt_rows); $i++) {
			if ($this->pmt_rows[$i]['pmt']) { // make sure the payment line is set and not zero
		  		$desc   = TEXT_POINT_OF_SALE . '-' . TEXT_PAYMENT;
		  		$method = $this->pmt_rows[$i]['meth'];
		  		if ($method) {
		  			$pay_meth = "\payment\methods\\$method\\$method";
		    		$$method    = new $pay_meth;
		    		$deposit_id = $$method->def_deposit_id ? $$method->def_deposit_id : ('DP' . date('Ymd'));
					$desc       = $this->journal_id . ':' . $method . ':' . $$method->payment_fields;
					$gl_acct_id = $$method->pos_gl_acct;
					if ($this->opendrawer == false) $this->opendrawer = $$method->open_pos_drawer;
					$messageStack->debug("\n payment type = ".$method." gl account id = " .$gl_acct_id. " open drawer = ".$$method->open_pos_drawer);
		  		}
		  		$total     += $this->pmt_rows[$i]['pmt'];
		  		if ($total > $this->total_amount) { // change was returned, adjust amount received for post
					$this->pmt_rows[$i]['pmt'] = $this->pmt_rows[$i]['pmt'] - ($total - $this->total_amount);
		    		$total = $this->total_amount;
		  		}
		  		$desc = ($this->pmt_rows[$i]['desc']) ? $this->pmt_rows[$i]['desc'] : $desc;
		  		$this->journal_rows[] = array(
					'gl_type'          => 'pmt',
					'debit_amount'     => $this->pmt_rows[$i]['pmt'],
					'description'      => $desc,
					'gl_account'       => $gl_acct_id,
					'serialize_number' => $deposit_id,
					'post_date'        => $this->post_date,
		  		);
			}
	  	}
	  	$this->journal_rows[] = array(
			'gl_type'          => 'tpm',
			'credit_amount'    => $total,
			'description'      => 'payment',
			'gl_account'       => $this->gl_acct_id,
			'post_date'        => $this->post_date,
  		);
	  	return $total;
  	}

  function add_discount_journal_row() { // put discount into journal row array
	  if ($this->discount <> 0) {
		$this->journal_rows[] = array(
		  'qty'                     => '1',
		  'gl_type'                 => 'dsc',		// code for discount charges
		  'debit_amount' 			=> $this->discount,
		  'description'             => TEXT_POINT_OF_SALE . '-' . TEXT_DISCOUNT,
		  'gl_account'              => $this->disc_gl_acct_id,
		  'taxable'                 => '0',
		  'post_date'               => $this->post_date,
		);
	  }
	  return $this->discount;
  }

  function add_item_journal_rows() {	// read in line items and add to journal row array
	  $total = 0;
	  for ($i = 0; $i < count($this->item_rows); $i++) {
		if ($this->item_rows[$i]['pstd']) { // make sure the quantity line is set and not zero
		  $this->journal_rows[] = array(
			'id'                      => $this->item_rows[$i]['id'],	// retain the db id (used for updates)
			'so_po_item_ref_id'       => 0,	// item reference id for so/po line items
			'gl_type'                 => $this->gl_type,
			'sku'                     => $this->item_rows[$i]['sku'],
			'qty'                     => $this->item_rows[$i]['pstd'],
			'description'             => $this->item_rows[$i]['desc'],
			'credit_amount' 		  => $this->item_rows[$i]['total'],
			'full_price'              => $this->item_rows[$i]['full'],
			'gl_account'              => $this->item_rows[$i]['acct'],
			'taxable'                 => $this->item_rows[$i]['tax'],
			'serialize_number'        => $this->item_rows[$i]['serial'],
			'project_id'              => $this->item_rows[$i]['proj'],
			'post_date'               => $this->post_date,
			'date_1'                  => '',
		  );
		  $total += $this->item_rows[$i]['total'];
		}
	  }
	  return $total;
  }

  function add_tax_journal_rows() {
	global $admin;
	  $total        = 0;
	  $auth_array   = array();
	  $tax_rates    = ord_calculate_tax_drop_down('b');
	  $tax_auths    = gen_build_tax_auth_array();
	  $tax_discount = $this->account_type == 'v' ? AP_TAX_BEFORE_DISCOUNT : AR_TAX_BEFORE_DISCOUNT;
	  // calculate each tax value by authority per line item
	  foreach ($this->journal_rows as $idx => $line_item) {
	    if ($line_item['taxable'] > 0 && ($line_item['gl_type'] == $this->gl_type || $line_item['gl_type'] == 'frt')) {
		  foreach ($tax_rates as $rate) {
		    if ($rate['id'] == $line_item['taxable']) {
			  $auths = explode(':', $rate['auths']);
			  foreach ($auths as $auth) {
			    $line_total = $line_item['debit_amount'] + $line_item['credit_amount']; // one will always be zero
			    if (ENABLE_ORDER_DISCOUNT && $tax_discount == '0') {
				  $line_total = $line_total * (1 - $this->disc_percent);
			    }
				$auth_array[$auth] += ($tax_auths[$auth]['tax_rate'] / 100) * $line_total;
			  }
		    }
		  }
	    }
	  }
	  // calculate each tax total by authority and put into journal row array
	  foreach ($auth_array as $auth => $auth_tax_collected) {
		if ($auth_tax_collected == '' && $tax_auths[$auth]['account_id'] == '') continue;
	  	if( ROUND_TAX_BY_AUTH == true ){
			$amount = number_format($auth_tax_collected, $admin->currencies->currencies[DEFAULT_CURRENCY]['decimal_places'], '.', '');
		}else {
			$amount = $auth_tax_collected;
		}
	    $this->journal_rows[] = array( // record for specific tax authority
		  'qty'                     => '1',
		  'gl_type'                 => 'tax',		// code for tax entry
		  'credit_amount' 			=> $amount,
		  'description'             => $tax_auths[$auth]['description_short'],
		  'gl_account'              => $tax_auths[$auth]['account_id'],
		  'post_date'               => $this->post_date,
	    );
	    $total += $amount;
	  }
	  return $total;
  }

  // this function adjusts the posted total to the calculated one to take into account fractions of a cent
  function adjust_total($amount) {
	if ($this->total_amount == $amount) $this->total_amount = $amount;
  }

  function add_rounding_journal_rows($amount) { // put rounding into journal row array
	global $messageStack, $admin;
	if((float)(string)$this->total_amount == (float)(string) $amount) return ;
	$this->rounding_amt = round(($this->total_amount - $amount), $admin->currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
	$messageStack->debug("\n calculated total = ".$amount." Posted total = ". $this->total_amount." rounding = ".$this->rounding_amt);
	if ($this->rounding_amt <> 0 ) {
		$this->journal_rows[] = array(
			'qty'            => '1',
			'gl_type'        => 'rnd',		// code for discount charges
			'debit_amount'   => ($this->rounding_amt < 0) ? -$this->rounding_amt : '',
			'credit_amount'  => ($this->rounding_amt > 0) ? $this->rounding_amt  : '',
			'description'    => TEXT_POINT_OF_SALE . '-' . TEXT_ROUNDED_OF,
			'gl_account'     => $this->rounding_gl_acct_id,
			'taxable'        => '0',
			'post_date'      => $this->post_date,
		);
	}
	return $this->rounding_amt;
  }

	function encrypt_payment($method) {
	  	$cc_info = array();
	  	$cc_info['name']    = $method['f0'];
	  	$cc_info['number']  = $method['f1'];
	  	$cc_info['exp_mon'] = $method['f2'];
	  	$cc_info['exp_year']= $method['f3'];
	  	$cc_info['cvv2']    = $method['f4'];
	  	$cc_info['alt1']    = $method['f5'];
	  	$cc_info['alt2']    = $method['f6'];
	  	\core\classes\encryption::encrypt_cc($cc_info);
	  	$payment_array = array(
		  'hint'      => $enc_value['hint'],
		  'module'    => 'contacts',
		  'enc_value' => $enc_value['encoded'],
		  'ref_1'     => $this->bill_acct_id,
		  'ref_2'     => $this->bill_address_id,
		  'exp_date'  => $enc_value['exp_date'],
	  	);
	   db_perform(TABLE_DATA_SECURITY, $payment_array, $this->payment_id ? 'update' : 'insert', 'id = '.$this->payment_id);
	   return true;
	}

}
?>