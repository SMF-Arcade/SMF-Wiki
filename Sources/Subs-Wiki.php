<?php
/**
 * Common functions for SMF Wiki
 *
 * @package core
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Creates url based on parameters
 * @param mixed url name of page or array of paramers
 */
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

/**
 * Gets tree of parent pages for a page
 * @return array Array of parents.
 */
function get_page_parents($page, $namespace)
{
	if (strpos($page, '/') === false)
		return array();
		
	$parts = explode('/', $page);
	$new_title = array_pop($parts);
	
	$link_info = cache_quick_get('wiki-pageinfo-' . wiki_cache_escape($namespace['id'], implode('/', $parts)), 'Subs-Wiki.php', 'wiki_get_page_info', array(implode('/', $parts), $namespace));
		
	if (!empty($link_info->page_tree))
		$page_tree = $link_info->page_tree;
	if ($link_info !== false)
		$page_tree[] = array(
			'title' => $link_info->title,
			'name' => $link_info->url_name,
		);
	else
		$page_tree[] = array(
			'title' => get_default_display_title(implode('/', $parts), $namespace['id']),
			'name' => implode('/', $parts),
		);
		
	return $page_tree;
}

/**
 * Makes url name from page name and namespace
 * @return string url name for requested page
 */
function wiki_get_url_name($page, $namespace = null, $clean = true)
{
	global $smcFunc;
	
	$page = un_htmlspecialchars($page);

	if ($namespace == null)
		list ($namespace, $page) = wiki_parse_url_name($page);

	if ($clean)
	{
		$namespace = clean_pagename($namespace, true);
		$page = clean_pagename($page);
	}

	return !empty($namespace) ? $namespace . ':' . $page : $page;
}

/**
 * Parses url name into namespace and page name
 * @return array array with namespace and page name
 */
function wiki_parse_url_name($page, $clean = false)
{
	global $context;
	
	if ($page[0] == ':')
		$page = substr($page, 1);

	if (strpos($page, ':') !== false)
		list ($namespace, $page) = explode(':', $page, 2);
	else
		$namespace = '';

	if (!empty($namespace) && !isset($context['namespaces'][$namespace]))
	{
		$page = $namespace . ':' . $page;
		$namespace = '';
	}
	
	if ($clean)
	{
		$namespace = clean_pagename($namespace, true);
		$page = clean_pagename($page);
	}

	return array($namespace, $page);
}

/**
 * Returns default display title for page
 */
function get_default_display_title($page, $namespace = '', $html = true)
{
	global $smcFunc;
	
	if ($namespace === true || $namespace === false)
		list ($namespace, $page) = wiki_parse_url_name($page);
		
	// Sub-page?
	if (strpos($page, '/') !== false)
	{
		$parts = explode('/', $page);
		$new_title = array_pop($parts);
		
		if ($html)
			return $smcFunc['htmlspecialchars']($smcFunc['ucwords'](str_replace(array('_', '%20'), ' ', un_htmlspecialchars($new_title))));
		else
			return $smcFunc['ucwords'](str_replace(array('_', '%20'), ' ', un_htmlspecialchars($new_title)));			
	}
	
	if ($html)
		return (!empty($namespace) ? $smcFunc['htmlspecialchars']($smcFunc['ucwords'](str_replace(array('_', '%20'), ' ', un_htmlspecialchars($namespace)))) . ':' : '') . $smcFunc['htmlspecialchars']($smcFunc['ucwords'](str_replace(array('_', '%20'), ' ', un_htmlspecialchars($page))));
	else
		return (!empty($namespace) ? $smcFunc['ucwords'](str_replace(array('_', '%20'), ' ', un_htmlspecialchars($namespace))) . ':' : '') . $smcFunc['ucwords'](str_replace(array('_', '%20'), ' ', un_htmlspecialchars($page)));	
}


/**
 * Checks if pagename is valid
 */
function is_valid_pagename($page, $namespace)
{
	if ($namespace['id'] != '' && empty($page))
		return false;

	return str_replace(array('[', ']', '{', '}', '|'), '', $page) == $page;
}

