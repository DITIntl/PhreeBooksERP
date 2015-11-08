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
//  Path: /includes/classes/user.php
//
namespace core\classes;
class user {
	public $language;
	public $languages = array();
	public $companies = array();

	function __construct(){
		$this->language = new \core\classes\language();
	}

	final static public function get($variable){
		if (isset($_SESSION[$variable])) return $_SESSION[$variable];
		if (isset(self::$variable)) 	return self::$variable;
		return "unknown";
	}

	/**
	 * checks if user is logged in.
	 * @return bool if user is logged in.
	 */

	final public function is_validated (\core\classes\basis &$admin) {
		if (is_null($admin->DataBase)){
			\core\classes\messageStack::debug_log("database isn't connected.");
			$admin->fireEvent('LoadLogIn');
		}
		if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] <> '') return true;
		if ($_REQUEST['action'] == "ValidateUser") {
			$_SESSION['company'] = $_REQUEST['company'];
			return true;
		}
		//allow the user to continu to with the login action.
		if (!in_array($_REQUEST['action'], array('ValidateUser', 'pw_lost_sub', 'pw_lost_req'))){
			$this->load_companies();
			self::load_languages();
			self::get_company();
			if($_REQUEST['action'] == 'pw_lost_req') {
				$admin->fireEvent('LoadLostPassword');
			}else{
				$admin->fireEvent('LoadLogIn');
			}
			return false;
		}
		$this->load_companies();
		self::load_languages();
		self::get_company();
		throw new \core\classes\userException(TEXT_SORRY_YOU_ARE_LOGGED_OUT, "LoadLogIn");
	}

	/**
	 * returns the current company and sets it in the Session variable.
	 */
	final static public function get_company(){
		if (isset($_SESSION['company'])) return $_SESSION['company'];
		if (isset($_REQUEST['company'])) {
			$_SESSION['company'] = $_REQUEST['company'];
		} else { // find default company
			if(defined('DEFAULT_COMPANY')) {
				$_SESSION['company'] = DEFAULT_COMPANY;
			}else{
				if (isset($_COOKIE['pb_company'])) $_SESSION['company'] = $_COOKIE['pb_company'];
			}
		}
		return $_SESSION['company'];
	}

	/**
	 * returns the current language and sets it in the Session variable.
	 */

	final static public function get_language(){
		if (isset($_SESSION['language'])) return $_SESSION['language'];
		if( isset($_REQUEST['language'])) {
			$_SESSION['language'] = $_REQUEST['language'];
		} elseif ( !isset($_SESSION['language'])) {
			if(defined('DEFAULT_LANGUAGE')) {
				$_SESSION['language'] = DEFAULT_LANGUAGE;
			}else if( isset($_COOKIE['pb_language'])){
				$_SESSION['language'] = $_COOKIE['pb_language'];
			}else if( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && strlen($_SERVER['HTTP_ACCEPT_LANGUAGE']) == 5){
				$_SESSION['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			}else{
				$_SESSION['language'] = $this->language;
			}
		}
		return $_SESSION['language'];
	}

	/**
	 * method will return current security level, will check if it is set.
	 * this method will not validate nor throw exceptions.
	 * @param unknown_type $token
	 */

	final static function security_level($token){
		if (isset($_SESSION['admin_security'][$token])) return $_SESSION['admin_security'][$token];
		return 0;
	}

	/**
	 * This method returns the current security_level of the requested token.
	 * If token isn't set a exception will be thrown
	 * @param int $token
	 * @param bool $user_active
	 * @throws Exception
	 */

	final static function validate($token = 0, $user_active = false) {
  		$security_level = $_SESSION['admin_security'][$token];
  		if (!in_array($security_level, array(1,2,3,4)) && !$user_active) throw new \core\classes\userException(ERROR_NO_PERMISSION, 10, $e);
  		return $user_active ? 1 : $security_level;
	}

	/**
	 * This method will check if user has security clearance if not a exception will be throw.
	 * @param int $security_level
	 * @param int $required_level
	 */

	final static function validate_security($current_security_level = 0, $required_level = 1) {
		if ($current_security_level < $required_level) throw new \core\classes\userException(ERROR_NO_PERMISSION);
	}

	/**
	 * This method will check if user has security clearance if not a exception will be throw.
	 * If token isn't set a exception will be thrown
	 * @param number $token
	 * @param number $required_level
	 * @param bool $user_active
	 * @throws \core\classes\userException
	 */
	final static function validate_security_by_token($token = 0, $required_level = 1, $user_active = false) {
		if (self::validate($token = 0, $user_active = false) < $required_level) throw new \core\classes\userException(ERROR_NO_PERMISSION);
	}
	/**
	 * this will return a array of permissions
	 * @param string $imploded_permissions
	 * @return array keyed permission levels.
	 */
	final static function parse_permissions($imploded_permissions) {
		$result = array();
		$temp = explode(',', $imploded_permissions);
		if (is_array($temp)) {
	  		foreach ($temp as $imploded_entry) {
				$entry = explode(':', $imploded_entry);
				$result[$entry[0]] = $entry[1];
			}
		}
		return $result;
  	}

  	final function load_companies() {
		$contents = @scandir(DIR_FS_MY_FILES);
		if($contents === false) throw new \core\classes\userException("couldn't read or find directory ". DIR_FS_MY_FILES);
		foreach ($contents as $file) {
			if ($file <> '.' && $file <> '..' && is_dir(DIR_FS_MY_FILES . $file)) {
			  	if (file_exists(DIR_FS_MY_FILES   . $file . '/config.php')) {
			  		if (!isset($_SESSION['company'])) $_SESSION['company'] = $file;
					require_once (DIR_FS_MY_FILES . $file . '/config.php');
					$this->companies[$file] = array(
				  	  'id'   => $file,
				  	  'text' => constant($file . '_TITLE'),
					);
			  	}
			}
		}
	}

	final static function load_languages() {//@todo rewrite for other language files and loading of core language
		$contents = @scandir('modules/phreedom/language/');
		if($contents === false) throw new \core\classes\userException("couldn't read or find directory modules/phreedom/language/");
		foreach ($contents as $lang) {
			if (!isset($_SESSION['language'])) $_SESSION['language'] = $lang;
			if ($lang <> '.' && $lang <> '..' && is_dir('modules/phreedom/language/'. $lang) && file_exists("modules/phreedom/language/$lang/language.php")) {
		  		if ($config_file = file("modules/phreedom/language/$lang/language.php")) {
		  			foreach ($config_file as $line) {
		  				if (strstr($line,'\'LANGUAGE\'') !== false) {
			    			$start_pos     = strpos($line, ',') + 2;
			    			$end_pos       = strpos($line, ')') + 1;
				    		$language_name = substr($line, $start_pos, $end_pos - $start_pos);
				    		break;
			  			}
		  			}
		  			$_SESSION['languages'][$lang] = array('id' => $lang, 'text' => $language_name);
		  		}
			}
		}
	}
	
	function __destruct(){
//		$_SESSION['companies'] = $this->companies; @todo do we still need this
	}

}