<?php
/**********************************************************************************
* WikiTalkPage.php                                                                *
***********************************************************************************
* SMF Wiki                                                                        *
* =============================================================================== *
* Software Version:           SMF Wiki 0.1                                        *
* Software by:                Niko Pahajoki (http://www.madjoki.com)              *
* Copyright 2008 by:          Niko Pahajoki (http://www.madjoki.com)              *
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

function ViewTalkPage()
{
	global $boarddir, $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	require_once($boarddir . '/SSI.php');

	checkSubmitOnce('register');

	$context['form_url'] = wiki_get_url(array_merge(array('sa' => 'talk2')), $context['wiki_url']);

	$context['comments'] = ssi_queryPosts('m.id_topic = {int:topic}', array('topic' => $context['current_page']['topic']), '', 'm.id_msg DESC', 'array');

	$context['current_page_title'] = sprintf($txt['talk_page'], $context['current_page']['title']);

	// Template
	$context['page_title'] = $context['forum_name'] . ' - ' . sprintf($txt['talk_page'], $context['current_page']['title']);
	$context['sub_template'] = 'talk_page';
}

function ViewTalkPage2()
{
	global $boarddir, $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	checkSession('post');
	checkSubmitOnce('check');
	checkSubmitOnce('free');

	$context['form_url'] = wiki_get_url(array_merge(array('sa' => 'talk2')), $context['wiki_url']);

	$message = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
	preparsecode($message);

	$msgOptions = array(
		'subject' => '[WikiTalk] ' . $context['current_page']['title'],
		'body' => $message,
	);
	$topicOptions = array(
		'board' => 80,
	);

	if (!empty($context['current_page']['topic']))
		$topicOptions['id'] = $context['current_page']['topic'];

	if ($user_info['is_guest'])
	{
		$posterOptions = array(
			'name' => $smcFunc['htmlspecialchars']('Guest', ENT_QUOTES),
			'email' => $smcFunc['htmlspecialchars']('', ENT_QUOTES),
		);
	}
	else
	{
		$posterOptions = array(
			'id' => $user_info['id'],
			'update_post_count' => false,
		);
	}

	createPost($msgOptions, $topicOptions, $posterOptions);

	if (empty($context['current_page']['topic']))
	{
		$smcFunc['db_query']('' ,'
			UPDATE {db_prefix}wiki_pages
			SET id_topic = {int:topic}
			WHERE id_page = {int:page}',
			array(
				'page' => $context['current_page']['id'],
				'topic' => $topicOptions['id'],
			)
		);
	}

	redirectexit($context['base_url'] . '?action=talk');
}

?>