<?php
/**********************************************************************************
* WikiAdmin.php                                                                   *
***********************************************************************************
* SMF Wiki                                                                        *
* =============================================================================== *
* Software Version:           SMF Wiki 0.1                                        *
* Software by:                Niko Pahajoki (http://www.madjoki.com)              *
* Copyright 2004-2008 by:     Niko Pahajoki (http://www.madjoki.com)              *
* Support, News, Updates at:  http://www.smfarcade.info                           *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

function WikiAdmin()
{
	global $context, $smcFunc, $sourcedir, $user_info, $txt;

	require_once($sourcedir . '/Wiki.php');
	require_once($sourcedir . '/ManageServer.php');

	isAllowedTo('wiki_admin');
	loadWiki('admin');

	$context[$context['admin_menu_name']]['tab_data']['title'] = &$txt['wiki_admin_title'];
	$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['wiki_admin_desc'];

	$context['page_title'] = $txt['projectSettings'];

	$subActions = array(
		'main' => array('WikiAdminMain'),
		'settings' => array('WikiAdminSettings'),
	);

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'main';

	if (isset($subActions[$_REQUEST['sa']][1]))
		isAllowedTo($subActions[$_REQUEST['sa']][1]);

	$subActions[$_REQUEST['sa']][0]();
}

function WikiAdminMain($return_config = false)
{

}

function WikiAdminSettings($return_config = false)
{
	global $context, $smcFunc, $sourcedir, $scripturl, $user_info, $txt;

	$config_vars = array(
			array('check', 'wikiEnabled'),
		'',
	);

	if ($return_config)
		return $config_vars;

	if (isset($_GET['save']))
	{
		checkSession('post');
		saveDBSettings($config_vars);

		writeLog();

		redirectexit('action=admin;area=wiki');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=wiki;save';
	$context['settings_title'] = $txt['wiki_settings'];
	$context['sub_template'] = 'show_settings';

	prepareDBSettingContext($config_vars);
}

?>