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
//  Path: /modules/phreeform/ajax/validate_db.php
//
/**************   Check user security   *****************************/
$xml = NULL;
$security_level = \core\classes\user::validate(SECURITY_ID_PHREEFORM);
/**************  include page specific files    *********************/
/**************   page specific initialization  *************************/
$runaway   = 0;
$tables    = array();
$i         = 2;
$strTable .= DB_PREFIX . $_GET['table1'];
$tables[]  = $_GET['table1'];
while (true) {
  	if (!isset($_GET['table' . $i])) break;
  	$joinopt = (isset($_GET['joinopt' . $i])) ? $_GET['joinopt' . $i] : 'JOIN';
  	$strTable .= ' ' . $joinopt . ' ' . DB_PREFIX . $_GET['table' . $i] . ' on ' . $_GET['table' . $i . 'criteria'];
  	$tables[] = $_GET['table' . $i];
  	$i++;
  	if ($runaway++ > 100) throw new \core\classes\userException('Runaway counter expired.');
}
foreach ($tables as $table) { // prefix the criteria
  $strTable = str_replace($table . '.', DB_PREFIX . $table . '.', $strTable);
}
$sql = "select * from " . $strTable . " limit 1";
$result = $admin->DataBase->query_return_error($sql);
// if we have a row, sql was valid
if ($admin->DataBase->error_number) {
  $message = sprintf(PHREEFORM_AJAX_BAD_DB_REFERENCE, $admin->DataBase->error_number . ' - ' . $admin->DataBase->error_text, $sql);
} elseif ($result->fetch(\PDO::FETCH_NUM) == 0) { // no rows were returned, could be no data yet so just warn and continue
  $message = PHREEFORM_AJAX_NO_TABLE_DATA;
} else {
  $message = PHREEFORM_AJAX_DB_SUCCESS;
}
echo createXmlHeader() . xmlEntry("message", $message) . createXmlFooter();
ob_end_flush();
session_write_close();
die;
?>