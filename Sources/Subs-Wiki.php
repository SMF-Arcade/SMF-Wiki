<?php
/**********************************************************************************
* Subs-Wiki.php                                                                   *
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

// Function to make all links
function wiki_get_url($params)
{
	global $scripturl, $modSettings;

	if (is_string($params))
		$params = array('page' => $params);

	// Running in "standalone" mode WITH rewrite
	if (!empty($modSettings['wikiStandalone']) && $modSettings['wikiStandalone'] == 2)
	{
		$page = '';

		if (isset($params['page']))
		{
			$page = $params['page'];
			unset($params['page']);
		}

		if (count($params) === 0)
			return $modSettings['wikiStandaloneUrl'] . '/' . $page;

		$query = '';

		foreach ($params as $p => $value)
		{
			if ($value === null)
				continue;

			if (!empty($query))
				$query .= ';';
			else
				$query .= '?';

			if (is_int($p))
				$query .= $value;
			else
				$query .= $p . '=' . $value;
		}

		return $modSettings['wikiStandaloneUrl'] . '/' . $page . $query;
	}
	//Running in "standalone" mode without rewrite or standard mode
	else
	{
		if (!empty($modSettings['wikiStandalone']))
			$return = '';
		else
			$return = '?action=wiki';

		foreach ($params as $p => $value)
		{
			if ($value === null)
				continue;

			if (!empty($return))
				$return .= ';';
			else
				$return .= '?';

			if (is_int($p))
				$return .= $value;
			else
				$return .= $p . '=' . $value;
		}

		if (!empty($modSettings['wikiStandalone']))
			return $modSettings['wikiStandaloneUrl'] . $return;
		else
			return $scripturl . $return;
	}
}

// Makes Readable name form urlname
function read_urlname($url)
{
	global $smcFunc;

	return $smcFunc['htmlspecialchars']($smcFunc['ucwords'](str_replace(array('_', '%20', '/'), ' ', un_htmlspecialchars($url))));
}

// Gets Namespace and Page from url style (Namespace:Page_Title)
function __url_page_parse($page)
{
	global $context;

	if (strpos($page, ':'))
		list ($namespace, $page) = explode(':', $page, 2);
	else
		$namespace = '';

	if (!empty($namespace) && !isset($context['namespaces'][$namespace]))
	{
		$page = $namespace . ':' . $page;
		$namespace = '';
	}

	return array($namespace, $page);
}

// Makes link from page title and namespace
function wiki_urlname($page, $namespace = null, $clean = true)
{
	global $smcFunc;

	if ($namespace == null)
		list ($namespace, $page) = __url_page_parse($page);

	if ($clean)
	{
		$namespace = clean_pagename($namespace, true);
		$page = clean_pagename($page);
	}

	return !empty($namespace) ? $namespace . ':' . $page : $page;
}

function is_valid_pagename($page, $namespace)
{
	if ($namespace['id'] != '' && empty($page))
		return false;

	return str_replace(array('[', ']', '{', '}', '|'), '', $page) == $page;
}

// Makes string safe to use as id for html element
function make_html_safe($string)
{
	return str_replace(array(' ', '[', ']', '{', '}'), '_', $string);
}

// Cleans illegal characters from pagename
function clean_pagename($string, $namespace = false)
{
	global $smcFunc;

	if ($namespace)
		return str_replace(array(' ', '[', ']', '{', '}', ':', '|'), '_', $string);

	return str_replace(array(' ', '[', ']', '{', '}', '|'), '_', $string);
}

function loadNamespace()
{
	global $smcFunc, $context;

	$context['namespaces'] = cache_quick_get('wiki-namespaces', 'Subs-Wiki.php', 'wiki_get_namespaces', array());

	foreach ($context['namespaces'] as $id => $ns)
	{
		// Hnadle special namespaces
		if ($ns['type'] == 1 && !isset($context['namespace_special']))
			$context['namespace_special'] = &$context['namespaces'][$id];
		elseif ($ns['type'] == 2 && !isset($context['namespace_files']))
			$context['namespace_files'] = &$context['namespaces'][$id];
		elseif ($ns['type'] == 3 && !isset($context['namespace_images']))
			$context['namespace_images'] = &$context['namespaces'][$id];
		elseif ($ns['type'] == 4 && !isset($context['namespace_internal']))
			$context['namespace_internal'] = &$context['namespaces'][$id];
		elseif ($ns['type'] != 0)
			fatal_lang_error('wiki_namespace_broken', false, array(read_urlname($id)));
	}
}

function wiki_get_namespaces()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT namespace, ns_prefix, page_header, page_footer, default_page, namespace_type
		FROM {db_prefix}wiki_namespace',
		array(
		)
	);

	$namespaces = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$namespaces[$row['namespace']] = array(
			'id' => $row['namespace'],
			'prefix' => $row['ns_prefix'],
			'url' => wiki_get_url(wiki_urlname($row['default_page'], $row['namespace'])),
			'type' => $row['namespace_type'],
		);
	$smcFunc['db_free_result']($request);

	return array(
		'data' => $namespaces,
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

function loadWikiPage()
{
	global $smcFunc, $context;

	$context['page_info'] = cache_quick_get('wiki-pageinfo-' .  $context['namespace']['id'] . '-' . $_REQUEST['page'], 'Subs-Wiki.php', 'wiki_get_page_info', array($_REQUEST['page'], $context['namespace']));

	$revision = !empty($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : $context['page_info']['current_revision'];

	$context['page_info'] += array(
		'is_current' => $revision == $context['page_info']['current_revision'],
		'revision' => $revision,
	);

	if ($context['page_info']['id'] === null)
		return;

	// Load content itself
	$context['wiki_page'] = cache_quick_get(
		'wiki-page-' .  $context['page_info']['id'] . '-rev' . $revision,
		'Subs-Wiki.php', 'wiki_get_page_content',
		array($context['page_info'], $context['namespace'], $revision)
	);
	
	unset($page_data);

	$context['page_info']['title'] = $context['wiki_page']->title;

	// Is there file attached to this page?
	if (!empty($context['wiki_page']->id_file))
	{
		$request = $smcFunc['db_query']('', '
			SELECT localname, mime_type, file_ext, filesize, timestamp, img_width, img_height
			FROM {db_prefix}wiki_files
			WHERE id_file = {int:file}',
			array(
				'file' => $context['wiki_page']->id_file,
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
			'time' => timeformat($row['timestamp']),
			'filesize' => $row['filesize'] / 1024,
			'width' => $row['img_width'],
			'height' => $row['img_height'],
			'is_image' => !empty($row['mime_type']),
		);
	}
}

function wiki_get_page_info($page, $namespace)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_page, title, id_revision_current, id_topic, is_locked
		FROM {db_prefix}wiki_pages
		WHERE title = {string:page}
			AND namespace = {string:namespace}',
		array(
			'page' => $page,
			'namespace' => $namespace['id'],
		)
	);

	if (!$row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_free_result']($request);

		return array(
			'data' => array(
				'id' => null,
				'title' => read_urlname($page, true),
				'name' => wiki_urlname($page, $namespace['id']),
				'is_current' => true,
				'is_locked' => false,
				'current_revision' => 0,
			),
			// Minimal cache time for non-existing pages
			'expires' => time() + 60,
			'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
		);
	}
	$smcFunc['db_free_result']($request);

	return array(
		'data' => array(
			'id' => $row['id_page'],
			'title' => read_urlname($row['title']),
			'name' => wiki_urlname($row['title'], $namespace['id']),
			'topic' => $row['id_topic'],
			'is_locked' => !empty($row['is_locked']),
			'current_revision' => $row['id_revision_current'],
		),
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

function wiki_get_page_content($page_info, $namespace, $revision)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT content, id_file
		FROM {db_prefix}wiki_content
		WHERE id_page = {int:page}
			AND id_revision = {raw:revision}',
		array(
			'page' => $page_info['id'],
			'revision' => $revision,
		)
	);

	if (!$row = $smcFunc['db_fetch_assoc']($request))
		fatal_lang_error('wiki_invalid_revision');
	
	$wikiPage = new WikiPage($page_info, $namespace, $row['content']);
	$wikiPage->parse();
	
	if (!empty($row['id_file']))
		$wikiPage->addFile($row['id_file']);
		
	return array(
		'data' => $wikiPage,
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

function loadWikiPage_old($name, $namespace = '', $revision = null)
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	$request = $smcFunc['db_query']('', '
		SELECT info.id_page, info.title, info.namespace, con.content, info.id_revision_current, con.id_revision,
			info.id_topic, info.is_locked, info.id_file
		FROM {db_prefix}wiki_pages AS info
			INNER JOIN {db_prefix}wiki_content AS con ON (con.id_revision = {raw:revision}
				AND con.id_page = info.id_page)
		WHERE info.title = {string:article}
			AND info.namespace = {string:namespace}',
		array(
			'article' => $name,
			'namespace' => $namespace,
			'revision' => !empty($revision) ? $revision : 'info.id_revision_current',
		)
	);

	if (!$row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_free_result']($request);

		return false;
	}
	$smcFunc['db_free_result']($request);

	$title = read_urlname($row['title']);

	/*$variables = wikiparse_variables($row['content']);

	if (!empty($variables['title']))
		$title = $variables['title'];*/

	return array(
		'id' => $row['id_page'],
		'title' => $title,
		'name' => wiki_urlname($row['title'], $row['namespace']),
		'namespace' => $row['namespace'],
		'topic' => $row['id_topic'],
		'is_current' => $row['id_revision'] == $row['id_revision_current'],
		'is_locked' => !empty($row['is_locked']),
		'revision' => $row['id_revision'],
		'current_revision' => $row['id_revision_current'],
		'body' => $row['content'],
		'variables' => $variables,
	);
}

