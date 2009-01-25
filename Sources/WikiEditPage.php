<?php
/**********************************************************************************
* WikiEditPage.php                                                                *
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

function EditPage()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Editor.php');
	require_once($sourcedir . '/Subs-Post.php');

	isAllowedTo('wiki_edit');

	if (empty($context['can_edit_page']))
		fatal_lang_error('cannot_wiki_edit_current_page', false);

	$context['form_url'] = wiki_get_url(array(
		'page' => $context['current_page_name'],
		'sa' => 'edit2',
	));

	$context['edit_section'] = 0;

	if (!isset($context['page_content_raw']))
		$context['page_content_raw'] = '';

	if (empty($_REQUEST['section']))
		$body = $context['page_content_raw'];
	else
	{
		$b = wikiparser($context['page_info']['title'], $context['page_content_raw'], false);

		if (!isset($b['sections'][$_REQUEST['section']]))
			$body = $context['page_content_raw'];
		else
		{
			$context['edit_section'] = $_REQUEST['section'];
			$sect = &$b['sections'][$_REQUEST['section']];

			$body = str_repeat('=', $sect['level']) . ' ' . $sect['title'] . ' ' . str_repeat('=', $sect['level']) . '<br />' . $sect['content'];
		}
	}

	if (isset($_POST['wiki_content']))
		$body = $smcFunc['htmlspecialchars']($_POST['wiki_content'], ENT_QUOTES);
	else
		$body = un_preparsecode($body);

	$preview_content = $body;

	preparsecode($body, true);

	$context['form_content'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $body);

	if (isset($_REQUEST['preview']))
	{
		preparsecode($preview_content);
		$context['page_content'] = wikiparser($context['page_info']['title'], $preview_content, true, $context['namespace']['id']);
	}

	$context['comment'] = '';
	if (isset($_POST['comment']))
		$context['comment'] = $_POST['comment'];

	$editorOptions = array(
		'id' => 'wiki_content',
		'value' => rtrim($context['form_content']),
		'labels' => array(
			'post_button' => $txt['wiki_save'],
		),
		'width' => '100%',
		'height' => '250px',
	);
	create_control_richedit($editorOptions);

	$context['post_box_name'] = 'wiki_content';

	// Template
	loadTemplate('WikiPage', array('article'));
	$context['page_title'] = sprintf($txt['edit_page'], $context['page_info']['title']);
	$context['current_page_title'] = sprintf($txt['edit_page'], $context['page_info']['title']);
	$context['sub_template'] = 'edit_page';
}

function EditPage2()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_edit');

	if (empty($context['can_edit_page']))
		fatal_lang_error('cannot_wiki_edit_current_page', false);

	require_once($sourcedir . '/Subs-Editor.php');
	require_once($sourcedir . '/Subs-Post.php');

	if (!empty($_REQUEST['wiki_content_mode']) && isset($_REQUEST['wiki_content']))
	{
		$_REQUEST['wiki_content'] = html_to_bbc($_REQUEST['wiki_content']);
		$_REQUEST['wiki_content'] = un_htmlspecialchars($_REQUEST['wiki_content']);
		$_POST['wiki_content'] = $_REQUEST['wiki_content'];
	}

	if (isset($_REQUEST['preview']))
		return EditPage();

	if (checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';
	if (htmltrim__recursive(htmlspecialchars__recursive($_POST['wiki_content'])) == '')
		$_POST['wiki_content'] = '';
	else
	{
		$_POST['wiki_content'] = $smcFunc['htmlspecialchars']($_POST['wiki_content'], ENT_QUOTES);

		if (!empty($_REQUEST['section']))
			$_POST['wiki_content'] .= "\n";

		preparsecode($_POST['wiki_content']);
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
		$body = $_POST['wiki_content'];
	else
	{
		$b = wikiparser($context['page_info']['title'], $context['page_info']['body'], false);

		if (!isset($b['sections'][$_REQUEST['section']]))
			$body = $_POST['wiki_content'];
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
					$body .= $_POST['wiki_content'];
			}
		}
	}

	if ($context['page_info']['id'] === null)
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}wiki_pages',
			array(
				'title' => 'string-255',
				'namespace' => 'string-255',
			),
			array(
				$_REQUEST['page'],
				$context['namespace']['id'],
			),
			array('id_page')
		);

		$context['page_info']['id'] = $smcFunc['db_insert_id']('{db_prefix}wiki_pages', 'id_article');
	}

	preparsecode($_POST['comment']);

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
			$context['page_info']['id'],
			$user_info['id'],
			isset($context['current_file']) ? $context['current_file']['id'] : 0,
			time(),
			$body,
			$_POST['comment'],
		),
		array('id_revision')
	);

	$id_revision = $smcFunc['db_insert_id']('{db_prefix}articles_content', 'id_revision');

	$smcFunc['db_query']('' ,'
		UPDATE {db_prefix}wiki_pages
		SET
			id_revision_current = {int:revision}' . ($context['can_lock_page'] ? ',
			is_locked = {int:lock}' : '') . '
		WHERE id_page = {int:page}',
		array(
			'page' => $context['page_info']['id'],
			'lock' => !empty($_REQUEST['lock_page']) ? 1 : 0,
			'revision' => $id_revision,
		)
	);

	redirectexit(wiki_get_url(wiki_urlname($_REQUEST['page'], $context['namespace']['id'])));
}

?>