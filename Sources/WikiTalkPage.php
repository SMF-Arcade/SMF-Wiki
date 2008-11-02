<?php

function ViewTalkPage()
{
	global $boarddir, $context, $baseurl, $scripturl, $rooturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	require_once($boarddir . '/SSI.php');

	checkSubmitOnce('register');

	$context['comments'] = ssi_queryPosts('m.id_topic = {int:topic}', array('topic' => $context['current_page']['topic']), '', 'm.id_msg DESC', 'array');

	// Template
	$context['page_title'] = $context['forum_name'] . ' - ' . sprintf($txt['talk_page'], $context['current_page']['title']);
	$context['wiki_title'] = sprintf($txt['talk_page'], $context['current_page']['title']);
	$context['sub_template'] = 'talk_page';
}

function ViewTalkPage2()
{
	global $boarddir, $context, $baseurl, $scripturl, $rooturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	checkSession('post');
	checkSubmitOnce('check');
	checkSubmitOnce('free');

	require_once($sourcedir . '/Subs-Post.php');

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