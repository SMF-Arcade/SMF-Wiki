<?php
/**********************************************************************************
* WikiMain.php                                                                    *
***********************************************************************************
* SMF Wiki                                                                        *
* =============================================================================== *
* Software Version:           SMF Wiki 0.1                                        *
* Software by:                Niko Pahajoki (http://www.madjoki.com)              *
* Copyright 2008 by:          Niko Pahajoki (http://www.madjoki.com)              *
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

function WikiMain()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	// Load page
	$context['current_page'] = loadWikiPage($_REQUEST['page'], $_REQUEST['namespace'], isset($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : 0);
	$context['can_edit_page'] = allowedTo('wiki_admin') || (allowedTo('wiki_edit') && !$context['current_page']['is_locked']);
	$context['can_lock_page'] = allowedTo('wiki_admin');

	$page_found = true;

	if (!$context['current_page'])
	{
		$page_found = false;

		$context['current_page'] = array(
			'title' => read_urlname($_REQUEST['page'], true),
			'name' => wiki_urlname($_REQUEST['page'], $_REQUEST['namespace']),
			'content' => '',
		);
	}

	// Name of current page
	$context['current_page_name'] = $context['current_page']['name'];

	if ($context['current_page']['name'] != wiki_urlname($_REQUEST['page'], $_REQUEST['namespace']))
		redirectexit(wiki_get_url($context['current_page_name']));

	// Base array for calling wiki_get_url for this page
	$context['wiki_url'] = array(
		'page' => $context['current_page_name'],
		'revision' => isset($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : null,
	);

	$context['current_page']['url'] = wiki_get_url($context['wiki_url']);

	$subActions = array(
		'view' => array('WikiPage.php', 'ViewPage'),
		'talk' => array('WikiTalkPage.php', 'ViewTalkPage'),
		'talk2' => array('WikiTalkPage.php', 'ViewTalkPage2'),
		'diff' => array('WikiPage.php', 'DiffPage'),
		'history' => array('WikiHistory.php', 'ViewPageHistory'),
		'edit' => array('WikiEditPage.php', 'EditPage'),
		'edit2' => array('WikiEditPage.php', 'EditPage2'),
	);

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'view';

	if ($_REQUEST['sa'] == 'edit2' && isset($_POST['arcontent']))
	{
		$context['current_page']['variables'] = wikiparse_variables($_POST['arcontent']);

		if (isset($context['current_page']['variables']['title']))
			$context['current_page']['title'] = $context['current_page']['variables']['title'];
	}

	// Setup tabs
	$context['wikimenu'] = array(
		'view' => array(
			'title' => $txt['wiki_view'],
			'url' => wiki_get_url($context['current_page_name']),
			'selected' => in_array($_REQUEST['sa'], array('view')),
			'show' => true,
		),
		'talk' => array(
			'title' => $txt['wiki_talk'],
			'url' => wiki_get_url(array(
				'page' => $context['current_page_name'],
				'sa' => 'talk',
			)),
			'selected' => in_array($_REQUEST['sa'], array('talk')),
			'show' => !empty($modSettings['wikiTalkBoard']),
		),
		'edit' => array(
			'title' => $txt['wiki_edit'],
			'url' => wiki_get_url(array(
				'page' => $context['current_page_name'],
				'sa' => 'edit',
			)),
			'selected' => in_array($_REQUEST['sa'], array('edit', 'edit2')),
			'show' => $context['can_edit_page'],
		),
		'history' => array(
			'title' => $txt['wiki_history'],
			'url' => wiki_get_url(array(
				'page' => $context['current_page_name'],
				'sa' => 'history',
			)),
			'selected' => in_array($_REQUEST['sa'], array('history', 'diff')),
			'show' => true,
		),
	);

	// Linktree
	$context['linktree'][] = array(
		'url' => $context['current_page']['url'],
		'name' => $context['current_page']['title'],
	);

	// Page title
	$context['page_title'] = $context['forum_name'] . ' - ' . un_htmlspecialchars($context['current_page']['title']);
	$context['current_page_title'] = $context['current_page']['title'];

	// Template
	loadTemplate('WikiPage');
	$context['template_layers'][] = 'wikipage';

	// Show error page if not found
	if (!$page_found && !in_array($_REQUEST['sa'], array('edit', 'edit2')))
	{
		$context['robot_no_index'] = true;
		$context['sub_template'] = 'not_found';
	}
	else
	{
		require_once($sourcedir . '/' . $subActions[$_REQUEST['sa']][0]);
		$subActions[$_REQUEST['sa']][1]();
	}
}

?>