// LoadWikiMenu
function loadWikiMenu()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	$return = array();

	$cacheInfo = wiki_get_page_info('Sidebar', $context['namespace_internal']);
	$page_info = $cacheInfo['data'];
	unset($cacheInfo);

	if ($page_info['id'] === null)
		return array(
			'data' => array(),
			'expires' => time(),
			'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
		);

	$cacheInfo = wiki_get_page_content($page_info, $context['namespace_internal'], $page_info['current_revision']);
	$wikiPage = $cacheInfo['data'];
	unset($cacheInfo);

	$menu = preg_split('~<br( /)?' . '>~', $wikiPage->raw_content);

	$current_menu = false;

	foreach ($menu as $item)
	{
		$item = trim($item);

		if (substr($item, 0, 2) == '**' && substr($item, 2, 1) != '*')
		{
			$subItem = true;
			$item = trim(substr($item, 2));
		}
		elseif (substr($item, 0, 1) == '*' && substr($item, 1, 1) != '*')
		{
			$subItem = false;
			$item = trim(substr($item, 1));
		}
		else
			continue;

		if (strpos($item, '|') !== false)
		{
			list ($page, $title) = explode('|', $item, 2);

			if (substr($page, 4) != 'http')
				$url = wiki_get_url($page);
			else
				$url = $page;
		}
		else
		{
			$url = '';
			$title = $item;
			$page = $item;
		}

		if (substr($title, 0, 2) == '__' || substr($title, -2, 2) == '__')
			$title = isset($txt['wiki_' . substr($title, 2, -2)]) ? $txt['wiki_' . substr($title, 2, -2)] : $title;

		if (!$subItem)
		{
			$return[] = array(
				'page' => $page,
				'url' => $url,
				'title' => $title,
				'items' => array(),
				'selected' => false,
			);

			$current_menu = &$return[count($return) - 1];
		}
		else
		{
			$current_menu['items'][] = array(
				'page' => $page,
				'url' => $url,
				'title' => $title,
				'selected' => false,
			);
		}
	}

	return array(
		'data' => $return,
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

// Creates new page without any revision
function createPage($title, $namespace)
{
	global $smcFunc;

	$smcFunc['db_insert']('insert',
		'{db_prefix}wiki_pages',
		array(
			'title' => 'string-255',
			'namespace' => 'string-255',
		),
		array(
			$title,
			$namespace['id'],
		),
		array('id_page')
	);

	return $smcFunc['db_insert_id']('{db_prefix}wiki_pages', 'id_page');
}

// Creates new revision for page
function createRevision($id_page, $pageOptions, $revisionOptions, $posterOptions)
{
	global $context, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT title, namespace
		FROM {db_prefix}wiki_pages
		WHERE id_page = {int:page}
		LIMIT 1',
		array(
			'page' => $id_page,
		)
	);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (!$row)
		return false;

	$smcFunc['db_insert']('insert',
		'{db_prefix}wiki_content',
		array(
			'id_page' => 'int',
			'id_author' => 'int',
			'id_file' => 'int',
			'timestamp' => 'int',
			'content' => 'string',
			'comment' => 'string-255',
		),
		array(
			$id_page,
			$posterOptions['id'],
			!empty($revisionOptions['file']) ? $revisionOptions['file'] : 0,
			time(),
			$revisionOptions['body'],
			$revisionOptions['comment'],
		),
		array('id_revision')
	);

	$id_revision = $smcFunc['db_insert_id']('{db_prefix}wiki_content', 'id_revision');

	$smcFunc['db_query']('' ,'
		UPDATE {db_prefix}wiki_pages
		SET
			id_revision_current = {int:revision}' . (isset($pageOptions['lock']) ? ',
			is_locked = {int:lock}' : '') . (!empty($revisionOptions['file']) ? ',
			id_file = {int:file}' : '') . '
		WHERE id_page = {int:page}',
		array(
			'page' => $id_page,
			'file' => !empty($revisionOptions['file']) ? $revisionOptions['file'] : 0,
			'lock' => !empty($pageOptions['lock']) ? 1 : 0,
			'revision' => $id_revision,
		)
	);

	// If editing menu, clear cached menu
	if ($row['namespace'] == $context['namespace_internal']['id'] && $row['title'] == 'Sidebar')
		cache_put_data('wiki-navigation', null, 360);

	cache_put_data('wiki-pageinfo-' . $row['namespace'] . '-' . $row['title'], null, 360);

	return true;
}

