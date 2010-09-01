<?php
/**
 * Main functions
 *
 * @package core
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function loadWiki($mode = '', $prefix = null)
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir, $wiki_version, $wiki_prefix, $db_prefix;

	// Set up wiki_prefix (for running multiple wikis)
	$wiki_prefix = !empty($prefix) ? $prefix : $db_prefix . 'wiki_';
	
	require_once($sourcedir . '/Subs-Wiki.php');
	
	loadClassFile('Class-WikiPage.php');
	loadClassFile('Class-WikiParser.php');
	loadClassFile('WikiExt-Base.php');
	
	// Wiki Version
	$wiki_version = '0.2';

	loadTemplate('Wiki', array('wiki'));
	loadLanguage('Wiki');

	// Normal mode
	if ($mode == '')
	{
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
				'noindex' => create_function('&$wiki_parser', '$wiki_parser->pageSettings[\'no_index\'] = true;'),
				'index' => create_function('&$wiki_parser', '$wiki_parser->pageSettings[\'no_index\'] = false;'),
				'notoc' => create_function('&$wiki_parser', '$wiki_parser->pageSettings[\'hide_toc\'] = true;'),
			),
			// Hash Tags
			// format 'tag' => array(function to call)
			'hash_tags' => array(
				'redirect' => create_function('&$wiki_parser, $firstParam, $params', '
					if ($firstParam[0][\'name\'] != \'wikilink\')
						return false;
					$wiki_parser->pageSettings[\'redirect_to\'] = $firstParam[0][\'firstParam\'];'),
			),
			// XML Tags
			// format 'tag' (lowercase only) => array(function to call)
			// function: (&wiki_parser, content, attributes)
			// returns html code for display
			'tags' => array(
				'test_tag' => array(create_function('&$wiki_parser, $content, $attributes', 'return $content;')),
			),
		);
		
		// Special Pages
		$context['wiki_special_pages'] = array(
			'RecentChanges' => array(
				'file' => 'WikiHistory.php',
				'function' => 'WikiRecentChanges',
			),
			'SpecialPages' => array(
				'file' => 'WikiSpecialPages.php',
				'function' => 'WikiListOfSpecialPages',		
			),
			'Upload' =>  array(
				'file' => 'WikiFiles.php',
				'function' => 'WikiFileUpload',
			),	
		);
	}
	// Admin Mode
	elseif ($mode == 'admin')
		loadTemplate('WikiAdmin');
		
	loadNamespace();
}

function Wiki($standalone = false, $prefix = null)
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;
	
	loadWiki('', $prefix);
	
	// Linktree
	$context['linktree'][] = array(
		'url' => $context['namespaces']['']['url'],
		'name' => $txt['wiki'],
	);

	// Go to default page if not defined
	if (!isset($_REQUEST['page']) || empty($_REQUEST['page']))
		redirectexit($context['namespaces']['']['url']);
	
	// Parse namespace from page
	list ($_REQUEST['namespace'], $_REQUEST['page']) = wiki_parse_url_name($_REQUEST['page']);

	// Set $context['namepsace'] to reference to current namespace
	$context['namespace'] = &$context['namespaces'][$_REQUEST['namespace']];

	// Add namespace to linktree if necassary
	if (!empty($context['namespace']['prefix']))
		$context['linktree'][] = array(
			'url' =>  $context['namespace']['url'],
			'name' => $context['namespace']['prefix'],
		);
		
	// Variable for current page
	$context['current_page_name'] = wiki_get_url_name($_REQUEST['page'], $_REQUEST['namespace'], false);

	// Subactions
	$subActions = array(
		// action => array(file, function, [requires existing page], [requires file])
		'view' => array('WikiPage.php', 'ViewPage'),
		'talk' => array('WikiTalkPage.php', 'ViewTalkPage', true),
		'talk2' => array('WikiTalkPage.php', 'ViewTalkPage2', true),
		'diff' => array('WikiPage.php', 'DiffPage', true),
		'history' => array('WikiHistory.php', 'ViewPageHistory', true),
		'edit' => array('WikiEditPage.php', 'EditPage'),
		'edit2' => array('WikiEditPage.php', 'EditPage2'),
		'delete' => array('WikiEditPage.php', 'DeletePage'),
		'delete2' => array('WikiEditPage.php', 'DeletePage2'),
		'restore' => array('WikiEditPage.php', 'DeletePage'),
		'restore2' => array('WikiEditPage.php', 'DeletePage2'),
		'download' => array('WikiFiles.php', 'WikiFileView', true, true),
		'purge' => array('WikiPage.php', 'CleanCache'),
	);
	
	// Don't allow talk actions if it's not enalbed
	if (empty($modSettings['wikiTalkBoard']))
		unset($subActions['talk'], $subActions['talk2']);

	// show error page for invalid titles
	if (!is_valid_pagename($_REQUEST['page'], $context['namespace']))
	{
		$namespaceGroup = 'normal';
		$subaction = 'view';

		$context['page_info'] = array(
			'id' => null,
			'title' => get_default_display_title($_REQUEST['page'], true),
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
		if (!$context['page_info']->exists || $context['page_info']->deleted || !$context['page_info']->is_current || isset($_REQUEST['revision']) || isset($_REQUEST['old_revision']))
			$context['robot_no_index'] = true;

		$namespaceGroup = 'normal';
		$subaction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'view';

		// Force page to be "view" for non existing and asked to, it's here to make correct tab highlight
		if (!$context['page_info']->exists && !empty($subActions[$subaction][2]))
			$subaction = 'view';
		// Don't allow file actions on plain pages
		elseif (!isset($context['current_file']) && !empty($subActions[$subaction][3]))
			$subaction = 'view';

		// Download image if asked to
		if (isset($context['current_file']) && $context['current_file']['is_image'] && isset($_REQUEST['image']))
			$subaction = 'download';

		$context['can_edit_page'] = allowedTo('wiki_admin') || (allowedTo('wiki_edit') && !$context['page_info']['is_locked']);
		$context['can_lock_page'] = allowedTo('wiki_admin');
		$context['can_delete_page'] = allowedTo('wiki_admin');
		$context['can_delete_permanent'] = allowedTo('wiki_admin');
		$context['can_restore_page'] = allowedTo('wiki_admin');
		
		$context['can_create_page'] = allowedTo('wiki_edit');
		$context['can_edit_page'] = $context['can_edit_page'] && !$context['page_info']->deleted;

		// Don't let anyone create or edit page if it's not "normal" page (ie. file)
		if ($context['namespace']['type'] != 0 && $context['namespace']['type'] != 4 && $context['namespace']['type'] != 5 && !$context['page_info']->exists)
			$context['can_create_page'] = $context['can_edit_page'] = false;

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
				'show' => !empty($modSettings['wikiTalkBoard']) && empty($context['page_info']['is_deleted']),
			),
			'edit' => array(
				'title' => !$context['can_create_page'] ? $txt['wiki_edit'] : $txt['wiki_create'],
				'url' => wiki_get_url(array(
					'page' => $context['current_page_name'],
					'sa' => 'edit',
				)),
				'selected' => in_array($subaction, array('edit', 'edit2')),
				'show' => $context['can_create_page'] || $context['can_edit_page'],
			),
			'delete' => array(
				'title' => $txt['wiki_delete'],
				'url' => wiki_get_url(array(
					'page' => $context['current_page_name'],
					'sa' => 'delete',
				)),
				'selected' => in_array($subaction, array('delete', 'delete2')),
				'show' => $context['can_delete_page'],				
			),
			'restore' => array(
				'title' => $txt['wiki_restore'],
				'url' => wiki_get_url(array(
					'page' => $context['current_page_name'],
					'sa' => 'restore',
				)),
				'selected' => in_array($subaction, array('restore', 'restore2')),
				'show' => $context['can_restore_page'] && $context['page_info']->deleted,
			),
			'history' => array(
				'title' => $txt['wiki_history'],
				'url' => wiki_get_url(array(
					'page' => $context['current_page_name'],
					'sa' => 'history',
				)),
				'selected' => in_array($subaction, array('history', 'diff')),
				'show' => $context['page_info']->exists && !$context['page_info']->deleted,
			),
		);
		
		// Don't index pages with invalid subaction
		if (!empty($_REQUEST['sa']) && $subaction != $_REQUEST['sa'])
			$context['robot_no_index'] = true;
		else
		{
			foreach ($context['wikimenu'] as $id => $menu_item)
			{
				if (!$menu_item['show'])
				{
					if ($menu_item['selected'])
					{
						$context['wikimenu'][$id]['selected'] = false;
						
						// Use view action then
						$subaction = 'view';
						$context['wikimenu'][$subaction]['selected'] = true;
					}
					
					unset($context['wikimenu'][$id]);
						
					continue;
				}
			}
		}
		
		if (isset($context['wiki_page']->pageSettings['redirect_to']) && !isset($_REQUEST['no_redirect']))
			$context['redirect_page_name'] = wiki_get_url_name($context['wiki_page']->pageSettings['redirect_to']);

		// Template
		loadTemplate('WikiPage');
		$context['template_layers'][] = 'wikipage';
	}
	// Load required info for Special pages
	else
	{
		$namespaceGroup = 'special';
		$pageName =  wiki_get_url_name($_REQUEST['page'], $context['namespace']['id']);

		if (strpos($_REQUEST['page'], '/'))
			list ($_REQUEST['page'], $_REQUEST['sub_page']) = explode('/', $_REQUEST['page'], 2);
		else
			$_REQUEST['sub_page'] = '';

		$subaction = $_REQUEST['page'];

		$context['page_info'] = array(
			'id' => null,
			'title' => get_default_display_title($_REQUEST['page'], $context['namespace']['id']),
			'name' => $pageName,
			'namespace' => $context['namespace']['id'],
			'is_locked' => false,
		);
	}

	// Redirect to page if needed
	if (isset($context['redirect_page_name']) || $context['current_page_name'] != $context['page_info']->page)
	{
		$newUrl = array('page' => isset($context['redirect_page_name']) ? $context['redirect_page_name'] : $context['page_info']->page);

		if (isset($_GET['image']))
			$newUrl[] = 'image';
		if (isset($_GET['sa']))
			$newUrl['sa'] = $_GET['sa'];
		if (isset($context['redirect_page_name']))
			$newUrl['redirect_from'] = $context['page_info']['name'];
			
		redirectexit(wiki_get_url($newUrl));
	}

	// Base array for calling wiki_get_url for this page
	$context['wiki_url'] = array(
		'page' => $context['current_page_name'],
		'revision' => isset($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : null,
	);
	$context['current_page_url'] = wiki_get_url($context['wiki_url']);

	// Load Navigation
	$context['wiki_navigation'] = cache_quick_get('wiki-navigation', 'Subs-Wiki.php', 'loadWikiMenu', array());

	// Add toolbox to navigation menu
	$context['wiki_navigation'][] = array(
		'title' => $txt['wiki_toolbox'],
		'items' => array(
			array(
				'title' => $txt['wiki_recent_changes'],
				'page' => wiki_get_url_name('RecentChanges', $context['namespace_special']['id']),
				'url' => wiki_get_url(wiki_get_url_name('RecentChanges', $context['namespace_special']['id'])),
				'selected' => $context['current_page_name'] == wiki_get_url_name('RecentChanges', $context['namespace_special']['id']),
			),
			array(
				'title' => $txt['wiki_upload_file'],
				'page' => wiki_get_url_name('Upload', $context['namespace_special']['id']),
				'url' => wiki_get_url(wiki_get_url_name('Upload', $context['namespace_special']['id'])),
				'selected' => $context['current_page_name'] == wiki_get_url_name('RecentChanges', $context['namespace_special']['id']),
			)
		),
	);
	
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
	
	if (!empty($context['page_info']->page_tree))
		foreach ($context['page_info']->page_tree as $page)
		{
			$context['linktree'][] = array(
				'url' => wiki_get_url($page['name']),
				'name' => $page['title'],
			);		
		}

	// Have display name of page in variable
	$context['current_page_title'] = $context['page_info']->title;

	// Special page?
	if ($namespaceGroup == 'special')
	{
		// Template
		loadTemplate('WikiPage');
		$context['template_layers'][] = 'wikipage';
			
		// Invalid special page?
		if (!isset($context['wiki_special_pages'][$subaction]))
		{
			$namespaceGroup = 'normal';
			$subaction = 'view';
			
			$context['sub_template'] = 'not_found';
			
			require_once($sourcedir . '/' . $subActions[$subaction][0]);
			$subActions[$subaction][1]();
			
			return;
		}
		
		require_once($sourcedir . '/' . $context['wiki_special_pages'][$subaction]['file']);
		$context['wiki_special_pages'][$subaction]['function']();
		
		return;
	}
	
	// Add Page to Link tree
	$context['linktree'][] = array(
		'url' => $context['current_page_url'],
		'name' => $context['current_page_title'],
	);

	// Page Title
	$context['page_title'] = $context['forum_name'] . ' - ' . un_htmlspecialchars($context['current_page_title']);

	require_once($sourcedir . '/' . $subActions[$subaction][0]);
	$subActions[$subaction][1]();
}

?>