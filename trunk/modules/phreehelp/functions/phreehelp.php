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
//  Path: /modules/phreehelp/functions/phreehelp.php
//

function synchronize() {
  global $admin;
  $config = $admin->DataBase->query('TRUNCATE ' . TABLE_PHREEHELP);
  // recursively read file and store in db
  $extensions = explode(',', VALID_EXTENSIONS);
  $file_list = array();
  $modules = @scandir(DIR_FS_MODULES);
  if($modules === false) throw new \core\classes\userException("couldn't read or find directory ". DIR_FS_MODULES);
  foreach ($modules as $module) {
    if ($module <> '.' && $module <> '..') {
      if       (file_exists(DIR_WS_MODULES . $module . '/language/' . $_SESSION['language'] . '/manual')) {
	    $file_list = array_merge($file_list, directory_to_array(DIR_WS_MODULES . $module . '/language/' . $_SESSION['language'] . '/manual', $extensions));
	  } elseif (file_exists(DIR_WS_MODULES . $module . '/language/en_us/manual')) {
	    $file_list = array_merge($file_list, directory_to_array(DIR_WS_MODULES . $module . '/language/en_us/manual', $extensions));
	  }
	}
  }
  $toc = array();
  foreach ($file_list as $filename) {
	$filename = str_replace(DOC_REL_PATH, DOC_ROOT_URL, $filename); // convert to url to read script generated filenames
	$tags      = get_meta_tags($filename);
	if (($doc_html  = @file_get_contents($filename)) === false)  throw new \core\classes\userException(sprintf(ERROR_READ_FILE, 	$filename));
	preg_match('/<title>([^>]*)<\/title>/si', $doc_html, $match);
	$doc_title = (isset($match) && is_array($match) && count($match) > 0) ? strip_tags($match[1]) : TEXT_NO_TITLE;
	$doc_text  = trim(strip_tags($doc_html));
	$doc_text  = str_replace(chr(10), ' ', $doc_text); // process out special characters
	$sql      = "insert into " . TABLE_PHREEHELP . " (doc_url, doc_pos, doc_index, doc_title, doc_text)
	  values ('$filename', '{$tags['doc_pos']}', '{$tags['doc_index_1']}', '$doc_title', '" . addslashes($doc_text) . "')";
	$row      = $admin->DataBase->query($sql);
	$id       = \core\classes\PDO::lastInsertId('id');
	$toc[$id] = $tags['doc_pos'];
  }
  foreach ($toc as $id => $value) {
    if (strrpos($value, '.') === false) {
	  $parent = '0';
    } else {
	  $parent = substr($value, 0, strrpos($value, '.'));
	  $key    = array_search($parent, $toc);
	  if ($key !== false) { // if no parent found, default to root
	    $admin->DataBase->query("update " . TABLE_PHREEHELP . " set parent_id = " . $key . " where id = " . $id);
	    $admin->DataBase->query("update " . TABLE_PHREEHELP . " set doc_type = '0' where id = " . $key); // set parent to type folder
	  }
    }
  }
}

function directory_to_array($directory, $extension = "", $full_path = true) {
  $array_items = array();
  $contents = @scandir($directory);
  if($contents === false) throw new \core\classes\userException("couldn't read or find directory $directory");
  foreach ($contents as $file) {
	if ($file <> "." && $file <> "..") {
	  if (is_dir($directory. "/" . $file)) {
		$array_items = array_merge($array_items, directory_to_array($directory. "/" . $file, $extension, $full_path));
	  } else {
		$file_ext = substr(strrchr($file, "."), 1);
		if (!$extension || in_array($file_ext, $extension)) {
		  $array_items[] = $full_path ? ($directory . "/" . $file) : $file;
		}
	  }
	}
  }
  return $array_items;
}

