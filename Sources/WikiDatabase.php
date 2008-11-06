<?php
/**********************************************************************************
* WikiDatabase.php                                                                *
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

/* Contains information about database tables

	void doTables()
		- ???

	void doSettings()
		- ???

	void doPermission()
		- ???

	void installDefaultData()
		- ???
*/

global $boarddir, $boardurl, $smcFunc;

$wiki_version = '0.1';

// Settings array
$addSettings = array(
	'wikiEnabled' => array(1, false),
	'wikiStandalone' => array(0, false),
	'wikiStandaloneUrl' => array('', false),
);

// Permissions array
$permissions = array(
	'wiki_access' => array(-1, 0, 2),
);

// Tables array
$tables = array(
	// Namespaces
	'wiki_namespace' => array(
		'name' => 'wiki_namespace',
		'columns' => array(
			array(
				'name' => 'namespace',
				'type' => 'varchar',
				'size' => '30',
				'default' => '',
			),
			array(
				'name' => 'ns_prefix',
				'type' => 'varchar',
				'size' => '45',
				'default' => '',
			),
			array(
				'name' => 'page_header',
				'type' => 'text',
			),
			array(
				'name' => 'page_footer',
				'type' => 'text',
			),
			array(
				'name' => 'default_page',
				'type' => 'varchar',
				'size' => '255',
				'default' => 'Index',
			),
		),
		'indexes' => array(
			array(
				'type' => 'primary',
				'columns' => array('namespace')
			),
		)
	),
	// Page names
	'wiki_pages' => array(
		'name' => 'wiki_pages',
		'columns' => array(
			array(
				'name' => 'id_page',
				'type' => 'int',
				'auto' => true,
				'unsigned' => true,
			),
			array(
				'name' => 'title',
				'type' => 'varchar',
				'size' => '255',
				'default' => '',
			),
			array(
				'name' => 'namespace',
				'type' => 'varchar',
				'size' => '30',
				'default' => '',
			),
			array(
				'name' => 'id_revision_current',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
			array(
				'name' => 'id_member',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
		),
		'indexes' => array(
			array(
				'type' => 'primary',
				'columns' => array('id_page')
			),
		)
	),
	// Page Content
	'wiki_content' => array(
		'name' => 'wiki_content',
		'columns' => array(
			array(
				'name' => 'id_revision',
				'type' => 'int',
				'auto' => true,
				'unsigned' => true,
			),
			array(
				'name' => 'id_page',
				'type' => 'int',
				'unsigned' => true,
			),
			array(
				'name' => 'id_author',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
			array(
				'name' => 'content',
				'type' => 'text',
			),
			array(
				'name' => 'comment',
				'type' => 'varchar',
				'size' => '255',
				'default' => '',
			),
		),
		'indexes' => array(
			array(
				'type' => 'primary',
				'columns' => array('id_revision')
			),
			array(
				'name' => 'id_page',
				'type' => 'index',
				'columns' => array('id_page')
			),
		)
	),
);

function doTables($tbl, $tables, $columnRename = array(), $smf2 = true, $remove = false)
{
	global $smcFunc, $db_prefix, $db_type;

	foreach ($tables as $table)
	{
		$table_name = $db_prefix . $table['name'];

		if (!empty($columnRename) && in_array($table_name, $tbl))
		{
			$ctable = $smcFunc['db_table_structure']($table_name, array('no_prefix' => true));

			foreach ($ctable['columns'] as $column)
			{
				if (isset($columnRename[$column['name']]))
				{
					$old_name = $column['name'];
					$column['name'] = $columnRename[$column['name']];
					$smcFunc['db_change_column']($table_name, $old_name, $column, array('no_prefix' => true));
				}
			}
		}

		if (empty($table['smf']))
			$smcFunc['db_create_table']($table_name, $table['columns'], $table['indexes'], array('no_prefix' => true));

		if (in_array($table_name, $tbl))
		{
			foreach ($table['columns'] as $column)
			{
				$smcFunc['db_add_column']($table_name, $column, array('no_prefix' => true));

				// TEMPORARY until SMF package functions works with this
				if (isset($column['unsigned']) && $db_type == 'mysql')
				{
					$column['size'] = isset($column['size']) ? $column['size'] : null;

					list ($type, $size) = $smcFunc['db_calculate_type']($column['type'], $column['size']);
					if ($size !== null)
						$type = $type . '(' . $size . ')';

					$smcFunc['db_query']('', "
						ALTER TABLE $table_name
						CHANGE COLUMN $column[name] $column[name] $type UNSIGNED " . (empty($column['null']) ? 'NOT NULL' : '') . ' ' .
							(empty($column['default']) ? '' : "default '$column[default]'") . ' ' .
							(empty($column['auto']) ? '' : 'auto_increment') . ' ',
						'security_override'
					);
				}
			}

			// Update table
			foreach ($table['indexes'] as $index)
			{
				if ($index['type'] != 'primary')
					$smcFunc['db_add_index']($table_name, $index, array('no_prefix' => true));
			}
		}
	}
}

function doSettings($addSettings, $smf2 = true)
{
	global $smcFunc;

	$update = array();

	foreach ($addSettings as $variable => $s)
	{
		list ($value, $overwrite) = $s;

		$result = $smcFunc['db_query']('', '
			SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			array(
				'variable' => $variable,
			)
		);

		if ($smcFunc['db_num_rows']($result) == 0 || $overwrite == true)
			$update[$variable] = $value;
	}

	if (!empty($update))
		updateSettings($update);
}

function doPermission($permissions, $smf2 = true)
{
	global $smcFunc;

	$perm = array();

	foreach ($permissions as $permission => $default)
	{
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}permissions
			WHERE permission = {string:permission}',
			array(
				'permission' => $permission
			)
		);

		list ($num) = $smcFunc['db_fetch_row']($result);

		if ($num == 0)
		{
			foreach ($default as $grp)
				$perm[] = array($grp, $permission);
		}
	}

	$group = $smf2 ? 'id_group': 'ID_GROUP';

	if (empty($perm))
		return;

	$smcFunc['db_insert']('insert',
		'{db_prefix}permissions',
		array(
			$group => 'int',
			'permission' => 'string'
		),
		$perm,
		array()
	);
}

function installDefaultData($forced = false)
{
	global $smcFunc, $wiki_version, $modSettings, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}package_servers
		WHERE name = {string:name}',
		array(
			'name' => 'SMF Arcade Package Server',
		)
	);

	list ($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($count == 0 || $forced)
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}package_servers',
			array(
				'name' => 'string',
				'url' => 'string',
			),
			array(
				'SMF Arcade Package Server',
				'http://download.smfarcade.info',
			),
			array()
		);
	}

	$smcFunc['db_insert']('ignore',
		'{db_prefix}wiki_namespace',
		array(
			'namespace' => 'string',
			'ns_prefix' => 'string',
			'page_header' => 'string',
			'page_footer' => 'string',
			'default_page' => 'string',
		),
		array(
			array(
				'',
				'',
				'',
				'',
				'Main_Page',
			),
			array(
				'Template',
				'Templates',
				'',
				'',
				'Index',
			),
		),
		array('namespace')
	);

	$defaultPages = array(
		array(
			'namespace' => '',
			'name' => 'Main_Page',
			'body' => 'SMF Wiki ' . $wiki_version . ' installed!',
		),
		array(
			'namespace' => 'Template',
			'name' => 'Navigation',
			'body' => '__navigation__' . "\n" . ':Main_Page|__main_page__',
		),
	);

	foreach ($defaultPages as $page)
	{
		$page['body'] = $smcFunc['htmlspecialchars']($page['body'], ENT_QUOTES);
		preparsecode($page['body']);

		createPage($page['namespace'], $page['name'], $page['body']);
	}

	updateSettings(array('wikiVersion' => $wiki_version));
}

