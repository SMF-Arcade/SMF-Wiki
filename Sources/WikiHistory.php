<?php
/**********************************************************************************
* WikiHistory.php                                                                 *
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

function WikiRecentChanges()
{
	global $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	$context['form_url'] = wiki_get_url(array(
		'page' => $context['current_page_name'],
	));

	$request = $smcFunc['db_query']('', '
		SELECT
			con.id_revision, con.id_page, con.timestamp, con.comment, mem.id_member, mem.real_name, MAX(prev.id_revision) AS id_prev_revision,
			page.title, page.namespace
		FROM {db_prefix}wiki_content AS con
			INNER JOIN {db_prefix}wiki_pages AS page ON (page.id_page = con.id_page)
			LEFT JOIN {db_prefix}wiki_content AS prev ON (prev.id_revision < con.id_revision AND prev.id_page = con.id_page)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = con.id_author)
		GROUP BY con.id_revision
		ORDER BY con.id_revision DESC',
		array(
		)
	);

	$context['recent_changes'] = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['recent_changes'][] = array(
			'title' => read_urlname($row['title']),
			'link' => '<a href="' . wiki_get_url(array('page' => wiki_urlname($row['title'], $row['namespace']))) . '">' . read_urlname($row['title']) . '</a>',
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
				'page' => wiki_urlname($row['title'], $row['namespace']),
				'revision' => $row['id_revision'],
			)),
			'diff_href' => wiki_get_url(array(
				'page' => wiki_urlname($row['title'], $row['namespace']),
				'sa' => 'diff',
				'old_revision' => $row['id_prev_revision'],
			)),
			'history_href' => wiki_get_url(array(
				'page' => wiki_urlname($row['title'], $row['namespace']),
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
		FROM {db_prefix}wiki_content AS con
			LEFT JOIN {db_prefix}wiki_content AS prev ON (prev.id_revision < con.id_revision AND prev.id_page = {int:page})
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = con.id_author)
		WHERE con.id_page = {int:page}
		GROUP BY con.id_revision
		ORDER BY id_revision DESC',
		array(
			'page' => $context['current_page']['id'],
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
			'current' => $row['id_revision'] == $context['current_page']['current_revision'],
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

	$context['current_page_title'] = sprintf($txt['revision_history'], $context['current_page']['title']);

	// Template
	$context['page_title'] = $context['forum_name'] . ' - ' . sprintf($txt['revision_history'], $context['current_page']['title']);
	$context['sub_template'] = 'page_history';
}

?>