function retrieve_toc() {
  global $admin;
  $toc = $admin->DataBase->query('select id, parent_id, doc_type, doc_url, doc_title from ' . TABLE_PHREEHELP . '
	order by parent_id, doc_pos');
  $toc_array = array();
  $toc_array[-1][] = array('id' => 0, 'doc_type' => '0', 'doc_title' => TEXT_MANUAL); // home dir
  while (!$toc->EOF) {
	if (!$dir_only || ($dir_only && $toc->fields['doc_type'] == '0')) {
	  $toc_array[$toc->fields['parent_id']][] = array(
		'id'        => $toc->fields['id'],
		'doc_type'  => $toc->fields['doc_type'],
		'doc_url'   => $toc->fields['doc_url'],
		'doc_title' => $toc->fields['doc_title'],
	  );
	}
	$toc->MoveNext();
  }
  $toc_string = build_help_tree('dir_tree', $toc_array, $index = -1, $level = 0, $cont_level = array());
  return $toc_string;
  }

  function build_help_tree($name, $full_array, $index = -1, $level = 0, $cont_level = array()) {
	$entry_string = '';
	for ($j = 0; $j < sizeof($full_array[$index]); $j++) {
	  $new_ref   = $index . '_' . $full_array[$index][$j]['id'];
	  $cont_temp = array_keys($cont_level);
	  $entry_string .= '<div style="height:16px;">' . chr(10);
	  for ($i = 0; $i < $level; $i++) {
	    if (false) {
	    } elseif ($i == $level-1 && $j < sizeof($full_array[$index])-1) {
		  $entry_string .= html_icon('phreebooks/cont-end.gif', '', 'small');
		} elseif ($i == $level-1 && $j == sizeof($full_array[$index])-1) {
		  $entry_string .= html_icon('phreebooks/end-end.gif',  '', 'small');
		} elseif (in_array($i, $cont_temp)) {
		  $entry_string .= html_icon('phreebooks/cont.gif',     '', 'small');
		} elseif ($i < $level-1) {
		  $entry_string .= html_icon('phreebooks/blank.gif',    '', 'small');
		}
	  }
	  if ($full_array[$index][$j]['doc_type'] == '0') {  // folder
		$entry_string .= '<a id="imgdc_' . $new_ref . '" href="javascript:Toggle(\'dc_' . $new_ref . '\');">' . html_icon('places/folder.png', TEXT_OPEN, 'small', 'class="draggable"', '', '', 'icndc_' . $new_ref) . '</a>';
	  } else {
		$entry_string .= html_icon('mimetypes/text-x-generic.png', $full_array[$index][$j]['doc_title'], 'small');
	  }
	  $short_title     = (strlen($full_array[$index][$j]['doc_title']) <= PH_DEFAULT_TRIM_LENGTH) ? $full_array[$index][$j]['doc_title'] : substr($full_array[$index][$j]['doc_title'], 0, PH_DEFAULT_TRIM_LENGTH) . '...';
	  if ($full_array[$index][$j]['doc_url']) {
	    $entry_string .= '&nbsp;<a href="' . $full_array[$index][$j]['doc_url'] . '" target="mainFrame">' . htmlspecialchars($short_title) . '</a>' . chr(10);
	  } else {
	    $entry_string .= '&nbsp;' . htmlspecialchars($short_title) . chr(10);
	  }
	  $entry_string .= '</div>' . chr(10);
	  if ($j < sizeof($full_array[$index])-1) {
		$cont_level[$level-1] = true;
	  } else {
		unset($cont_level[$level-1]);
	  }
	  if (isset($full_array[$full_array[$index][$j]['id']])) {
		$display_none  = ($level == 0) ? '' : 'display:none; ';
		$entry_string .= '<div id="dc_' . $new_ref . '" style="' . $display_none . 'margin-left:0px;">' . chr(10);
		$entry_string .= build_help_tree($name, $full_array, $full_array[$index][$j]['id'], $level+1, $cont_level) . chr(10);
		$entry_string .= '</div>' . chr(10);
	  }
	}
	return $entry_string;
  }

function build_href($array_tree, $ref = '') {
  ksort($array_tree);
  $entry_string = '';
  foreach ($array_tree as $key => $entry) {
	$new_ref = $ref . '_' . $entry['id'];
	if (isset($entry['children'])) {
	  $entry_string .= '<a id="ph_' . $new_ref . '" href="javascript:Toggle(\'' . $new_ref . '\');">' . html_icon('places/folder.png', TEXT_FOLDER, 'small') . '</a>' . chr(10);
	} else {
	  $entry_string .= html_icon('mimetypes/text-html.png', TEXT_DOCUMENT, 'small') . chr(10);
	}
	if (isset($entry['doc_title'])) {
	  if (isset($entry['doc_url'])) {
		$entry_string .= '<a href="' . $entry['doc_url'] . '" target="mainFrame">' . $entry['doc_title'] . '</a>' . chr(10);
	  } else {
		$entry_string .= $entry['doc_title'] . chr(10);
	  }
	} else {
	  $entry_string .= TEXT_UNTITLED_DOCUMENT . chr(10);
	}
	$entry_string .= '<br />' . chr(10);
	if (isset($entry['children'])) {
	  $entry_string .= '<div id="' . $new_ref . '" style="display:none; margin-left:1px;">' . chr(10) . chr(10);
	  $entry_string .= build_href($entry['children'], $new_ref) . chr(10);
	  $entry_string .= '</div>' . chr(10);
	}
  }
  return $entry_string;
}

function retrieve_index() {
  global $admin;
  $index = $admin->DataBase->query('select id, doc_url, doc_index from ' . TABLE_PHREEHELP . ' order by doc_index');
  $index_array = array();
  while (!$index->EOF) {
	$element = explode('.', $index->fields['doc_index']);
	$index_array[$element[0]]['id']        = $index->fields['id'];
	$index_array[$element[0]]['doc_title'] = $element[0];
	switch (count($element)) { // currently handles two levels deep
	  case '1': $index_array[$element[0]]['doc_url']   = $index->fields['doc_url']; break;
	  case '2':
		$index_array[$element[0]]['children'][$element[1]]['doc_url']   = $index->fields['doc_url'];
		$index_array[$element[0]]['children'][$element[1]]['doc_title'] = $element[1];
		break;
	  default: // no index specified, ignore
	}
	$index->MoveNext();
  }
  $index_string = build_href($index_array, $ref = 'idx');
  return $index_string;
}

function search_results($search_text) {
  global $admin;
  if (!$search_text) return '';
  $sql = "select id, doc_url, doc_title, MATCH (doc_title, doc_text) AGAINST ('" . $search_text . "') as score
    from " . TABLE_PHREEHELP . " where MATCH (doc_title, doc_text) AGAINST ('" . $search_text . "')";
  $results = $admin->DataBase->query($sql);
  if ($results->rowCount() == 0) return TEXT_NO_RESULTS_FOUND;
  $search_array = array();
  $index = 0;
  while (!$results->EOF) {
	$score = number_format($index->fields['score'], 2);
	$search_array[$index]['id']        = $results->fields['id'];
	$search_array[$index]['doc_url']   = $results->fields['doc_url'];
	$search_array[$index]['doc_title'] = $results->fields['doc_title'];
	$index++;
	$results->MoveNext();
  }
  $search_string = build_href($search_array, $ref = 'srch');
  return $search_string;
}

?>