<?php
/**********************************************************************************
* install_main.php                                                                *
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

global $txt, $smcFunc, $db_prefix, $modSettings;
global $wiki_version, $addSettings, $permissions, $tables, $sourcedir;

if (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please run wiki_install/index.php instead');

require_once($sourcedir . '/Subs-Post.php');

// Step 1: Do tables
doTables($tables);

// Step 2: Do Settings
doSettings($addSettings);

// Step 3: Update admin features
updateAdminFeatures('wiki', !empty($modSettings['wikiEnabled']));

// Step 3: Do Permissions
doPermission($permissions);

// Step 4: Install SMF Wiki Package server
$request = $smcFunc['db_query']('', '
	SELECT COUNT(*)
	FROM {db_prefix}package_servers
	WHERE name = {string:name}',
	array(
		'name' => 'SMF Wiki Package Server',
	)
);

list ($count) = $smcFunc['db_fetch_row']($request);
$smcFunc['db_free_result']($request);

if ($count == 0)
	$smcFunc['db_insert']('insert',
		'{db_prefix}package_servers',
		array(
			'name' => 'string',
			'url' => 'string',
		),
		array(
			'SMF Wiki Package Server',
			'http://download.smfwiki.net',
		),
		array()
	);

// Step 5: Install default namespace
$smcFunc['db_insert']('ignore',
	'{db_prefix}wiki_namespace',
	array(
		'namespace' => 'string',
		'ns_prefix' => 'string',
		'default_page' => 'string',
		'namespace_type' => 'int',
	),
	array(
		array(
			'',
			'',
			'Main_Page',
			0,
		),
		array(
			'Template',
			'Templates',
			'Index',
			0,
		),
	),
	array('namespace')
);

// Step 6: Install other namespaces
$specialNamespaces = array(
	1 => array('name' => 'Special', 'default_page' => 'List'),
	2 => array('name' => 'File', 'default_page' => 'List'),
	3 => array('name' => 'Image', 'default_page' => 'List'),
	4 => array('name' => 'SMFWiki', 'default_page' => 'List'),
	5 => array('name' => 'Category', 'default_page' => 'List'),
);

foreach ($specialNamespaces as $type => $data)
{
	$request = $smcFunc['db_query']('', '
		SELECT namespace, ns_prefix, default_page, namespace_type
		FROM {db_prefix}wiki_namespace
		WHERE namespace_type = {int:type}',
		array(
			'type' => $type,
		)
	);

	$row = $smcFunc['db_fetch_assoc']($request);

	if (!$row)
		$smcFunc['db_insert']('replace',
			'{db_prefix}wiki_namespace',
			array(
				'namespace' => 'string',
				'ns_prefix' => 'string',
				'default_page' => 'string',
				'namespace_type' => 'int',
			),
			array(
				array(
					$data['name'],
					'',
					$data['default_page'],
					$type,
				),
			),
			array('namespace')
		);

	$smcFunc['db_free_result']($request);
}

// Step 7: Create and update default pages (incase they are not edited before)
$defaultPages = array(
	array(
		'namespace' => '',
		'name' => 'Main_Page',
		'body' => 'SMF Wiki {{wikiversion}} installed!',
		'locked' => true,
	),
	array(
		'namespace' => 'SMFWiki',
		'name' => 'Sidebar',
		'body' => '* __navigation__' . "\n" . '** Main_Page|__main_page__',
		'locked' => true,
	),
);

foreach ($defaultPages as $page)
{
	$page['body'] = $smcFunc['htmlspecialchars']($page['body'], ENT_QUOTES);
	preparsecode($page['body']);

	createPage($page['namespace'], $page['name'], $page['body'], $page['locked']);
}

?>