/**
 * Cleans illegal characters from pagename
 */
function clean_pagename($string, $namespace = false)
{
	global $smcFunc;

	if ($namespace)
		return str_replace(array(' ', '[', ']', '{', '}', ':', '|'), '_', $string);

	return str_replace(array(' ', '[', ']', '{', '}', '|'), '_', $string);
}

/**
 * Makes namespace pagename + page name safe for use in cachefile name
 */
function wiki_cache_escape($namespace, $page)
{
	return sha1($namespace) . '-' . sha1($page);
}

/**
 * Loads namespaces
 */
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
		elseif ($ns['type'] == 5 && !isset($context['namespace_category']))
			$context['namespace_category'] = &$context['namespaces'][$id];
		elseif ($ns['type'] != 0)
			fatal_lang_error('wiki_namespace_broken', false, array(get_default_display_title($id)));
	}
	
	if (!isset($context['namespace_special']) || !isset($context['namespace_files']) || !isset($context['namespace_images']) || !isset($context['namespace_internal']) || !isset($context['namespace_category']))
		fatal_lang_error('wiki_namespace_broken', false, '(n/a)');
}

/**
 * Helper function for namespaces used via cache_quick_get
 */
function wiki_get_namespaces()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT namespace, ns_prefix, default_page, namespace_type
		FROM {wiki_prefix}namespace',
		array(
		)
	);

	$namespaces = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$namespaces[$row['namespace']] = array(
			'id' => $row['namespace'],
			'prefix' => $row['ns_prefix'],
			'url' => wiki_get_url(wiki_get_url_name($row['default_page'], $row['namespace'])),
			'type' => $row['namespace_type'],
		);
	$smcFunc['db_free_result']($request);

	return array(
		'data' => $namespaces,
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

/**
 * Loads wikipage
 */
function loadWikiPage()
{
	global $smcFunc, $context;

	$context['page_info'] = cache_quick_get('wiki-pageinfo-' .  wiki_cache_escape($context['namespace']['id'], $_REQUEST['page']), 'Subs-Wiki.php', 'wiki_get_page_info', array($_REQUEST['page'], $context['namespace']));

	$revision = !empty($_REQUEST['revision']) ? (int) $_REQUEST['revision'] : $context['page_info']->current_revision;

	$context['page_info']->revision = $revision;
	$context['page_info']->is_current = $context['page_info']->revision == $context['page_info']->current_revision;

	// Load Pages in this category 
	if ($context['namespace'] == $context['namespace_category'])
	{
		list (, $category) = wiki_parse_url_name($context['page_info']->page);
		
		$context['category_name'] = $category;
		
		$request = $smcFunc['db_query']('', '
			SELECT page.id_page, page.display_title, page.title, page.namespace
			FROM {wiki_prefix}category AS cat
				INNER JOIN {wiki_prefix}pages AS page ON (cat.id_page = page.id_page)
			WHERE cat.category = {string:category}
			ORDER BY page.title',
			array(
				'category' => $category,
			)
		);
		
		$context['category_members'] = array();
		
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$context['category_members'][] = array(
				'page' => wiki_get_url_name($row['title'], $row['namespace']),
				'title' => !empty($row['display_title']) ? $row['display_title'] : get_default_display_title($row['title'], $row['namespace']),
			);
		}
		$smcFunc['db_free_result']($request);
	}
	
	if (!$context['page_info']->exists)
		return;

	// Load content itself
	$context['wiki_page'] = cache_quick_get(
		'wiki-page-' .  $context['page_info']->id . '-rev' . $revision,
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
			FROM {wiki_prefix}files
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

/**
 * Returns special page info
 */
function wiki_get_special_page_info($page)
{
	global $smcFunc, $context;
	
	if (strpos($page, '/'))
		list ($page, $sub_page) = explode('/', $page, 2);
	else
		$sub_page = '';
	
	if (!isset($context['wiki_special_pages'][$page]))
		return array(
			'data' => array(
				'id' => null,
				'display_title' => get_default_display_title($page, $context['namespace_special']),
				'title' => get_default_display_title($page, $context['namespace_special']),
				'name' => wiki_get_url_name($page, $context['namespace_special']),
				'is_current' => true,
				'is_locked' => false,
				'current_revision' => 0,
			),
			// Minimal cache time for non-existing pages
			'expires' => time() + 60,
			'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
		);
		
	return array(
		'data' => array(
			'id' => $page,
			'display_title' => get_default_display_title($page, $context['namespace_special']),
			'title' => get_default_display_title($page, $context['namespace_special']),
			'name' => wiki_get_url_name($page, $context['namespace_special']),
			'is_current' => true,
			'is_locked' => false,
			'current_revision' => 0,
		),
		// Minimal cache time for non-existing pages
		'expires' => time() + 60,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

/**
 * Returns page info
 */
function wiki_get_page_info($page, $namespace)
{
	global $smcFunc, $context;

	return array(
		'data' => $namespace == $context['namespace_special'] ? WikiPage::getSpecialPageInfo($page) : WikiPage::getPageInfo($namespace, $page),
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

/**
 * Returns raw (unparsed) page content.
 * Used to get templates from WikiParser
 */
function wiki_get_page_raw_content(WikiPage $page_info, $revision)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT content
		FROM {wiki_prefix}content
		WHERE id_page = {int:page}
			AND id_revision = {raw:revision}',
		array(
			'page' => $page_info->id,
			'revision' => $revision,
		)
	);

	if (!$row = $smcFunc['db_fetch_assoc']($request))
		fatal_lang_error('wiki_invalid_revision');
	$smcFunc['db_free_result']($request);
	
	return $row['content'];
}

/**
 * Returns page content
 * @todo $namespace, $include removal?
 */
function wiki_get_page_content(WikiPage $page_info, $namespace, $revision, $include = false)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT content, id_file
		FROM {wiki_prefix}content
		WHERE id_page = {int:page}
			AND id_revision = {raw:revision}',
		array(
			'page' => $page_info->id,
			'revision' => $revision,
		)
	);

	if (!$row = $smcFunc['db_fetch_assoc']($request))
		fatal_lang_error('wiki_invalid_revision');
	$smcFunc['db_free_result']($request);
	
	$wiki_parser = new WikiParser($page_info);
	$wiki_parser->parse($row['content'], $include);
	
	$page_info->raw_content = $row['content'];
	$page_info->parser = $wiki_parser;
	//
	
	if (!empty($row['id_file']))
		$page_info->addFile($row['id_file']);
		
	return array(
		'data' => $page_info,
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

/**
 * Setups wiki navigation menu
 */
function loadWikiMenu()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	$return = array();

	$cacheInfo = wiki_get_page_info('Sidebar', $context['namespace_internal']);
	$page_info = $cacheInfo['data'];
	unset($cacheInfo);

	if (!$page_info->exists)
		return array(
			'data' => array(),
			'expires' => time(),
			'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
		);

	$cacheInfo = wiki_get_page_content($page_info, $context['namespace_internal'], $page_info->current_revision);
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

/**
 * Creates new page without any revision
 */
function createPage($page, $namespace)
{
	global $smcFunc;

	$smcFunc['db_insert']('insert',
		'{wiki_prefix}pages',
		array(
			'title' => 'string-255',
			'display_title' => 'string-255',
			'namespace' => 'string-255',
		),
		array(
			$page,
			get_default_display_title($page, $namespace['id']),
			$namespace['id'],
		),
		array('id_page')
	);

	return $smcFunc['db_insert_id']('{wiki_prefix}pages', 'id_page');
}

/**
 * Creates new revision for page
 */
function createRevision($id_page, $pageOptions, $revisionOptions, $posterOptions)
{
	global $context, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT title, namespace
		FROM {wiki_prefix}pages
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
		'{wiki_prefix}content',
		array(
			'id_page' => 'int',
			'id_author' => 'int',
			'id_file' => 'int',
			'timestamp' => 'int',
			'display_title' => 'string',
			'content' => 'string',
			'comment' => 'string-255',
		),
		array(
			$id_page,
			$posterOptions['id'],
			!empty($revisionOptions['file']) ? $revisionOptions['file'] : 0,
			time(),
			!empty($pageOptions['display_title']) ? $pageOptions['display_title'] : get_default_display_title($row['title']),
			$revisionOptions['body'],
			$revisionOptions['comment'],
		),
		array('id_revision')
	);

	$id_revision = $smcFunc['db_insert_id']('{wiki_prefix}content', 'id_revision');

	$smcFunc['db_query']('' ,'
		UPDATE {wiki_prefix}pages
		SET
			id_revision_current = {int:revision},
			display_title = {string:display_title}' . (isset($pageOptions['lock']) ? ',
			is_locked = {int:lock}' : '') . (!empty($revisionOptions['file']) ? ',
			id_file = {int:file}' : '') . '
		WHERE id_page = {int:page}',
		array(
			'page' => $id_page,
			'display_title' => !empty($pageOptions['display_title']) ? $pageOptions['display_title'] : get_default_display_title($row['title']),
			'file' => !empty($revisionOptions['file']) ? $revisionOptions['file'] : 0,
			'lock' => !empty($pageOptions['lock']) ? 1 : 0,
			'revision' => $id_revision,
		)
	);
	
	// If editing menu, clear cached menu
	if ($row['namespace'] == $context['namespace_internal']['id'] && $row['title'] == 'Sidebar')
		cache_put_data('wiki-navigation', null, 360);

	cache_put_data('wiki-pageinfo-' . wiki_cache_escape($row['namespace'], $row['title']), null, 3600);

	return true;
}

/**
 * Remove an array of revisions. (permissions are NOT checked in this function!)
 */
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
		FROM {wiki_prefix}content
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
		UPDATE {wiki_prefix}pages
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
		DELETE FROM {wiki_prefix}content
		WHERE id_revision IN ({array_int:revisions})',
		array(
			'revisions' => $revisions,
		)
	);

	return true;
}

/**
 * Restores softdelete page(s)
 */
function restoreWikiPage($page)
{
	global $smcFunc;

	if (!is_array($page))
		$page = array((int) $page);
		
	$pages = array();

	$request = $smcFunc['db_query']('', '
		SELECT title, namespace
		FROM {wiki_prefix}pages
		WHERE id_page IN({array_int:page})',
		array(
			'page' => $page,
		)
	);
	while ($row = $smcFunc['db_fetch_row']($request))
		$pages[] = $row;
	$smcFunc['db_free_result']($request);
	
	$smcFunc['db_query']('', '
		UPDATE {wiki_prefix}pages
		SET is_deleted = 0
		WHERE id_page IN({array_int:page})',
		array(
			'page' => $page,
		)
	);
		
	foreach ($pages as $page)
		cache_put_data('wiki-pageinfo-' . wiki_cache_escape($page['namespace'], $page['title']), null, 3600);
	
	return true;	
}

/**
 * Deletes page(s)
 */
function deleteWikiPage($page, $soft_delete = true)
{
	global $smcFunc;

	if (!is_array($page))
		$page = array((int) $page);

	$pages = array();

	$request = $smcFunc['db_query']('', '
		SELECT title, namespace
		FROM {wiki_prefix}pages
		WHERE id_page IN({array_int:page})',
		array(
			'page' => $page,
		)
	);
	while ($row = $smcFunc['db_fetch_row']($request))
		$pages[] = $row;
	$smcFunc['db_free_result']($request);
	
	if (!$soft_delete)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {wiki_prefix}pages
			WHERE id_page IN({array_int:page})',
			array(
				'page' => $page,
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {wiki_prefix}content
			WHERE id_page IN({array_int:page})',
			array(
				'page' => $page,
			)
		);
	}
	else
	{
		$smcFunc['db_query']('', '
			UPDATE {wiki_prefix}pages
			SET is_deleted = 1
			WHERE id_page IN({array_int:page})',
			array(
				'page' => $page,
			)
		);		
	}

	foreach ($pages as $page)
		cache_put_data('wiki-pageinfo-' . wiki_cache_escape($page['namespace'], $page['title']), null, 3600);
	
	return true;
}

?>