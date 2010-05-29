<?php
/**********************************************************************************
* Database.php                                                                    *
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

global $boarddir, $boardurl, $smcFunc, $addSettings, $permissions, $tables;

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
				'name' => 'display_title',
				'type' => 'varchar',
				'size' => '255',
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
				'name' => 'display_title',
				'type' => 'varchar',
				'size' => '255',
				'default' => '',
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
	// Wiki Category
	'wiki_category' => array(
		'name' => 'wiki_category',
		'columns' => array(
			array(
				'name' => 'id_page',
				'type' => 'int',
				'unsigned' => true,
			),
			array(
				'name' => 'category',
				'type' => 'varchar',
				'size' => 255,
			),		
		),
		'indexes' => array(
			array(
				'type' => 'primary',
				'columns' => array('id_page', 'category'),
			),
			array(
				'name' => 'id_page',
				'type' => 'index',
				'columns' => array('id_page')
			),
			array(
				'name' => 'category',
				'type' => 'index',
				'columns' => array('category')
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
				'name' => 'id_page_is_current',
				'type' => 'index',
				'columns' => array('id_page', 'is_current')
			),
		)
	),
);

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

	$id_revision = $smcFunc['db_insert_id']('{db_prefix}wiki_content', 'id_revision');

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