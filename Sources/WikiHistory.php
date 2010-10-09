<?php
/**
 * History and recent changes pages
 *
 * @package core
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function WikiRecentChanges()
{
	global $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	$context['form_url'] = wiki_get_url(array(
		'page' => $context['current_page_name'],
	));

	// Set page title
	$context['current_page_title'] = $txt['wiki_recent_changes'];

	$request = $smcFunc['db_query']('', '
		SELECT
			con.id_revision, con.id_page, con.timestamp, con.comment, mem.id_member, mem.real_name, MAX(prev.id_revision) AS id_prev_revision,
			page.title, page.namespace
		FROM {wiki_prefix}content AS con
			INNER JOIN {wiki_prefix}pages AS page ON (page.id_page = con.id_page)
			LEFT JOIN {wiki_prefix}content AS prev ON (prev.id_revision < con.id_revision AND prev.id_page = con.id_page)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = con.id_author)
		WHERE
			page.is_deleted = 0
		GROUP BY con.id_revision
		ORDER BY con.id_revision DESC',
		array(
		)
	);

	$context['recent_changes'] = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['recent_changes'][] = array(
			'title' => get_default_display_title($row['title'], $row['namespace']),
			'link' => '<a href="' . wiki_get_url(array('page' => wiki_get_url_name($row['title'], $row['namespace']))) . '">' . get_default_display_title($row['title'], false) . '</a>',
			'revision' => $row['id_revision'],
			'date' => timeformat($row['timestamp']),
			'author' => array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
			),
			'comment' => $row['comment'],
			'previous' => $row['id_prev_revision'],
			'href' => wiki_get_url(array(
				'page' => wiki_get_url_name($row['title'], $row['namespace']),
				'revision' => $row['id_revision'],
			)),
			'diff_href' => wiki_get_url(array(
				'page' => wiki_get_url_name($row['title'], $row['namespace']),
				'sa' => 'diff',
				'old_revision' => $row['id_prev_revision'],
			)),
			'history_href' => wiki_get_url(array(
				'page' => wiki_get_url_name($row['title'], $row['namespace']),
				'sa' => 'history',
			)),
		);
	}
	$smcFunc['db_free_result']($request);

	// Template
	loadTemplate('WikiPage');
	$context['page_title'] = sprintf($txt['wiki_recent_changes_title'], $context['forum_name']);
	$context['sub_template'] = 'recent_changes';
}

function ViewPageHistory()
{
	global $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	$context['form_url'] = wiki_get_url(array(
		'page' => $context['current_page_name'],
		'sa' => 'diff',
	));

	$request = $smcFunc['db_query']('', '
		SELECT con.id_revision, con.id_page, con.timestamp, con.comment, mem.id_member, mem.real_name, MAX(prev.id_revision) AS id_prev_revision
		FROM {wiki_prefix}content AS con
			LEFT JOIN {wiki_prefix}content AS prev ON (prev.id_revision < con.id_revision AND prev.id_page = {int:page})
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = con.id_author)
		WHERE con.id_page = {int:page}
		GROUP BY con.id_revision
		ORDER BY id_revision DESC',
		array(
			'page' => $context['page_info']->id,
		)
	);

	$context['history'] = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['history'][] = array(
			'revision' => $row['id_revision'],
			'date' => timeformat($row['timestamp']),
			'author' => array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
			),
			'comment' => $row['comment'],
			'current' => $row['id_revision'] == $context['page_info']->current_revision,
			'previous' => $row['id_prev_revision'],
			'href' => wiki_get_url(array(
				'page' => $context['current_page_name'],
				'revision' => $row['id_revision'],
			)),
			'diff_current_href' => wiki_get_url(array(
				'page' => $context['current_page_name'],
				'sa' => 'diff',
				'old_revision' => $row['id_revision'],
			)),
			'diff_prev_href' => wiki_get_url(array(
				'page' => $context['current_page_name'],
				'sa' => 'diff',
				'revision' =>  $row['id_revision'],
				'old_revision' => $row['id_prev_revision'],
			)),
		);
	}
	$smcFunc['db_free_result']($request);

	$context['current_page_title'] = sprintf($txt['revision_history'], $context['current_page_title']);

	// Template
	$context['page_title'] = $context['forum_name'] . ' - ' . sprintf($txt['revision_history'], $context['current_page_title']);
	$context['sub_template'] = 'page_history';
}

?>