// Remove an array of revisions. (permissions are NOT checked in this function!)
function removeWikiRevisions($page, $revisions)
{
	global $smcFunc;

	if (empty($revisions))
		return;
	// Only a single revision.
	elseif (!is_array($revisions))
		$revisions = array((int) $revisions);

	// Get newest revision that isn't going to be removed
	$request = $smcFunc['db_query']('', '
		SELECT id_page, id_revision
		FROM {db_prefix}wiki_content
		WHERE id_page = {int:pages}
			AND id_revision NOT IN ({array_int:revisions})
		ORDER BY id_revision DESC
		LIMIT 1',
		array(
			'pages' => $page,
			'revisions' => $revisions,
		)
	);

	if ($smcFunc['db_num_rows']($request) == 0)
		return removeWikiPages($page);

	list ($new_revision) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}wiki_pages
		SET id_revision_current = {int:new_revision}
		WHERE id_page = {int:page}
		LIMIT 1',
		array(
			'page' => $page,
			'new_revision' => $new_revision,
		)
	);

	// Lastly, delete the revisions.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}wiki_content
		WHERE id_revision IN ({array_int:revisions})',
		array(
			'revisions' => $revisions,
		)
	);

	return true;
}

function removeWikiPages($page)
{
	global $smcFunc;

	if (!is_array($page))
		$page = array((int) $page);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}wiki_pages
		WHERE id_page IN({array_int:page})',
		array(
			'page' => $page,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}wiki_content
		WHERE id_page IN({array_int:page})',
		array(
			'page' => $page,
		)
	);

	return true;
}

