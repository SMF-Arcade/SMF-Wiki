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
	
	if (!isset($context['wiki_page']) || !$context['wiki_page'] instanceof WikiPage)
		$body = '';
	elseif (empty($_REQUEST['section']))
		$body = $context['wiki_page']->raw_content;
	else
	{
		// Reparse without bbc conversion or any fixes
		$context['wiki_page']->parse_bbc = false;
		$context['wiki_page']->parse();
		
		if (!isset($context['wiki_page']->sections[$_REQUEST['section']]))
			$body = $context['wiki_page']->raw_content;
		else
		{
			$context['edit_section'] = $_REQUEST['section'];
			$section = $context['wiki_page']->sections[$_REQUEST['section']];
			
			$body = str_repeat('=', $section['level']) . ' ' . $section['name'] . ' ' . str_repeat('=', $section['level']) . '<br />' . $section['html'];
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
		
		$context['wiki_page_preview'] = new WikiPage($context['page_info'], $context['namespace'], $preview_content);
		$context['wiki_page_preview']->parse();
		
		$context['current_page_title'] = $context['wiki_page_preview']->title;
	}

	$context['comment'] = '';
	if (isset($_POST['comment']))
		$context['comment'] = $_POST['comment'];

	$editorOptions = array(
		'form' => 'editpage',
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
	loadTemplate('WikiPage');
	$context['page_title'] = sprintf($txt['edit_page'], $context['current_page_title']);
	$context['current_page_title'] = sprintf($txt['edit_page'], $context['current_page_title']);
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
	if (!isset($context['wiki_page']) || !$context['wiki_page'] instanceof WikiPage || empty($_REQUEST['section']))
		$body = $_POST['wiki_content'];
	else
	{
		$context['wiki_page']->parse_bbc = false;
		$context['wiki_page']->parse();
		
		if (!isset($context['wiki_page']->sections[$_REQUEST['section']]))
			$body = $_POST['wiki_content'];
		else
		{
			$body = '';
			
			if (substr($_POST['wiki_content'], -6) != '<br />')
				$_POST['wiki_content'] .= '<br />';
					
			foreach ($context['wiki_page']->sections as $id => $section)
			{
				if (substr($section['html'], -6) != '<br />')
					$section['html'] .= '<br />';
				
				if ($section['level'] == 1)
					$body .= $section['html'];
				elseif ($id != $_REQUEST['section'])
					$body .= str_repeat('=', $section['level']) . ' ' . $section['name'] . ' ' . str_repeat('=', $section['level']) . '<br />' . $section['html'];
				else
					$body .= $_POST['wiki_content'];
			}
			
			// Trim start and end
			while (substr($body, 0, 6) == '<br />')
				$body = substr($body, 6);			
			while (substr($body, -6) == '<br />')
				$body = substr($body, 0, -6);
		}
	}

	if ($context['page_info']['id'] === null)
		$context['page_info']['id'] = createPage($_REQUEST['page'], $context['namespace']);

	preparsecode($_POST['comment']);

	// Parse Page for usage in 
	$context['wiki_page'] = new WikiPage($context['page_info'], $context['namespace'], $body);
	
	$context['wiki_page']->title = null;
	$context['wiki_page']->parse_bbc = true;
	$context['wiki_page']->raw_content = $body;
	$context['wiki_page']->parse();
	
	$pageOptions = array();
	$revisionOptions = array(
		'file' => !empty($context['page_info']['id_file']) ? $context['page_info']['id_file'] : 0,
		'body' => $body,
		'comment' => $_POST['comment'],
	);
	$posterOptions = array(
		'id' => $user_info['id'],
		'display_title' => !empty($context['wiki_page']->title) ? $context['wiki_page']->title : get_default_display_title($_REQUEST['page'], $context['namespace']),
	);

	if ($context['can_lock_page'])
		$pageOptions['lock'] = !empty($_REQUEST['lock_page']);

	createRevision($context['page_info']['id'], $pageOptions, $revisionOptions, $posterOptions);

	// Categories
	$rows = array();
	
	if (!empty($context['wiki_page']->categories))
		foreach ($context['wiki_page']->categories as $cat)
			$rows[$cat['title']] = array($context['page_info']['id'], $cat['title']);
	
	// Remove categories first
	$smcFunc['db_query']('', '
		DELETE FROM {wiki_prefix}category
		WHERE id_page = {int:page}',
		array(
			'page' => $context['page_info']['id'],
		)
	);
	
	// then insert new categories
	if (!empty($rows))		
		$smcFunc['db_insert']('replace',
			'{wiki_prefix}category',
			array('id_page' => 'int', 'category' => 'string',),
			$rows,
			array('id_page', 'category')
		);

	redirectexit($context['current_page_url']);
}

function DeletePage()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_admin');
	
	$context['form_url'] = wiki_get_url(array(
		'page' => $context['current_page_name'],
		'sa' => 'delete2',
	));
	
	// Template
	loadTemplate('WikiPage');
	$context['page_title'] = sprintf($txt['delete_page'], $context['current_page_title']);
	$context['current_page_title'] = sprintf($txt['delete_page'], $context['current_page_title']);
	$context['sub_template'] = 'delete_page';	
}

function DeletePage2()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_admin');
	
	checkSession('post');
	
	$delete_permanently = !empty($context['can_delete_permanent']) && !empty($_REQUEST['permanent_delete']);
	
	deleteWikiPage($context['page_info']['id'], !$delete_permanently);
	
	redirectexit($context['current_page_url']);
}

function RestorePage()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_admin');
	
	$context['form_url'] = wiki_get_url(array(
		'page' => $context['current_page_name'],
		'sa' => 'restore2',
	));
	
	// Template
	loadTemplate('WikiPage');
	$context['page_title'] = sprintf($txt['restore_page'], $context['current_page_title']);
	$context['current_page_title'] = sprintf($txt['restore_page'], $context['current_page_title']);
	$context['sub_template'] = 'restore_page';	
}

function RestorePage2()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_admin');
	
	checkSession('post');
	
	restoreWikiPage($context['page_info']['id'], !$delete_permanently);
	
	redirectexit($context['current_page_url']);
}

?>