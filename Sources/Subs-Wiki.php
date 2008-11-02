<?php

function wiki_get_url($params)
{
	global $scripturl;

	$return = '';

	foreach ($params as $p => $value)
	{
		if (!empty($return))
			$return .= ';';
		else
			$return .= '?';

		$return .= $p . '=' . $value;
	}

	return $scripturl . $return;
}

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

function loadWikiPage($name, $namespace, $revision = null)
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

	$context['page_url'] = wiki_get_url(array(
		'page' => (!empty($namespace) ? $namespace . ':' : '') . $name,
	));

	if (!$row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_free_result']($request);

		return false;
	}
	$smcFunc['db_free_result']($request);

	return array(
		'id' => $row['id_page'],
		'topic' => $row['id_topic'],
		'title' => read_urlname($row['title']),
		'namespace' => $row['namespace'],
		'url' => $row['title'],
		'name' => $row['title'],
		'is_current' => $row['id_revision'] == $row['id_revision_current'],
		'revision' => $row['id_revision'],
		'current_revision' => $row['id_revision_current'],
		'body' => $row['content'],
	);
}

function read_urlname($url, $last = false)
{
	global $smcFunc;

	return $smcFunc['ucwords'](str_replace(array('_', '%20', '/'), ' ', $url));
}
function sanitise_urlname($page, $namespace = null)
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

	$page = $smcFunc['ucfirst'](str_replace(array(' ', '[', ']', '{', '}'), '_', $page));

	return !empty($namespace) ? $namespace . ':' . $page : $page;
}

function wikiurlname($url)
{
	return sanitise_urlname($url);
}

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

function wikiparser($page_title, $message, $parse_bbc = true, $namespace = null)
{
	global $rep_temp, $wikiurl;

	$object = array(
		'toc' => array(),
		'sections' => array(),
	);

	$curSection = array(
		'title' => $page_title,
		'level' => 1,
		'content' => '',
		'edit_url' => $wikiurl . '/' . sanitise_urlname($page_title, $namespace) . '?action=edit',
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
					'edit_url' => $wikiurl . '/' . sanitise_urlname($page_title, $namespace) . '?action=edit;section=' . count($object['sections']),
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

function wikilink_callback($groups)
{
	global $wikiurl, $rep_temp;

	if (empty($groups[3]))
		$link = '<a href="' . $wikiurl . '/' . sanitise_urlname($groups[1]) . '">' . read_urlname($groups[1]) . $groups[4] . '</a>';
	else
		$link = '<a href="' . $wikiurl . '/' . sanitise_urlname($groups[1]) . '">' . $groups[3] . $groups[4] . '</a>';

	return $link . $groups[5];
}

function wikitemplate_callback($groups)
{
	global $context, $wikiurl, $wikiReplaces;
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

function wikitemplate_if_callback($groups)
{
	global $context, $wikiurl, $wikiReplaces;

	if (!empty($wikiReplaces['@@' . $groups[2] . '@@']))
		return $groups[4];
	else
		return '';
}

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

function show_not_found_error($title = '')
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	header('HTTP/1.0 404 Not Found');
	$context['robot_no_index'] = true;

	$context['current_page'] = array(
		'title' => !empty($title) ? $tite : $context['title'],
	);

	$context['wiki_title'] = !empty($title) ? $tite : $context['title'];

	// Template
	loadTemplate('WikiPage');
	$context['sub_template'] = 'not_found';
}

?>