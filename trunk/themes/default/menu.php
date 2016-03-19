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
//  Path: /themes/default/menu.php
//
usort($basis->mainmenu, 'sortByOrder');
echo '<!-- Pull Down Menu -->' . chr(10);
switch (MY_MENU) {
   case 'left': echo '<div id="smoothmenu" class="ddsmoothmenu-v" style="float:left">'.chr(10); break;
   case 'top':
   default:     echo '<div id="smoothmenu" class="ddsmoothmenu">'.chr(10); break;
}
echo '  <ul>' . chr(10);
foreach((array)$basis->mainmenu as $menu_item) create_menu($menu_item);
echo '  </ul>' . chr(10);
echo '<br style="clear:left" />'.chr(10);
echo '</div>'.chr(10);
switch (MY_MENU) {
   case 'left': echo '<div style="float:left;margin-left:auto;margin-right:auto;">'.chr(10); break;
   case 'top':
   default:     echo '<div>'.chr(10); break;
}

function sortByOrder($a, $b) {
    if(is_integer($a['order']) && is_integer($b['order'])) return $a['order'] - $b['order'];
    return strcmp($a["order"], $b["order"]);
}

function create_menu(array $array){
	if($array['security_id'] != 1 && $_SESSION['user']->admin_id != 1){
		if(isset($array['security_id']) && $array['security_id'] != ''){
			if (\core\classes\user::security_level($array['security_id']) != 0 ) return '';
		}
	}
	if(isset($array['submenu'])){
		usort($array['submenu'], 'sortByOrder');
		if(check_permission($array['submenu'])){
			echo "  <li><a href='{$array['link']}' {$array['params']}>".(isset($array['icon']) ? $array['icon'].' '.$array['text'] : $array['text']).'</a>';
			echo '    <ul>';
			foreach($array['submenu'] as $menu_item) create_menu($menu_item);
			echo '    </ul>';
			echo '  </li>';
		}
	}else{
		echo "  <li><a href='{$array['link']}' {$array['params']}>";
		if ($array['text'] == TEXT_HOME && ENABLE_ENCRYPTION && strlen($_SESSION['ENCRYPTION_VALUE']) > 0) echo html_icon('emblems/emblem-readonly.png', TEXT_ENCRYPTION_KEY_IS_SET, 'small');
  		echo (isset($array['icon']) ? $array['icon'].' '.$array['text'] : $array['text']).'</a>  </li>'.chr(10);
	}
	return true;
}

function check_permission(array $array){
	$valid = false;
	foreach($array as $menu_item){
		if(is_array($menu_item['submenu'])) {
			if(check_permission($menu_item['submenu'])) $valid = true;
		}else{
			if($menu_item['show_in_users_settings'] == false && $menu_item['security_id'] == SECURITY_ID_PHREEFORM) continue;
			if(isset($menu_item['security_id']) && $menu_item['security_id'] != ''){
				if(\core\classes\user::security_level($menu_item['security_id']) > 0) $valid = true;
			}
		}
	}
	return $valid;
}
?>
