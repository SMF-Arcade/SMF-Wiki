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

			if (!empty($value))
				$query .= $p . '=' . $value;
			else
				$query .= $p;
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

			if (!empty($value))
				$return .= $p . '=' . $value;
			else
				$return .= $p;
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
			info.id_topic
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

	return array(
		'id' => $row['id_page'],
		'title' => read_urlname($row['title']),
		'name' => wiki_urlname($row['title'], $row['namespace']),
		'namespace' => $row['namespace'],
		'topic' => $row['id_topic'],
		'is_current' => $row['id_revision'] == $row['id_revision_current'],
		'revision' => $row['id_revision'],
		'current_revision' => $row['id_revision_current'],
		'body' => $row['content'],
	);
}

// Makes Readable name form urlname
function read_urlname($url)
{
	global $smcFunc;

	return $smcFunc['ucwords'](str_replace(array('_', '%20', '/'), ' ', $url));
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

	$namespace = ucfirst($smcFunc['strtolower'](str_replace(array(' ', '[', ']', '{', '}'), '_', $namespace)));
	$page = str_replace(array(' ', '[', ']', '{', '}'), '_', $page);

	return !empty($namespace) ? $namespace . ':' . $page : $page;
}

// Makes string safe to use as id for html element
function make_html_safe($string)
{
	return str_replace(array(' ', '[', ']', '{', '}'), '_', $string);
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

// Parses wiki page
function wikiparser($page_title, $message, $parse_bbc = true, $namespace = null)
{
	global $rep_temp;

	$object = array(
		'toc' => array(),
		'sections' => array(),
	);

	$curSection = array(
		'title' => $page_title,
		'level' => 1,
		'content' => '',
		'edit_url' => wiki_get_url(array(
			'page' => wiki_urlname($page_title, $namespace),
			'sa' => 'edit',
		)),
	);


	if ($parse_bbc)
	{
		$message = preg_replace_callback('%{(.+?)\s?([^}]+?)?}(.+?){/\1}%s', 'wikitemplate_callback', $message);
		$message = parse_bbc($message);
		$message = preg_replace_callback('/\[\[(.*?)(\|(.*?))?\]\](.*?)([.,\'"\s]|$|\r\n|\n|\r|<br \/>|<)/', 'wikilink_callback', $message);
	}
	$parts = preg_split('%(={2,5})\s{0,}(.+?)\s{0,}\1\s{0,}(<br />)?%', $message, null,  PREG_SPLIT_DELIM_CAPTURE);

	$i = 0;

	$toc = array();

	while ($i < count($parts))
	{
		if (substr($parts[$i], 0, 1) == '=' && strlen($parts[$i]) >= 2 && strlen($parts[$i]) <= 5)
		{
			if (str_replace('=', '', $parts[$i]) == '')
			{
				$toc[] = array(strlen($parts[$i]), $parts[$i + 1]);
				$object['sections'][] = $curSection;

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

				$i += 3;
			}
		}

		if (!isset($parts[$i]))
			break;

		$curSection['content'] .= $parts[$i];
		$i++;
	}

	$i = 0;

	$tempToc = array();

	$trees = array();

	$object['toc'] = do_toctable(2, $toc);

	$object['sections'][] = $curSection;

	return $object;
}

// Callback for making wikilinks
function wikilink_callback($groups)
{
	global $rep_temp;

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
		$context['wiki_template'][$namespace . ':' . $page] = cache_quick_get('wiki-template-' . $namespace . ':' . $page, 'Subs.php', 'wiki_template_get', array($namespace, $page));

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
			'data' => '<span style="color: red">' . (!empty($namespace) ? $namespace . ':' . $page : $page) . ' not found!</span>',
			'refresh_eval' => 'return isset($_REQUEST[\'purge\']);',
		);
	}
	$smcFunc['db_free_result']($request);

	return array(
		'data' => $row['content'],
		'expires' => time() + 3600,
		'refresh_eval' => 'return isset($_REQUEST[\'purge\']);',
	);
}

?>