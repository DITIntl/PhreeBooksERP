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
//  Path: /modules/phreepos/dashboards/pos_this_week/pos_this_week.php
//
namespace phreepos\dashboards\pos_this_week;
class pos_this_week extends \core\classes\ctl_panel {
	public $description	 		= CP_POS_THIS_WEEK_DESCRIPTION;
	public $security_id  		= SECURITY_ID_POS_MGR;
	public $text		 		= CP_POS_THIS_WEEK_TITLE;
	public $version      		= '4.0';

	function output() {
		global $admin;
		$contents = '';
		$control  = '';
		for($i=0; $i<=7; $i++){
			if ('Mon'== strftime("%a", time()-($i * 24 * 60 * 60)) ){
				$a = $i;
			}
		}
		// Build content box
		$total = 0;
		$sql = $admin->DataBase->prepare("SELECT SUM(total_amount) as day_total, currencies_code, currencies_value, post_date
		  FROM " . TABLE_JOURNAL_MAIN . "
		  WHERE journal_id = 19 and post_date >= '" . date('Y-m-d', time()-($a * 24 * 60 * 60)) . "' GROUP BY post_date ORDER BY post_date");
		$sql->execute();
		if ($sql->fetch(\PDO::FETCH_NUM) < 1) {
			$contents = TEXT_NO_RESULTS_FOUND;
		} else {
			$week = array();
			while ($result = $sql->fetch(\PDO::FETCH_LAZY)){
			  	$total += $result['day_total'];
				$contents .= '<div style="float:right">' . $admin->currencies->format_full($result['day_total'], true, $result['currencies_code'], $result['currencies_value']) . '</div>';
				$contents .= '<div>';
				$contents .= gen_locale_date($result['post_date']) ;
				$contents .= '</a></div>' . chr(10);
		  	}
		}
		if ($sql->fetch(\PDO::FETCH_NUM) > 0) {
		  	$contents .= '<div style="float:right"><b>' . $admin->currencies->format_full($total, true, $result['currencies_code'], $result['currencies_value']) . '</b></div>';
		  	$contents .= '<div><b>' . TEXT_TOTAL . '</b></div>' . chr(10);
		}
		return $this->build_div($contents, $control);
	}

}
?>