/*
        Paul's Simple Diff Algorithm v 0.1
        (C) Paul Butler 2007 <http://www.paulbutler.org/>
        May be used and distributed under the zlib/libpng license.

        This code is intended for learning purposes; it was written with short
        code taking priority over performance. It could be used in a practical
        application, but there are a few ways it could be optimized.

        Given two arrays, the function diff will return an array of the changes.
        I won't describe the format of the array, but it will be obvious
        if you use print_r() on the result of a diff on some test data.

        htmlDiff is a wrapper for the diff command, it takes two strings and
        returns the differences in HTML. The tags used are <ins> and <del>,
        which can easily be styled with CSS.
*/

function diff($old, $new)
{
	$maxlen = 0;

	foreach($old as $oindex => $ovalue)
	{
		$nkeys = array_keys($new, $ovalue);
		foreach($nkeys as $nindex)
		{
			$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
				$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
			if ($matrix[$oindex][$nindex] > $maxlen)
			{
				$maxlen = $matrix[$oindex][$nindex];
				$omax = $oindex + 1 - $maxlen;
				$nmax = $nindex + 1 - $maxlen;
			}
		}
	}

	if ($maxlen == 0)
		return array(
			array('d' => $old, 'i'=> $new)
		);

	return array_merge(
		diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
		array_slice($new, $nmax, $maxlen),
		diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen))
	);
}

?>