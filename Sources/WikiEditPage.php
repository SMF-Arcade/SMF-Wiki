<?php
/**********************************************************************************
* WikiEditPage.php                                                                *
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

function EditPage()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Editor.php');
	require_once($sourcedir . '/Subs-Post.php');

	allowedTo('wiki_edit');
	$context['edit_section'] = 0;

	if (!isset($context['current_page']['body']))
		$context['current_page']['body'] = '';

	if (empty($_REQUEST['section']))
		$body = $context['current_page']['body'];
	else
	{
		$b = wikiparser($context['current_page']['title'], $context['current_page']['body'], false);

		if (!isset($b['sections'][$_REQUEST['section']]))
			$body = $context['current_page']['body'];
		else
		{
			$context['edit_section'] = $_REQUEST['section'];
			$sect = &$b['sections'][$_REQUEST['section']];

			$body = str_repeat('=', $sect['level']) . ' ' . $sect['title'] . ' ' . str_repeat('=', $sect['level']) . '<br />' . $sect['content'];
		}
	}

	if (isset($_POST['arcontent']))
	{
		$body = $smcFunc['htmlspecialchars']( $_POST['arcontent'], ENT_QUOTES);
	}
	else
		$body = un_preparsecode($body);

	$preview_content = $body;

	preparsecode($body, true);

	$context['form_content'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $body);

	if (isset($_REQUEST['preview']))
	{
		preparsecode($preview_content);
		$context['page_content'] = wikiparser($context['current_page']['title'], $preview_content, true, $context['current_page']['namespace']);
	}

	$context['comment'] = '';
	if (isset($_POST['comment']))
		$context['comment'] = $_POST['comment'];

	$editorOptions = array(
		'id' => 'arcontent',
		'value' => rtrim($context['form_content']),
		'labels' => array(
			'post_button' => $txt['wiki_save'],
		),
		'width' => '100%',
		'height' => '250px',
	);
	create_control_richedit($editorOptions);

	$context['post_box_name'] = 'arcontent';

	// Template
	loadTemplate('WikiPage', array('article'));
	$context['page_title'] = $txt['wiki_edit_page'] . ' - ' . sprintf($txt['edit_page'], $context['current_page']['title']);
	$context['wiki_title'] = sprintf($txt['edit_page'], $context['current_page']['title']);
	$context['sub_template'] = 'edit_page';
}

function EditPage2()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_edit');

	require_once($sourcedir . '/Subs-Editor.php');
	require_once($sourcedir . '/Subs-Post.php');

	if (!empty($_REQUEST['arcontent_mode']) && isset($_REQUEST['arcontent']))
	{
		$_REQUEST['arcontent'] = html_to_bbc($_REQUEST['arcontent']);
		$_REQUEST['arcontent'] = un_htmlspecialchars($_REQUEST['arcontent']);
		$_POST['arcontent'] = $_REQUEST['arcontent'];
	}

	if (isset($_REQUEST['preview']))
	{
		return EditPage();
	}

	if (checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';
	if (htmltrim__recursive(htmlspecialchars__recursive($_POST['arcontent'])) == '')
		$_POST['arcontent'] = '';
	else
	{
		$_POST['arcontent'] = $smcFunc['htmlspecialchars']($_POST['arcontent'], ENT_QUOTES);

		if (!empty($_REQUEST['section']))
			$_POST['arcontent'] .= "\n";

		preparsecode($_POST['arcontent']);
	}

	if (!empty($post_errors))
	{
		loadLanguage('Errors');
		$_REQUEST['preview'] = true;

		$context['post_error'] = array('messages' => array());
		foreach ($post_errors as $post_error)
		{
			$context['post_error'][$post_error] = true;
			$context['post_error']['messages'][] = $txt['error_' . $post_error];
		}

		return EditPage();
	}

	// Handle sections
	if (empty($_REQUEST['section']))
		$body = $_POST['arcontent'];
	else
	{
		$b = wikiparser($context['current_page']['title'], $context['current_page']['body'], false);

		if (!isset($b['sections'][$_REQUEST['section']]))
			$body = $_POST['arcontent'];
		else
		{
			$body = '';

			foreach ($b['sections'] as $id => $sect)
			{
				if ($sect['level'] == 1)
					$body .= $sect['content'];
				elseif ($id != $_REQUEST['section'])
					$body .= str_repeat('=', $sect['level']) . ' ' . $sect['title'] . ' ' . str_repeat('=', $sect['level']) . '<br />' . $sect['content'];
				else
					$body .= $_POST['arcontent'];
			}
		}
	}

	if (!$context['current_page'] = loadWikiPage($_REQUEST['page'], $_REQUEST['namespace']))
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}wiki_pages',
			array(
				'title' => 'string-255',
				'namespace' => 'string-255',
			),
			array(
				$_REQUEST['page'],
				$_REQUEST['namespace'],
			),
			'id_page'
		);

		$context['current_page'] = array(
			'id' => $smcFunc['db_insert_id']('{db_prefix}wiki_pages', 'id_article')
		);
	}

	preparsecode($_POST['comment']);

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
			$context['current_page']['id'],
			$user_info['id'],
			time(),
			$body,
			$_POST['comment']
		),
		'id_revision'
	);

	$id_revision = $smcFunc['db_insert_id']('{db_prefix}articles_content', 'id_revision');

	$smcFunc['db_query']('' ,'
		UPDATE {db_prefix}wiki_pages
		SET
			id_revision_current = {int:revision}
		WHERE id_page = {int:page}',
		array(
			'page' => $context['current_page']['id'],
			'revision' => $id_revision,
		)
	);

	redirectexit(wiki_get_url(array(
		'page' => wiki_urlname($_REQUEST['page'], $_REQUEST['namespace']),
	)));
}

?>