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

	// Name of current page
	$context['current_page_name'] = wiki_urlname($_REQUEST['page'], $_REQUEST['namespace']);

	// Base array for calling wiki_get_url for this page
	$context['wiki_url'] = array(
		'page' => $context['current_page_name'],
		'revision' => isset($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : null,
	);

	$page_found = true;

	if (!$context['current_page'])
	{
		$page_found = false;

		$context['current_page'] = array(
			'title' => read_urlname($_REQUEST['page'], true),
			'namespace' => $_REQUEST['namespace'],
			'name' => $_REQUEST['page'],
			'content' => '',
		);
	}

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

	if (!isset($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
		$_REQUEST['sa'] = 'view';

	// Menu
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
				'sa' => 'talk',
				'page' => $context['current_page_name'],
			)),
			'selected' => in_array($_REQUEST['sa'], array('talk')),
			'show' => true,
		),
		'edit' => array(
			'title' => $txt['wiki_edit'],
			'url' => wiki_get_url(array(
				'sa' => 'edit',
				'page' => $context['current_page_name'],
			)),
			'selected' => in_array($_REQUEST['sa'], array('edit', 'edit2')),
			'show' => allowedTo('wiki_edit'),
			'class' => 'margin',
		),
		'history' => array(
			'title' => $txt['wiki_history'],
			'url' => wiki_get_url(array(
				'sa' => 'history',
				'page' => $context['current_page_name'],
			)),
			'selected' => in_array($_REQUEST['sa'], array('history', 'diff')),
			'show' => true,
			'class' => allowedTo('wiki_edit') ? '' : 'margin',
		),
	);

	// Template
	loadTemplate('WikiPage');
	$context['template_layers'][] = 'wikipage';

	$context['linktree'][] = array(
		'url' => $context['current_page']['url'],
		'name' => $context['current_page']['title'],
	);

	if (!$context['current_page'] && !in_array($_REQUEST['sa'], array('edit', 'edit2')))
		return 'show_not_found_error';

	require_once($sourcedir . '/' . $subActions[$_REQUEST['sa']][0]);
	$subActions[$_REQUEST['sa']][1]();
}

?>