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
	
	loadClassFile('WikiParser.php');

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
		
		// Template
		$context['template_layers'][] = 'wiki';
		
		$context['wiki_parser_extensions'] = array(
			// Custom variables 
			// format 'variable' (lowercase only) => array(function to call, can have parameter)
			// function: (&wiki_parser, variable[, value]) (value is present when parameter given, otherwise null)
			// returns html code for display
			'variables' => array(
				'wikiversion' => array(create_function('&$wiki_parser, $variable', 'return $GLOBALS[\'wiki_version\'];'), false),
				'displaytitle' => array(create_function('&$wiki_parser, $variable, $value', 'if ($value === null) { return $wiki_parser->title; } else { $wiki_parser->title = $value; return true; }'), true),
			),
			// Functions
			// format 'function' (lowercase only) => array(function to call)
			// function: (&wiki_parser, item)
			// returns html code for display
			'functions' => array(
				'#if' => array(create_function('&$wiki_parser, $item', '							
					$result = trim(str_replace(array(\'<br />\', \'&nbsp;\'), array("\n", \' \'), $wiki_parser->__parse_part($wiki_parser->fakeStatus, $item[\'firstParam\'], true)));
							
					if (isset($item[\'params\'][!empty($result) ? 1 : 2]))
						return $wiki_parser->__parse_part($status, $item[\'params\'][!empty($result) ? 1 : 2]);
					return \'\';')),
			),
			// format 'switch' => function to call
			// function: (&wiki_parser)
			// returns nothing
			'behaviour_switch' => array(
				'noindex' => create_function('&$wiki_parser', 'global $context; $context[\'robot_no_index\'] = true;'),
				'index' => create_function('&$wiki_parser', 'global $context; $context[\'robot_no_index\'] = false;'),
			),
			// XML Tags
			// format 'tag' (lowercase only) => array(function to call)
			// function: (&wiki_parser, content, attributes)
			// returns html code for display
			'tags' => array(
				'test_tag' => array(create_function('&$wiki_parser, $content, $attributes', 'return $content;')),
			),
		);
	}
	// Admin Mode
	elseif ($mode == 'admin')
		loadTemplate('WikiAdmin');
		
	loadNamespace();
}

function Wiki($standalone = false)
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	loadWiki();

	if (!isset($_REQUEST['page']))
		$_REQUEST['page'] = '';

	list ($_REQUEST['namespace'], $_REQUEST['page']) = __url_page_parse($_REQUEST['page']);

	$context['namespace'] = &$context['namespaces'][$_REQUEST['namespace']];

	// Add namespace to linktree if necassary
	if (!empty($context['namespace']['prefix']))
		$context['linktree'][] = array(
			'url' =>  $context['namespace']['url'],
			'name' => $context['namespace']['prefix'],
		);

	$context['current_page_name'] = wiki_urlname($_REQUEST['page'], $_REQUEST['namespace'], false);

	// Subactions
	$subActions = array(
		'normal' => array(
			// action => array(file, function, [requires existing page], [requires file])
			'view' => array('WikiPage.php', 'ViewPage'),
			'talk' => array('WikiTalkPage.php', 'ViewTalkPage', true),
			'talk2' => array('WikiTalkPage.php', 'ViewTalkPage2', true),
			'diff' => array('WikiPage.php', 'DiffPage', true),
			'history' => array('WikiHistory.php', 'ViewPageHistory', true),
			'edit' => array('WikiEditPage.php', 'EditPage'),
			'edit2' => array('WikiEditPage.php', 'EditPage2'),
			'download' => array('WikiFiles.php', 'WikiFileView', true, true),
		),
		// Special Pages
		'special' => array(
			'RecentChanges' => array('WikiHistory.php', 'WikiRecentChanges'),
			'Upload' =>  array('WikiFiles.php', 'WikiFileUpload'),
		)
	);

	// show error page for invalid titles
	if (!is_valid_pagename($_REQUEST['page'], $context['namespace']))
	{
		$namespaceGroup = 'normal';
		$subaction = 'view';

		$context['page_info'] = array(
			'id' => null,
			'title' => read_urlname($_REQUEST['page'], true),
			'name' => $context['current_page_name'],
			'namespace' => $context['namespace']['id'],
			'is_locked' => false,
		);

		// Setup tabs
		$context['wikimenu'] = array(
			'view' => array(
				'title' => $txt['wiki_view'],
				'url' => wiki_get_url($context['current_page_name']),
				'selected' => in_array($subaction, array('view')),
				'show' => true,
			),
		);

		$context['robot_no_index'] = true;

		// Template
		loadTemplate('WikiPage');
		$context['template_layers'][] = 'wikipage';
		$context['sub_template'] = 'not_found';
	}
	// Load Page info if namesapce isn't "Special" namespace
	elseif ($context['namespace']['type'] != 1)
	{
		if (empty($_REQUEST['page']))
			redirectexit($context['namespace']['url']);

		loadWikiPage();

		// Don't index older versions please or links to certain version
		if ($context['page_info']['id'] === null || !$context['page_info']['is_current'] || isset($_REQUEST['revision']) || isset($_REQUEST['old_revision']))
			$context['robot_no_index'] = true;

		$namespaceGroup = 'normal';
		$subaction = isset($_REQUEST['sa']) && isset($subActions[$namespaceGroup][$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'view';

		// Force page to be "view" for non existing and asked to, it's here to make correct tab highlight
		if ($context['page_info']['id'] === null && !empty($subActions[$namespaceGroup][$subaction][2]))
			$subaction = 'view';
		// Don't allow file actions on plain pages
		elseif (!isset($context['current_file']) && !empty($subActions[$namespaceGroup][$subaction][3]))
			$subaction = 'view';

		// Download image if asked to
		if (isset($context['current_file']) && $context['current_file']['is_image'] && isset($_REQUEST['image']))
			$subaction = 'download';

		// Don't index pages with invalid subaction
		if (!empty($_REQUEST['sa']) && $subaction != $_REQUEST['sa'])
			$context['robot_no_index'] = true;

		$context['can_edit_page'] = allowedTo('wiki_admin') || (allowedTo('wiki_edit') && !$context['page_info']['is_locked']);
		$context['can_lock_page'] = allowedTo('wiki_admin');

		// Don't let anyone create page if it's not "normal" page (ie. file)
		if ($context['namespace']['type'] != 0 && $context['namespace']['type'] != 5 && $context['page_info']['id'] === null)
			$context['can_edit_page'] = false;

		// Setup tabs
		$context['wikimenu'] = array(
			'view' => array(
				'title' => $txt['wiki_view'],
				'url' => wiki_get_url($context['current_page_name']),
				'selected' => in_array($subaction, array('view')),
				'show' => true,
			),
			'talk' => array(
				'title' => $txt['wiki_talk'],
				'url' => wiki_get_url(array(
					'page' => $context['current_page_name'],
					'sa' => 'talk',
				)),
				'selected' => in_array($subaction, array('talk')),
				'show' => !empty($modSettings['wikiTalkBoard']),
			),
			'edit' => array(
				'title' => $txt['wiki_edit'],
				'url' => wiki_get_url(array(
					'page' => $context['current_page_name'],
					'sa' => 'edit',
				)),
				'selected' => in_array($subaction, array('edit', 'edit2')),
				'show' => $context['can_edit_page'],
			),
			'history' => array(
				'title' => $txt['wiki_history'],
				'url' => wiki_get_url(array(
					'page' => $context['current_page_name'],
					'sa' => 'history',
				)),
				'selected' => in_array($subaction, array('history', 'diff')),
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
		$pageName =  wiki_urlname($_REQUEST['page'], $context['namespace']['id']);

		if (strpos($_REQUEST['page'], '/'))
			list ($_REQUEST['page'], $_REQUEST['sub_page']) = explode('/', $_REQUEST['page'], 2);
		else
			$_REQUEST['sub_page'] = '';

		$subaction = $_REQUEST['page'];

		$context['page_info'] = array(
			'id' => null,
			'title' => read_urlname($_REQUEST['page'], true),
			'name' => $pageName,
			'namespace' => $context['namespace']['id'],
			'is_locked' => false,
		);
	}

	// Redirect to page if needed
	if ($context['current_page_name'] != $context['page_info']['name'])
	{
		$newUrl = array('page' => $context['page_info']['name']);

		if (isset($_GET['image']))
			$newUrl[] = 'image';
		if (isset($_GET['sa']))
			$newUrl['sa'] = $_GET['sa'];
		redirectexit(wiki_get_url($newUrl));
	}

	// Name of current page
	$context['current_page_name'] = $context['page_info']['name'];

	// Base array for calling wiki_get_url for this page
	$context['wiki_url'] = array(
		'page' => $context['current_page_name'],
		'revision' => isset($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : null,
	);
	$context['current_page_url'] = wiki_get_url($context['wiki_url']);

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

?>