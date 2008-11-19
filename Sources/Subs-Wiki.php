<?php
/**********************************************************************************
* Subs-Wiki.php                                                                   *
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

// Diff
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

function loadWikiPage($name, $namespace = '', $revision = null)
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	$request = $smcFunc['db_query']('', '
		SELECT info.id_page, info.title, info.namespace, con.content, info.id_revision_current, con.id_revision,
			info.id_topic, info.is_locked
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

	$variables = wikiparse_variables($row['content']);

	if (!empty($variables['title']))
		$title = $variables['title'];

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
		'found' => true,
	);
}

// Makes Readable name form urlname
function read_urlname($url)
{
	global $smcFunc;

	return $smcFunc['htmlspecialchars']($smcFunc['ucwords'](str_replace(array('_', '%20', '/'), ' ', un_htmlspecialchars($url))));
}

// Makes link from page title and namespace
function wiki_urlname($page, $namespace = null)
{
	global $smcFunc;

	if ($namespace == null)
	{
		if (strpos($page, ':'))
			list ($namespace, $page) = explode(':', $page, 2);
		else
			$namespace = '';
	}

	$namespace = clean_pagename($namespace, true);
	$page = clean_pagename($page);

	return !empty($namespace) ? $namespace . ':' . $page : $page;
}

// Makes string safe to use as id for html element
function make_html_safe($string)
{
	return str_replace(array(' ', '[', ']', '{', '}'), '_', $string);
}

function clean_pagename($string, $namespace = false)
{
	global $smcFunc;

	if ($namespace)
		return ucfirst($smcFunc['strtolower'](str_replace(array(' ', '[', ']', '{', '}', ':', '|'), '_', $string)));

	return str_replace(array(' ', '[', ']', '{', '}', '|'), '_', $string);
}

// Makes table of contents
function do_toctable($tlevel, $toc, $main = true)
{
	$stack = array(
		'',
		array(),
	);
	$num = 0;
	$mainToc = array();

	foreach ($toc as $t)
	{
		list ($level, $title) = $t;

		if ($level == $tlevel)
		{
			if (!empty($stack[0]))
			{
				$mainToc[] = array(
					$num,
					$stack[0],
					!empty($stack[1]) ? do_toctable($tlevel + 1, $stack[1], false) : array(),
				);
			}

			$stack = array(
				$title,
				array()
			);

			$num++;
		}
		elseif ($level >= $tlevel)
			$stack[1][] = array($level, $title);
	}

	if (!empty($stack[0]))
	{
		$mainToc[] = array(
			$num,
			$stack[0],
			!empty($stack[1]) ? do_toctable($tlevel+1, $stack[1], false) : array(),
		);
	}

	return $mainToc;
}

function wikiparse_variables($message)
{
	global $rep_temp, $pageVariables;

	$pageVariables = array();

	$message = preg_replace_callback('%{{([a-zA-Z]+):(.+?)}}%', 'wikivariable_callback', $message);

	$temp = $pageVariables;
	unset($pageVariables);

	return $temp;
}

// Parses wiki page
function wikiparser($page_title, $message, $parse_bbc = true, $namespace = null)
{
	global $rep_temp;

	$wikiPageObject = array(
		'toc' => array(),
		'sections' => array(),
	);


	$message = preg_replace_callback('%{{([a-zA-Z]+(:(.+?))?)}}(<br( />)?)?%', 'wikivariable_callback', $message);
	$message = preg_replace_callback('%{(.+?)\s?([^}]+?)?}(.+?){/\1}%', 'wikitemplate_callback', $message);

	if ($parse_bbc)
		$message = parse_bbc($message);

	$message = preg_replace_callback('/\[\[Image:(.*?)(\|(.*?))?\]\]/', 'wiki_image_callback', $message);
	$message = preg_replace_callback('/\[\[(.*?)(\|(.*?))?\]\](.*?)([.,\'"\s]|$|\r\n|\n|\r|<br( \/)?>|<)/', 'wikilink_callback', $message);
	$parts = preg_split('%(={2,5})\s{0,}(.+?)\s{0,}\1\s{0,}<br />|(<br /><br />)|(<!!!>)|(</!!!>)|(<div|<ul|<table|<code)|(</div>|</ul>|</table>|</code>)%', $message, null,  PREG_SPLIT_DELIM_CAPTURE);

	$i = 0;

	$toc = array();
	$curSection = array(
		'title' => $page_title,
		'level' => 1,
		'content' => '',
		'edit_url' => wiki_get_url(array(
			'page' => wiki_urlname($page_title, $namespace),
			'sa' => 'edit',
		)),
	);

	$para_open = false;
	// Can paragraph be opened?
	$can_para = true;

	//(print_r($parts));

	while ($i < count($parts))
	{
		if (substr($parts[$i], 0, 1) == '=' && strlen($parts[$i]) >= 2 && strlen($parts[$i]) <= 5)
		{
			if (str_replace('=', '', $parts[$i]) == '')
			{
				if ($para_open)
					$curSection['content'] .= '</p>';
				$wikiPageObject['sections'][] = $curSection;

				$toc[] = array(strlen($parts[$i]), $parts[$i + 1]);

				$curSection = array(
					'title' => $parts[$i + 1],
					'level' => strlen($parts[$i]),
					'content' => '',
					'edit_url' => wiki_get_url(array(
						'page' => wiki_urlname($page_title, $namespace),
						'sa' => 'edit',
						'section' => count($object['sections']),
					)),
				);

				$para_open = false;

				$i += 1;
			}
		}
		// New Paragraph?
		elseif ($parts[$i] == '<br /><br />')
		{
			if ($para_open)
				$curSection['content'] .= '</p>';
			$para_open = false;
		}
		// Block tags can't be in paragraph
		elseif (in_array($parts[$i], array('<div', '<ul', '<table', '<code')))
		{
			if ($para_open)
				$curSection['content'] .= '</p>';
			$para_open = false;

			// Don't start new paragraph
			$can_para = false;

			$curSection['content'] .= $parts[$i];
		}
		elseif (in_array($parts[$i], array('</div>', '</ul>', '</table>', '</code')))
		{
			// Now new paragraph can be started again
			$can_para = true;

			$curSection['content'] .= $parts[$i];
		}
		elseif ($parts[$i] == '<!!!>')
		{
			if ($para_open)
				$curSection['content'] .= '</p>';
			$para_open = false;

			// Don't start new paragraph
			$can_para = false;
		}
		elseif ($parts[$i] == '</!!!>')
		{
			// Now new paragraph can be started again
			$can_para = true;
		}
		elseif (!empty($parts[$i]))
		{
			// Open new paragraph if one isn't open
			if (!$para_open && $can_para)
			{
				$curSection['content'] .= '<p>';
				$para_open = true;
			}

			$curSection['content'] .= $parts[$i];
		}

		$i++;
	}

	$i = 0;

	$tempToc = array();

	$trees = array();

	$wikiPageObject['toc'] = do_toctable(2, $toc);

	// Close last paragraph
	if ($para_open)
		$curSection['content'] .= '</p>';

	$wikiPageObject['sections'][] = $curSection;

	return $wikiPageObject;
}

// Callback for wikivariables
function wikivariable_callback($groups)
{
	global $context, $pageVariables;

	if (empty($groups[2]))
	{
		if (isset($pageVariables[$groups[1]]))
			return $pageVariables[$groups[1]];
		elseif (isset($context['wiki_variables'][$groups[1]]))
			return $context['wiki_variables'][$groups[1]];
	}
	else
	{
		if (isset($pageVariables))
			$pageVariables[$groups[1]] = $groups[2];
		return '';
	}

	return $groups[0];
}

// Callback for images
function wiki_image_callback($groups)
{
	if (!empty($groups[3]))
	{
		$options = explode('|', $groups[3]);
		$align = '';
		$size = '';
		$caption = '';
		$alt = '';

		// Size
		if (!empty($options[0]))
		{
			if ($options[0] == 'thumb')
				$size = ' width="180"';
			elseif (is_numeric($options[0]))
				$size = ' width="' . $options[0] . '"';
			elseif (strpos($options[0], 'x') !== false)
			{
				list ($width, $height) = explode('x', $options[0], 2);

				if (is_numeric($width) && is_numeric($height))
				{
					$size = ' width="' . $width . '" height="' . $height. '"';
				}
			}
		}

		// Align
		if (!empty($options[1]) && ($options[1] == 'left' || $options[1] == 'right'))
			$align = $options[1];

		// Alt
		if (!empty($options[2]))
			$alt = $options[2];

		// Caption
		if (!empty($options[3]))
			$caption = $options[3];

		if (!empty($align) || !empty($caption))
			$code = '<div' . (!empty($align) ? $code .= ' style="float: ' . $align . '; clear: ' . $align . '"' : '') . '>';

		$code .= '<a href="' . wiki_get_url(wiki_urlname($groups[1], 'Image')) . '"><img src="' . wiki_get_url(array('page' => wiki_urlname($groups[1], 'Image'), 'image')) . '" alt="' . $alt . '"' . $size . ' /></a>';

		if (!empty($align) || !empty($caption))
			$code .= '</div>';

		return $code;
	}

	return '<a href="' . wiki_get_url(wiki_urlname($groups[1], 'Image')) . '"><img src="' . wiki_get_url(array('page' => wiki_urlname($groups[1], 'Image'), 'image')) . '" alt="" /></a>';
}

// Callback for making wikilinks
function wikilink_callback($groups)
{
	if (empty($groups[3]))
		$link = '<a href="' . wiki_get_url(wiki_urlname($groups[1])) . '">' . read_urlname($groups[1]) . $groups[4] . '</a>';
	else
		$link = '<a href="' . wiki_get_url(wiki_urlname($groups[1])) . '">' . $groups[3] . $groups[4] . '</a>';

	return $link . $groups[5];
}

// Callback for templates
function wikitemplate_callback($groups)
{
	global $context, $wikiReplaces;
	static $templateFunctions = array();

	$page = $groups[1];

	if (strpos($page, ':') !== false)
		list ($namespace, $page) = explode(':', $page);
	else
		$namespace = 'Template';

	if (!isset($context['wiki_template']))
		$context['wiki_template'] = array();

	if (!isset($context['wiki_template'][$namespace . ':' . $page]))
		$context['wiki_template'][$namespace . ':' . $page] = cache_quick_get('wiki-template-' . $namespace . ':' . $page, 'Subs-Wiki.php', 'wiki_template_get', array($namespace, $page));

	if ($context['wiki_template'][$namespace . ':' . $page] === false)
		return '<span style="color: red">' . sprintf($txt['template_not_found'], (!empty($namespace) ? $namespace . ':' . $page : $page)). '</span>';

	$wikiReplaces = array(
		'@@content@@' => $groups[3]
	);

	preg_match_all('/([^\s]+?)=(&quot;|")(.+?)(&quot;|")/s', $groups[2], $result, PREG_SET_ORDER);

	foreach ($result as $res)
		$wikiReplaces['@@' . trim($res[1]) . '@@'] = trim($res[3]);

	return strtr(
		preg_replace_callback('/\{IF(\s+?)?@@(.+?)@@(\s+?)?\{(.+?)\}\}/s', 'wikitemplate_if_callback', $context['wiki_template'][$namespace . ':' . $page]),
		$wikiReplaces
	);
}

// Callback for condtional IF
function wikitemplate_if_callback($groups)
{
	global $context, $wikiReplaces;

	if (!empty($wikiReplaces['@@' . $groups[2] . '@@']))
		return $groups[4];
	else
		return '';
}

// Gets template
function wiki_template_get($namespace, $page, $revision = 0)
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	$request = $smcFunc['db_query']('', '
		SELECT con.content
		FROM {db_prefix}wiki_pages AS info
			INNER JOIN {db_prefix}wiki_content AS con ON (con.id_revision = {raw:revision}
				AND con.id_page = info.id_page)
		WHERE info.title = {string:article}
			AND info.namespace = {string:namespace}',
		array(
			'article' => $page,
			'namespace' => $namespace,
			'revision' => !empty($revision) ? $revision : 'info.id_revision_current',
		)
	);

	if (!$row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_free_result']($request);

		return array(
			'expires' => time() + 360,
			'data' => false,
			'refresh_eval' => 'return isset($_REQUEST[\'purge\']);',
		);
	}
	$smcFunc['db_free_result']($request);

	return array(
		'data' => $row['content'],
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
	);
}

// LoadWikiMenu
function loadWikiMenu()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	$return = array();

	$template = wiki_template_get('Template', 'Navigation');

	$menu = preg_split('~<br( /)?' . '>~', $template['data']);

	$current_menu = false;

	foreach ($menu as $item)
	{
		$item = trim($item);
		$subItem = false;

		$subItem = substr($item, 0, 1) == ':';

		if ($subItem)
			$item = substr($item, 1);

		if (strpos($item, '|') !== false)
		{
			list ($page, $title) = explode('|', $item, 2);

			if (substr($url, 4) != 'http')
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
?>
