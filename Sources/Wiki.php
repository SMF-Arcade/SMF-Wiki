<?php
/**********************************************************************************
* Wiki.php                                                                        *
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

function Wiki()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir, $wiki_version;

	require_once($sourcedir . '/Subs-Wiki.php');

	// Wiki Version
	$wiki_version = '{version}';

	loadTemplate('Wiki', array('wiki'));
	loadLanguage('Wiki');

	// Santise Namespace
	if (strpos($_REQUEST['page'], ':'))
		list ($_REQUEST['namespace'], $_REQUEST['page']) = explode(':', $_REQUEST['page'], 2);
	else
		$_REQUEST['namespace'] = '';

	$namespace = ucfirst($smcFunc['strtolower'](str_replace(array(' ', '[', ']', '{', '}'), '_', $_REQUEST['namespace'])));
	$page = $smcFunc['ucfirst'](str_replace(array(' ', '[', ']', '{', '}'), '_', $_REQUEST['page']));

	if ($namespace != $_REQUEST['namespace'] || $page != $_REQUEST['page'])
		redirectexit(wiki_get_url(array(
			'page' => (!empty($namespace) ? $namespace . ':' : '') . $page,
		)));

	// Linktree
	$context['linktree'][] = array(
		'url' => wiki_get_url(array('page' => 'Main_Page')),
		'name' => $txt['wiki'],
	);

	// Template
	$context['template_layers'][] = 'wiki';

	// Load Namespace unless it's Special
	if ($namespace != 'Special')
	{
		$request = $smcFunc['db_query']('', '
			SELECT namespace, ns_prefix, page_header, page_footer, default_page
			FROM {db_prefix}wiki_namespace
			WHERE namespace = {string:namespace}',
			array(
				'namespace' => $_REQUEST['namespace'],
			)
		);

		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!$row)
			fatal_lang_error('wiki_namespace_not_found', false, array(read_urlname($_REQUEST['namespace'])));

		$context['namespace'] = array(
			'id' => $row['namespace'],
			'prefix' => $row['ns_prefix'],
			'url' => wiki_get_url(array(
				'page' => (!empty($row['namespace']) ? $row['namespace'] . ':' : '') . $row['default_page']
			)),
		);

		if (empty($_REQUEST['page']))
			redirectexit($context['namespace']['url']);

		if (!empty($context['namespace']['prefix']))
		{
			$context['linktree'][] = array(
				'url' =>  $context['namespace']['url'],
				'name' => $context['namespace']['prefix'],
			);
		}
	}

	$context['title'] = str_replace('_', ' ', $page);

	// Normal Namespace
	if ($namespace != 'Special')
	{
		require_once($sourcedir . '/WikiMain.php');
		return WikiMain();
	}
	// Special Namespace
	elseif ($namespace == 'Special')
	{
		if (strpos($_REQUEST['page'], '/'))
			list ($_REQUEST['params'], $_REQUEST['page']) = explode('/', $_REQUEST['page'], 2);
		else
			$_REQUEST['params'] = '';

		$actionArray = array(
		);

		if (!isset($_REQUEST['page']) || !isset($actionArray[$_REQUEST['page']]))
			fatal_lang_error('wiki_action_not_found', false, array($_REQUEST['page']));

		require_once($sourcedir . '/' . $actionArray[$_REQUEST['page']][0]);
		return $actionArray[$_REQUEST['page']][1];
	}

	fatal_error('Don\'t hack me plz');
}

?>