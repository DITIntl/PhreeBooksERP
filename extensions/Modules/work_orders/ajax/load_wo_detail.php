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
//  Path: /modules/work_orders/ajax/load_wo_detail.php
//
/**************   Check user security   *****************************/
$xml = NULL;
$security_level = \core\classes\user::validate();
/**************   page specific initialization  *************************/
$id = $_GET['id'];
if (!$id) throw new \core\classes\userException("Error - Bad ID passed.");

$result = $admin->DataBase->query("select display_name, admin_email from " . TABLE_USERS . " where admin_id = " . $_SESSION['user']->admin_id);
$xml  = xmlEntry("id",     $id);
$xml .= xmlEntry("sEmail", $result->fields['admin_email']);
$xml .= xmlEntry("sName",  $result->fields['display_name']);
$xml .= xmlEntry("rEmail", '');
$xml .= xmlEntry("rName",  '');

echo createXmlHeader() . $xml . createXmlFooter();
ob_end_flush();
session_write_close();
die;
?>