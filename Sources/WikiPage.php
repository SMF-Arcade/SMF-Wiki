<?php
/**
 * View Wikipage
 *
 * @package core
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function ViewPage()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	// Show error page if not found
	if ($context['page_info']['id'] === null)
	{
		$context['robot_no_index'] = true;
		$context['sub_template'] = 'not_found';
		
		if (isset($context['wikimenu']['edit']['url']))
			$context['create_message'] = sprintf($txt['wiki_create_page'], '<a href="' . $context['wikimenu']['edit']['url'] . '">', '</a>');
	}
	elseif (!empty($context['page_info']['is_deleted']))
	{
		$context['robot_no_index'] = true;
		$context['sub_template'] = 'page_deleted';
	}
	else
	{
		// Check if page has index tag
		if (isset($context['wiki_page']->pageSettings['no_index']))
			$context['robot_no_index'] = $context['wiki_page']->pageSettings['no_index'];
		
		$context['sub_template'] = 'view_page';
	}
}

function DiffPage()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	// This is pretty much duplicate content
	$context['robot_no_index'] = true;

	if (empty($_REQUEST['old_revision']) || (!empty($_REQUEST['revision']) && $_REQUEST['revision'] < $_REQUEST['old_revision']))
		fatal_lang_error('wiki_old_revision_not_selected', false);

	// Load content itself
	$context['wiki_parser_compare'] = cache_quick_get(
		'wiki-page-' .  $context['page_info']['id'] . '-rev' . (int) $_REQUEST['old_revision'],
		'Subs-Wiki.php', 'wiki_get_page_content',
		array($context['page_info'], $context['namespace'], (int) $_REQUEST['old_revision'])
	);
	
	$diff = $context['wiki_page']->compareTo($context['wiki_parser_compare']);

	$context['diff'] = array();

	$old = 0;
	$new = 0;

	foreach ($diff as $l)
	{
		if (!is_array($l))
		{
			$old++;
			$new++;

			$context['diff'][] = array(
				'',
				$l,
				$old,
				$new,
			);
		}
		else
		{
			if (!empty($l['d']))
			{
				foreach ($l['d'] as $ld)
				{
					$old++;

					$context['diff'][] = array(
						'd',
						$ld,
						$old,
						'...',
					);
				}
			}
			if (!empty($l['i']))
			{
				foreach ($l['i'] as $li)
				{
					$new++;

					$context['diff'][] = array(
						'a',
						$li,
						'...',
						$new,
					);
				}
			}
		}
	}

	$context['current_page_title'] = $context['page_info']['title'];

	// Template
	$context['page_title'] = $context['forum_name'] . ' - ' . $context['page_info']['title'];
	$context['sub_template'] = 'view_page';
}

function CleanCache()
{
	global $context;
	
	redirectexit($context['current_page_url']);
}

?>