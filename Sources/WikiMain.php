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

function WikiMain()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	$context['current_page'] = loadWikiPage($_REQUEST['page'], $_REQUEST['namespace'], isset($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : 0);
	$context['base_url'] = wiki_get_url(array(
		'page' => (!empty($_REQUEST['namespace']) ? $_REQUEST['namespace'] . ':' : '' ) . $_REQUEST['page'],
	));
	$context['url'] = wiki_get_url(array(
		'page' => (!empty($_REQUEST['namespace']) ? $_REQUEST['namespace'] . ':' : '' ) . $_REQUEST['page'],
		'revision' => isset($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : null,
	));

	$actionArray = array(
		'view' => array('ViewPage.php', 'ViewPage'),
		'talk' => array('TalkPage.php', 'ViewTalkPage'),
		'talk2' => array('TalkPage.php', 'ViewTalkPage2'),
		'diff' => array('ViewPage.php', 'DiffPage'),
		'history' => array('WikiHistory.php', 'ViewPageHistory'),
		'edit' => array('EditPage.php', 'EditPage'),
		'edit2' => array('EditPage.php', 'EditPage2'),
	);

	if (!isset($_REQUEST['action']) || !isset($actionArray[$_REQUEST['action']]))
		$_REQUEST['action'] = 'view';

	// Menu
	$context['wikimenu'] = array(
		'view' => array(
			'title' => $txt['wiki_view'],
			'url' => $context['url'],
			'selected' => in_array($_REQUEST['action'], array('view')),
			'show' => true,
		),
		'talk' => array(
			'title' => $txt['wiki_talk'],
			'url' => $context['url'] . '?action=talk',
			'selected' => in_array($_REQUEST['action'], array('talk')),
			'show' => true,
		),
		'edit' => array(
			'title' => $txt['wiki_edit'],
			'url' => $context['url'] . (strpos($context['url'], '?') !== false ? ';' : '?') . 'action=edit',
			'selected' => in_array($_REQUEST['action'], array('edit', 'edit2')),
			'show' => allowedTo('wiki_edit'),
			'class' => 'margin',
		),
		'history' => array(
			'title' => $txt['wiki_history'],
			'url' => $context['base_url'] . '?action=history',
			'selected' => in_array($_REQUEST['action'], array('history', 'diff')),
			'show' => true,
			'class' => allowedTo('wiki_edit') ? '' : 'margin',
		),
	);

	// Template
	loadTemplate('WikiPage');
	$context['template_layers'][] = 'wikipage';

	if (!$context['current_page'] && !in_array($_REQUEST['action'], array('edit', 'edit2')))
	{
		$context['linktree'][] = array(
			'url' => $context['url'],
			'name' => read_urlname($_REQUEST['page'], true),
		);

		return 'show_not_found_error';
	}
	elseif (!$context['current_page'])
	{
		$context['linktree'][] = array(
			'url' => $context['url'],
			'name' => read_urlname($_REQUEST['page'], true),
		);

		$context['current_page'] = array(
			'title' => read_urlname($_REQUEST['page'], true),
			'namespace' => $_REQUEST['namespace'],
			'name' => $_REQUEST['page'],
			'content' => '',
		);
	}
	else
	{
		$context['linktree'][] = array(
			'url' => $context['url'],
			'name' => $context['current_page']['title'],
		);
	}

	require_once($sourcedir . '/' . $actionArray[$_REQUEST['action']][0]);
	return $actionArray[$_REQUEST['action']][1];
}

?>