<?php
/**
 * Provides discussion page for pages
 *
 * @package core
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function ViewTalkPage()
{
	global $boarddir, $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	require_once($boarddir . '/SSI.php');

	checkSubmitOnce('register');

	$context['form_url'] = wiki_get_url(array(
		'page' => $context['current_page_name'],
		'sa' => 'talk2',
	));
	
	if (!empty($context['page_info']->topic))
	{
		$context['comments'] = ssi_queryPosts('m.id_topic = {int:topic} AND m.id_board = {int:board}', array('topic' => $context['page_info']->topic, 'board' => $modSettings['wikiTalkBoard']), '', 'm.id_msg DESC', 'array');
	
		if (empty($context['comments']))
			$smcFunc['db_query']('' ,'
				UPDATE {wiki_prefix}pages
				SET id_topic = {int:topic}
				WHERE id_page = {int:page}',
				array(
					'page' => $context['page_info']->id,
					'topic' => 0,
				)
			);
	}
	
	$context['current_page_title'] = sprintf($txt['talk_page'], $context['current_page_title']);

	// Template
	$context['page_title'] = $context['forum_name'] . ' - ' . sprintf($txt['talk_page'], $context['current_page_title']);
	$context['sub_template'] = 'talk_page';
}

function ViewTalkPage2()
{
	global $boarddir, $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	checkSession('post');
	checkSubmitOnce('check');
	checkSubmitOnce('free');

	$context['form_url'] = wiki_get_url(array(
		'page' => $context['current_page_name'],
		'sa' => 'talk2',
	));

	$message = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
	preparsecode($message);

	$msgOptions = array(
		'subject' => '[WikiTalk] ' . $context['current_page_title'],
		'body' => $message,
	);
	$topicOptions = array(
		'board' => $modSettings['wikiTalkBoard'],
	);

	if (!empty($context['page_info']->topic))
		$topicOptions['id'] = $context['page_info']->topic;

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

	if (empty($context['page_info']->topic))
	{
		$smcFunc['db_query']('' ,'
			UPDATE {wiki_prefix}pages
			SET id_topic = {int:topic}
			WHERE id_page = {int:page}',
			array(
				'page' => $context['page_info']->id,
				'topic' => $topicOptions['id'],
			)
		);

		cache_put_data('wiki-pageinfo-' . wiki_cache_escape($context['namespace']['id'], $_REQUEST['page']), null, 3600);
	}

	redirectexit(wiki_get_url(array('page' => $context['current_page_name'], 'sa' => 'talk')));
}

?>