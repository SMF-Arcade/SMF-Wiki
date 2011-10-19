<?php
/**
 * Edit Page
 *
 * @package core
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');
	
/**
 *
 */
function CreateNewPage()
{
	global $context;
	
	// Template
	loadTemplate('WikiPage');
	
	$context['sub_template'] = 'create_page';	
}

/**
 *
 */
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
		$body = $context['wiki_page']->parser->getRawContent();
	else
	{
		$edit_section = $context['wiki_page']->parser->getRawContentSection((int) $_REQUEST['section']);

		if (!$edit_section)
			$body = $context['wiki_page']->parser->getRawContent();
		else
		{
			$context['edit_section'] = $_REQUEST['section'];
			
			$body = str_repeat('=', $edit_section['level']) . ' ' . $edit_section['title'] . ' ' . str_repeat('=', $edit_section['level']) . $edit_section['content'];
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
		
		$context['page_info_preview'] = clone $context['page_info'];
		
		// $preview_content
		$context['wiki_page_preview'] = new WikiParser($context['page_info_preview']);
		$context['wiki_page_preview']->parse($preview_content);
		
		$context['current_page_title'] = $context['page_info_preview']->title;
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
	
	$context['sub_template'] = 'edit_page';
	$context['page_title'] = sprintf($txt['edit_page'], $context['current_page_title']);
	$context['current_page_title'] = sprintf($txt['edit_page'], $context['current_page_title']);
}

/**
 *
 */
function EditPage2()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_edit');

	if (!$create && empty($context['can_edit_page']))
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
		$sections = $context['wiki_page']->parser->getRawContentSection();
		
		if (!isset($sections[$_REQUEST['section']]))
			$body = $_POST['wiki_content'];
		else
		{
			$body = '';
			
			if (substr($_POST['wiki_content'], -6) != '<br />')
				$_POST['wiki_content'] .= '<br />';
					
			foreach ($sections as $id => $section)
			{
				if (substr($section['html'], -6) != '<br />')
					$section['html'] .= '<br />';
				
				if ($section['level'] == 1)
					$body .= $section['content'];
				elseif ($id != $_REQUEST['section'])
					$body .= str_repeat('=', $section['level']) . ' ' . $section['title'] . ' ' . str_repeat('=', $section['level']) . $section['content'];
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

	if (!$context['page_info']->exists)
		$context['page_info']->id = createPage($_REQUEST['page'], $context['namespace']);

	preparsecode($_POST['comment']);

	// Parse Page for usage in
	$context['page_info']->title = get_default_display_title($_REQUEST['page'], $context['namespace']['id']);
	$wikiParser = new WikiParser($context['page_info']);
	$wikiParser->parse($body);

	$pageOptions = array(
		'display_title' => $wikiParser->page->title,
	);
	$revisionOptions = array(
		'file' => !empty($context['wiki_page']->file) ? $context['wiki_page']->file['id'] : 0,
		'body' => $body,
		'comment' => $_POST['comment'],
	);
	$posterOptions = array(
		'id' => $user_info['id'],
	);

	if ($context['can_lock_page'])
		$pageOptions['lock'] = !empty($_REQUEST['lock_page']);

	createRevision($context['page_info']->id, $pageOptions, $revisionOptions, $posterOptions);

	// Categories
	$rows = array();
	
	if (!empty($context['page_info']->categories))
		foreach ($context['page_info']->categories as $cat)
			$rows[$cat['title']] = array($context['page_info']->id, $cat['title']);
	
	// Remove categories first
	$smcFunc['db_query']('', '
		DELETE FROM {wiki_prefix}category
		WHERE id_page = {int:page}',
		array(
			'page' => $context['page_info']->id,
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

/**
 *
 */
function ViewPageSource()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Editor.php');
	require_once($sourcedir . '/Subs-Post.php');

	$context['page_source'] = un_preparsecode($context['wiki_page']->parser->getRawContent());

	// Template
	loadTemplate('WikiPage');
	$context['page_title'] = sprintf($txt['view_source'], $context['current_page_title']);
	$context['current_page_title'] = sprintf($txt['view_source'], $context['current_page_title']);
	$context['sub_template'] = 'view_source';
}

/**
 *
 */
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

/**
 *
 */
function DeletePage2()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_admin');
	
	checkSession('post');
	
	$delete_permanently = !empty($context['can_delete_permanent']) && !empty($_REQUEST['permanent_delete']);
	
	deleteWikiPage($context['page_info']->id, !$delete_permanently);
	
	redirectexit($context['current_page_url']);
}

/**
 *
 */
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

/**
 *
 */
function RestorePage2()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	isAllowedTo('wiki_admin');
	
	checkSession('post');
	
	restoreWikiPage($context['page_info']->id);
	
	redirectexit($context['current_page_url']);
}

?>