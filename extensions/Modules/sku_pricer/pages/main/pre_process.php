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
//  Path: /modules/sku_pricer/pages/main/pre_process.php
//
$security_level = \core\classes\user::validate(SECURITY_ID_SKU_PRICER);
/**************  include page specific files    *********************/
/**************   page specific initialization  *************************/
$upload_name = 'file_name';
/***************   Act on the action request   *************************/
switch ($_REQUEST['action']) {
  	case 'save':
		\core\classes\user::validate_security($security_level, 1); // security check
		validate_upload($upload_name, 'text', 'csv');
		$lines_array = file($_FILES[$upload_name]['tmp_name']);
		$post_pay = new \sku_pricer\classes\sku_pricer();
		$post_pay->processCSV($upload_name);
		break;
  	default:
}
/*****************   prepare to display templates  *************************/
$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', TEXT_SKU_PRICE_IMPORTER);

?>