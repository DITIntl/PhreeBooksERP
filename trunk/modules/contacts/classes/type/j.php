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
//  Path: /modules/contacts/classes/type/j.php
//  jobs/projects
namespace contacts\classes\type;
class j extends \contacts\classes\contacts{
	public $security_token	= SECURITY_ID_MAINTAIN_PROJECTS;
	public $address_types	= array('jm', 'js', 'jb', 'im');
	public $type            = 'j';
	public $title			= TEXT_PROJECT;
	public $dept_rep_id 	= AR_DEF_GL_SALES_ACCT; //will overwrite if exists in database.

	public function __construct(){
		$this->tab_list[] = array('file'=>'template_j_general',	'tag'=>'general',  'order'=> 1, 'text'=>TEXT_GENERAL);
		parent::__construct();
	}
}
?>