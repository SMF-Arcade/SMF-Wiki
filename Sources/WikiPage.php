<?php
/**********************************************************************************
* WikiPage.php                                                                    *
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

function ViewPage()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	// Show error page if not found
	if ($context['page_info']['id'] === null)
	{
		$context['robot_no_index'] = true;
		$context['sub_template'] = 'not_found';
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

?>