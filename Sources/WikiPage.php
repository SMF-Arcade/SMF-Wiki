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
		$context['sub_template'] = 'view_page';
	}
}

function DiffPage()
{
	global $smcFunc, $context, $modSettings, $txt, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	// This is pretty much duplicate content
	$context['robot_no_index'] = true;

	if (empty($_REQUEST['old_revision']))
		fatal_lang_error('wiki_old_revision_not_selected', false);

	if (!empty($_REQUEST['revision']) && $_REQUEST['revision'] < $_REQUEST['old_revision'])
	{
		$context['diff_page'] = $context['page_info'];
		$context['page_info'] = loadWikiPage_old($_REQUEST['page'], $_REQUEST['namespace'], (int) $_REQUEST['old_revision']);
	}
	else
	{
		$context['diff_page'] = loadWikiPage_old($_REQUEST['page'], $_REQUEST['namespace'], (int) $_REQUEST['old_revision']);
	}

	$diff = diff(explode("\n", un_preparsecode($context['diff_page']['body'])), explode("\n", un_preparsecode($context['page_info']['body'])));

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