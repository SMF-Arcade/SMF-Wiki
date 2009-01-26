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
		$b = wikiparser($context['page_info'], $context['page_content_raw'], false);

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
		$context['page_content'] = wikiparser($context['page_info'], $preview_content, true, $context['namespace']['id']);
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
		$b = wikiparser($context['page_info'], $context['page_info']['body'], false);

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
		$context['page_info']['id'] = createPage($_REQUEST['page'], $context['namespace']);

	preparsecode($_POST['comment']);

	$pageOptions = array();
	$revisionOptions = array(
		'file' => !empty($context['page_info']['id_file']) ? $context['page_info']['id_file'] : 0,
		'body' => $body,
		'comment' => $_POST['comment'],
	);
	$posterOptions = array(
		'id' => $user_info['id'],
	);

	if ($context['can_lock_page'])
		$pageOptions['lock'] = !empty($_REQUEST['lock_page']);

	createRevision($context['page_info']['id'], $pageOptions, $revisionOptions, $posterOptions);

	redirectexit(wiki_get_url(wiki_urlname($_REQUEST['page'], $context['namespace']['id'])));
}

?>