// Function to create page
function createPage($namespace, $name, $body, $exists = 'ignore')
{
	global $smcFunc, $wiki_version, $user_info, $modSettings;

	$comment = 'SMF Wiki default page';

	$request = $smcFunc['db_query']('', '
		SELECT info.id_page, con.comment
		FROM {db_prefix}wiki_pages AS info
			INNER JOIN {db_prefix}wiki_content AS con ON (con.id_revision = info.id_revision_current
				AND con.id_page = info.id_page)
		WHERE info.title = {string:article}
			AND info.namespace = {string:namespace}',
		array(
			'article' => $name,
			'namespace' => $namespace,
			'revision' => !empty($revision) ? $revision : 'info.id_revision_current',
		)
	);

	if ($smcFunc['db_num_rows']($request) > 0)
	{
		list ($id_page, $comment2) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if ($comment2 != $comment && $exists != 'update')
			return false;
	}
	else
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}wiki_pages',
			array(
				'title' => 'string-255',
				'namespace' => 'string-255',
			),
			array(
				$name,
				$namespace,
			),
			array('id_page')
		);

		$id_page = $smcFunc['db_insert_id']('{db_prefix}wiki_pages', 'id_article');
	}

	$smcFunc['db_insert']('insert',
		'{db_prefix}wiki_content',
		array(
			'id_page' => 'int',
			'id_author' => 'int',
			'timestamp' => 'int',
			'content' => 'string',
			'comment' => 'string-255',
		),
		array(
			$id_page,
			$user_info['id'],
			time(),
			$body,
			$comment,
		),
		array('id_revision')
	);

	$id_revision = $smcFunc['db_insert_id']('{db_prefix}articles_content', 'id_revision');

	$smcFunc['db_query']('' ,'
		UPDATE {db_prefix}wiki_pages
		SET id_revision_current = {int:revision}
		WHERE id_page = {int:page}',
		array(
			'page' => $id_page,
			'revision' => $id_revision,
		)
	);

	return true;
}

?>