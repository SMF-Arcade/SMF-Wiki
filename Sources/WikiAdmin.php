<?php
/**
 * Admin settings page
 *
 * @package core
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function WikiAdmin($prefix = null)
{
	global $context, $smcFunc, $sourcedir, $user_info, $txt;

	require_once($sourcedir . '/Wiki.php');
	require_once($sourcedir . '/ManageServer.php');

	isAllowedTo('wiki_admin');

	// Load necassary settings
	loadWiki('admin', $prefix);

	$context[$context['admin_menu_name']]['tab_data']['title'] = $txt['wiki_admin_title'];
	$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['wiki_admin_desc'];

	$context['page_title'] = $txt['admin_wiki'];

	$subActions = array(
		'main' => array('WikiAdminMain'),
		'settings' => array('WikiAdminSettings'),
	);

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'main';

	if (isset($subActions[$_REQUEST['sa']][1]))
		isAllowedTo($subActions[$_REQUEST['sa']][1]);

	$subActions[$_REQUEST['sa']][0]();
}

function WikiAdminMain()
{
	global $context, $smcFunc, $sourcedir, $scripturl, $user_info, $txt;

	$context['sub_template'] = 'wiki_admin_main';
}

function WikiAdminSettings($return_config = false)
{
	global $context, $smcFunc, $sourcedir, $scripturl, $user_info, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT b.id_board, b.name, c.name AS cat_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE b.redirect = {string:blank_redirect}',
		array(
			'blank_redirect' => '',
		)
	);
	$boards = array(0 => '');
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$boards[$row['id_board']] = strip_tags($row['cat_name']) . ' - ' . strip_tags($row['name']);
	$smcFunc['db_free_result']($request);

	$config_vars = array(
		$txt['wiki_standalone_mode'],
			array('select', 'wikiStandalone', array($txt['wikiStandalone_0'], $txt['wikiStandalone_1'], $txt['wikiStandalone_2'])),
			array('text', 'wikiStandaloneUrl'),
			array('select', 'wikiTalkBoard', $boards),
		'',
			array('text', 'wikiAttachmentsDir'),
	);

	if ($return_config)
		return $config_vars;

	if (isset($_GET['save']))
	{
		checkSession('post');
		saveDBSettings($config_vars);

		writeLog();

		redirectexit('action=admin;area=wiki;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=wiki;sa=settings;save';
	$context['settings_title'] = $txt['wiki_settings'];
	$context['sub_template'] = 'show_settings';

	prepareDBSettingContext($config_vars);
}

?>