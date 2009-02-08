<?php
/**********************************************************************************
* WikiDatabase.php                                                                *
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
	'wikiAttachmentsDir' => array('', false),
	'wikiStandalone' => array(0, false),
	'wikiStandaloneUrl' => array('', false),
	'wikiTalkBoard' => array(0, false),
	'wikiVersion' => array($wiki_version, true),
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
			array(
				'name' => 'namespace_type',
				'type' => 'int',
				'unsigned' => true,
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
				'name' => 'is_locked',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
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
			array(
				'name' => 'id_topic',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
			array(
				'name' => 'id_file',
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
				'name' => 'id_file',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
			array(
				'name' => 'timestamp',
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
	// Files
	'wiki_files' => array(
		'name' => 'wiki_files',
		'columns' => array(
			array(
				'name' => 'id_file',
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
				'name' => 'localname',
				'type' => 'varchar',
				'size' => '255',
				'default' => '',
			),
			array(
				'name' => 'mime_type',
				'type' => 'varchar',
				'size' => '35',
				'default' => '',
			),
			array(
				'name' => 'file_ext',
				'type' => 'varchar',
				'size' => '10',
				'default' => '',
			),
			array(
				'name' => 'is_current',
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
			array(
				'name' => 'timestamp',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
			array(
				'name' => 'filesize',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
			array(
				'name' => 'img_width',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
			array(
				'name' => 'img_height',
				'type' => 'int',
				'default' => 0,
				'unsigned' => true,
			),
		),
		'indexes' => array(
			array(
				'type' => 'primary',
				'columns' => array('id_file')
			),
			array(
				'type' => 'index',
				'columns' => array('id_page', 'is_current')
			),
		)
	),
);

function doTables($tbl, $tables, $columnRename = array(), $smf2 = true)
{
	global $smcFunc, $db_prefix, $db_type;

	$log = array();

	foreach ($tables as $table)
	{
		$table_name = $table['name'];

		// Renames in this table?
		if (!empty($table['rename']))
		{
			$oldTable = $smcFunc['db_table_structure']($table_name);

			foreach ($oldTable['columns'] as $column)
			{
				if (isset($table['rename'][$column['name']]))
				{
					$old_name = $column['name'];
					$column['name'] = $table['rename'][$column['name']];

					$smcFunc['db_change_column']($table_name, $old_name, $column);
				}
			}
		}

		// Global renames? (should be avoided)
		if (!empty($columnRename) && in_array($db_prefix . $table_name, $tbl))
		{
			$currentTable = $smcFunc['db_table_structure']($table_name);

			foreach ($currentTable['columns'] as $column)
			{
				if (isset($columnRename[$column['name']]))
				{
					$old_name = $column['name'];
					$column['name'] = $columnRename[$column['name']];
					$smcFunc['db_change_column']($table_name, $old_name, $column);
				}
			}
		}

		// Create table
		if (!in_array($db_prefix . $table_name, $tbl))
			$smcFunc['db_create_table']($table_name, $table['columns'], $table['indexes']);
		// Update table
		else
		{
			$currentTable = $smcFunc['db_table_structure']($table_name);

			// Check that all columns are in
			foreach ($table['columns'] as $id => $col)
			{
				$exists = false;

				// TODO: Check that definition is correct
				foreach ($currentTable['columns'] as $col2)
				{
					if ($col['name'] === $col2['name'])
					{
						$exists = true;
						break;
					}
				}

				// Add missing columns
				if (!$exists)
					$smcFunc['db_add_column']($table_name, $col);

				// TEMPORARY until SMF package functions works with this
				if (isset($column['unsigned']) && $db_type == 'mysql')
				{
					$column['size'] = isset($column['size']) ? $column['size'] : null;

					list ($type, $size) = $smcFunc['db_calculate_type']($column['type'], $column['size']);
					if ($size !== null)
						$type = $type . '(' . $size . ')';

					$smcFunc['db_query']('', "
						ALTER TABLE {db_prefix}$table_name
						CHANGE COLUMN $column[name] $column[name] $type UNSIGNED " . (empty($column['null']) ? 'NOT NULL' : '') . ' ' .
							(empty($column['default']) ? '' : "default '$column[default]'") . ' ' .
							(empty($column['auto']) ? '' : 'auto_increment') . ' ',
						'security_override'
					);
				}
			}

			// Remove any unnecassary columns
			foreach ($currentTable['columns'] as $col)
			{
				$exists = false;

				foreach ($table['columns'] as $col2)
				{
					if ($col['name'] === $col2['name'])
					{
						$exists = true;
						break;
					}
				}

				if (!$exists && isset($table['upgrade']['columns'][$col['name']]))
				{
					if ($table['upgrade']['columns'][$col['name']] == 'drop')
						$smcFunc['db_remove_column']($table_name, $col['name']);
				}
				elseif (!$exists && empty($table['smf']))
					$log[] = sprintf('Table %s has non-required column %s', $table_name, $col['name']);
			}

			// Check that all indexes are in and correct
			foreach ($table['indexes'] as $id => $index)
			{
				$exists = false;

				foreach ($currentTable['indexes'] as $index2)
				{
					// Primary is special case
					if ($index['type'] == 'primary' && $index2['type'] == 'primary')
					{
						$exists = true;

						if ($index['columns'] !== $index2['columns'])
						{
							$smcFunc['db_remove_index']($table_name, 'primary');
							$smcFunc['db_add_index']($table_name, $index);
						}

						break;
					}
					// Make sure index is correct
					elseif (isset($index['name']) && isset($index2['name']) && $index['name'] == $index2['name'])
					{
						$exists = true;

						// Need to be changed?
						if ($index['type'] != $index2['type'] || $index['columns'] !== $index2['columns'])
						{
							$smcFunc['db_remove_index']($table_name, $index['name']);
							$smcFunc['db_add_index']($table_name, $index);
						}

						break;
					}
				}

				if (!$exists)
					$smcFunc['db_add_index']($table_name, $index);
			}

			// Remove unnecassary indexes
			foreach ($currentTable['indexes'] as $index)
			{
				$exists = false;

				foreach ($table['indexes'] as $index2)
				{
					// Primary is special case
					if ($index['type'] == 'primary' && $index2['type'] == 'primary')
						$exists = true;
					// Make sure index is correct
					elseif (isset($index['name']) && isset($index2['name']) && $index['name'] == $index2['name'])
						$exists = true;
				}

				if (!$exists)
				{
					if (isset($table['upgrade']['indexes']))
					{
						foreach ($table['upgrade']['indexes'] as $index2)
						{
							if ($index['type'] == 'primary' && $index2['type'] == 'primary' && $index['columns'] === $index2['columns'])
								$smcFunc['db_remove_index']($table_name, 'primary');
							elseif (isset($index['name']) && isset($index2['name']) && $index['name'] == $index2['name'] && $index['type'] == $index2['type'] && $index['columns'] === $index2['columns'])
								$smcFunc['db_remove_index']($table_name, $index['name']);
							else
								$log[] = $table_name . ' has Unneeded index ' . var_dump($index);
						}
					}
					else
						$log[] = $table_name . ' has Unneeded index ' . var_dump($index);
				}
			}
		}
	}

	if (!empty($log))
		log_error(implode('<br />', $log));

	return $log;
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
			'namespace_type' => 'int',
		),
		array(
			array(
				'',
				'',
				'',
				'',
				'Main_Page',
				0,
			),
			array(
				'Template',
				'Templates',
				'',
				'',
				'Index',
				0,
			),
		),
		array('namespace')
	);

	$specialNamespaces = array(
		1 => array('name' => 'Special', 'default_page' => 'List'),
		2 => array('name' => 'File', 'default_page' => 'List'),
		3 => array('name' => 'Image', 'default_page' => 'List'),
	);

	foreach ($specialNamespaces as $type => $data)
	{
		$request = $smcFunc['db_query']('', '
			SELECT namespace, ns_prefix, page_header, page_footer, default_page, namespace_type
			FROM {db_prefix}wiki_namespace
			WHERE namespace_type = {int:type}',
			array(
				'type' => $type,
			)
		);

		$row = $smcFunc['db_fetch_assoc']($request);

		if (!$row)
			$smcFunc['db_insert']('ignore',
				'{db_prefix}wiki_namespace',
				array(
					'namespace' => 'string',
					'ns_prefix' => 'string',
					'page_header' => 'string',
					'page_footer' => 'string',
					'default_page' => 'string',
					'namespace_type' => 'int',
				),
				array(
					array(
						$data['name'],
						'',
						'',
						'',
						$data['default_page'],
						$type,
					),
				),
				array('namespace')
			);

		$smcFunc['db_free_result']($request);
	}

	$defaultPages = array(
		array(
			'namespace' => '',
			'name' => 'Main_Page',
			'body' => 'SMF Wiki {{wikiversion}} installed!',
			'locked' => true,
		),
		array(
			'namespace' => 'Template',
			'name' => 'Navigation',
			'body' => '__navigation__' . "\n" . ':Main_Page|__main_page__',
			'locked' => true,
		),
	);

	foreach ($defaultPages as $page)
	{
		$page['body'] = $smcFunc['htmlspecialchars']($page['body'], ENT_QUOTES);
		preparsecode($page['body']);

		createPage($page['namespace'], $page['name'], $page['body'], $page['locked']);
	}
}

// Function to create page
function createPage($namespace, $name, $body, $locked = true, $exists = 'ignore')
{
	global $smcFunc, $wiki_version, $user_info, $modSettings;

	$comment = 'SMF Wiki default page';

	$request = $smcFunc['db_query']('', '
		SELECT info.id_page, con.content, con.comment
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
		list ($id_page, $content, $comment2) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if ($comment2 != $comment && $exists != 'update')
			return false;

		if ($content == $body)
			return true;
	}
	else
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}wiki_pages',
			array(
				'title' => 'string-255',
				'namespace' => 'string-255',
				'is_locked' => 'int',
			),
			array(
				$name,
				$namespace,
				$locked ? 1 : 0,
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