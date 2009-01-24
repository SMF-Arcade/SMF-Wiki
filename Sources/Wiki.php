<?php
/**********************************************************************************
* Wiki.php                                                                        *
***********************************************************************************
* SMF Wiki                                                                        *
* =============================================================================== *
* Software Version:           SMF Wiki 0.1                                        *
* Software by:                Niko Pahajoki (http://www.madjoki.com)              *
* Copyright 2008-2009 by:     Niko Pahajoki (http://www.madjoki.com)              *
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

function loadWiki($mode = '')
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir, $wiki_version;

	require_once($sourcedir . '/Subs-Wiki.php');

	// Wiki Version
	$wiki_version = '0.1';

	loadTemplate('Wiki', array('wiki'));
	loadLanguage('Wiki');

	// Normal mode
	if ($mode == '')
	{
		// Linktree
		$context['linktree'][] = array(
			'url' => wiki_get_url('Main_Page'),
			'name' => $txt['wiki'],
		);

		// Wiki Variables
		$context['wiki_variables'] = array(
			'wikiversion' => $wiki_version,
		);

		// Template
		$context['template_layers'][] = 'wiki';
	}
	// Admin Mode
	elseif ($mode == 'admin')
	{
		loadTemplate('WikiAdmin');
	}
}

function Wiki($standalone = false)
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	loadWiki();

	if (!isset($_REQUEST['page']))
		$_REQUEST['page'] = '';

	// Check that namespace and page has only legal characters and redirect if not
	if (strpos($_REQUEST['page'], ':'))
		list ($_REQUEST['namespace'], $_REQUEST['page']) = explode(':', $_REQUEST['page'], 2);
	else
		$_REQUEST['namespace'] = '';

	$namespace = clean_pagename($_REQUEST['namespace'], true);
	$page = clean_pagename($_REQUEST['page']);

	$context['current_page_name'] = wiki_urlname($page, $namespace);

	if ($namespace != $_REQUEST['namespace'] || $page != $_REQUEST['page'])
		redirectexit(wiki_get_url($context['current_page_name']));

	unset($namespace, $page);

	loadNamespace();

	if (empty($_REQUEST['page']))
		redirectexit($context['namespace']['url']);

	// Subactions
	$subActions = array(
		'normal' => array(
			// action => array(file, function, [requires existing page])
			'view' => array('WikiPage.php', 'ViewPage'),
			'talk' => array('WikiTalkPage.php', 'ViewTalkPage', true),
			'talk2' => array('WikiTalkPage.php', 'ViewTalkPage2', true),
			'diff' => array('WikiPage.php', 'DiffPage', true),
			'history' => array('WikiHistory.php', 'ViewPageHistory', true),
			'edit' => array('WikiEditPage.php', 'EditPage'),
			'edit2' => array('WikiEditPage.php', 'EditPage2'),
			'file_info' => array('WikiFiles.php', 'WikiFileInfo'),
			'file_view' => array('WikiFiles.php', 'WikiFileView'),
		),
		// Special Pages
		'special' => array(
			'RecentChanges' => array('WikiHistory.php', 'WikiRecentChanges'),
			'Upload' =>  array('WikiFiles.php', 'WikiFileUpload'),
		)
	);

	// Load Page info if namesapce isn't "Special" namespace
	if ($context['namespace']['type'] != 1)
	{
		loadWikiPage();

		$namespaceGroup = 'normal';
		$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$namespaceGroup][$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'view';

		// Force page to be "view" for non existing and asked to, it's here to make correct tab highlight
		if ($context['page_info']['id'] === null && !empty($subActions[$namespaceGroup][$_REQUEST['sa']][2]))
			$_REQUEST['sa'] = 'view';

		$subaction = $_REQUEST['sa'];

		$context['can_edit_page'] = allowedTo('wiki_admin') || (allowedTo('wiki_edit') && !$context['page_info']['is_locked']);
		$context['can_lock_page'] = allowedTo('wiki_admin');

		// Don't let anyone create page if it's not "normal" page (ie. file)
		if ($context['namespace']['type'] != 0 && $context['page_info']['id'] === null)
			$context['can_edit_page'] = false;

		if ($_REQUEST['sa'] == 'edit' || $_REQUEST['sa'] == 'edit2')
			unset($context['page_content']);

		if ($_REQUEST['sa'] == 'edit2' && isset($_POST['wiki_content']))
		{
			$context['page_info']['variables'] = wikiparse_variables($_POST['wiki_content']);

			if (isset($context['page_info']['variables']['title']))
				$context['page_info']['title'] = $context['page_info']['variables']['title'];
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

		// Template
		loadTemplate('WikiPage');
		$context['template_layers'][] = 'wikipage';
	}
	// Load required info for Special pages
	else
	{
		$namespaceGroup = 'special';

		if (strpos($_REQUEST['page'], '/'))
			list ($_REQUEST['page'], $_REQUEST['sub_page']) = explode('/', $_REQUEST['page'], 2);
		else
			$_REQUEST['sub_page'] = '';

		$subaction = $_REQUEST['page'];

		$context['page_info'] = array(
			'id' => null,
			'title' => read_urlname($_REQUEST['page'], true),
			'name' => wiki_urlname($_REQUEST['page'], $context['namespace']['id']),
			'namespace' => $context['namespace']['id'],
			'is_locked' => false,
		);
	}

	// Name of current page
	$context['current_page_name'] = $context['page_info']['name'];

	// Base array for calling wiki_get_url for this page
	$context['wiki_url'] = array(
		'page' => $context['current_page_name'],
		'revision' => isset($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : null,
	);

	$context['current_page_url'] = wiki_get_url($context['wiki_url']);

	// Redirect to correct case if needed
	if ($context['current_page_name'] != wiki_urlname($_REQUEST['page'], $_REQUEST['namespace']))
		redirectexit($context['current_page_url']);

	// Load Navigation
	$context['wiki_navigation'] = cache_quick_get('wiki-navigation', 'Subs-Wiki.php', 'loadWikiMenu', array());

	// Highlight current section
	foreach ($context['wiki_navigation'] as $id => $grp)
	{
		if ($grp['page'] == $context['current_page_name'])
			$context['wiki_navigation'][$id]['selected'] = true;

		foreach ($grp['items'] as $subid => $item)
		{
			if ($item['page'] == $context['current_page_name'])
				$context['wiki_navigation'][$id]['items'][$subid]['selected'] = true;
		}
	}

	// Add Page to Link tree
	$context['linktree'][] = array(
		'url' => $context['current_page_url'],
		'name' => $context['page_info']['title'],
	);

	// Page Title
	$context['page_title'] = $context['forum_name'] . ' - ' . un_htmlspecialchars($context['page_info']['title']);
	$context['current_page_title'] = $context['page_info']['title'];

	require_once($sourcedir . '/' . $subActions[$namespaceGroup][$subaction][0]);
	$subActions[$namespaceGroup][$subaction][1]();
}

// Handles Files namespace
/*
function WikiFiles()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	if (empty($modSettings['wikiAttachmentsDir']))
		fatal_lang_error('wiki_file_not_found', false);

	$request = $smcFunc['db_query']('', '
		SELECT localname, mime_type, file_ext
		FROM {db_prefix}wiki_files
		WHERE filename = {string:filename}
			AND is_current = {int:is_current}',
		array(
			'filename' => $_REQUEST['file'],
			'is_current' => 1,
		)
	);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (!$row)
		fatal_lang_error('wiki_file_not_found', false);

	$context['current_file'] = array(
		'local_name' => $row['localname'],
		'mime_type' => $row['mime_type'],
		'file_ext' => $row['file_ext'],
	);

	if (isset($_REQUEST['image']) && empty($_REQUEST['sa']))
		$_REQUEST['sa'] = 'view';
	else
		$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'info';

	require_once($sourcedir . '/' . $subActions[$_REQUEST['sa']][0]);
	$subActions[$_REQUEST['sa']][1]();
}*/

?>