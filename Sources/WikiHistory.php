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

function ViewPageHistory()
{
	global $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	$context['form_url'] = wiki_get_url(array_merge(array('sa' => 'history')), $context['wiki_url']);

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
			'href' => wiki_get_url(array_merge($context['wiki_url'], array('revision' => $row['id_revision']))),
			'diff_current_href' => wiki_get_url(array_merge($context['wiki_url'], array(
				'sa' => 'diff',
				'revision' => null,
				'old_revision' => $row['id_revision'],
			))),
			'diff_prev_href' => wiki_get_url(array_merge($context['wiki_url'], array(
				'sa' => 'diff',
				'revision' =>  $row['id_revision'],
				'old_revision' => $row['id_revision'],
			))),
		);
	}
	$smcFunc['db_free_result']($request);

	$context['current_page_title'] = sprintf($txt['revision_history'], $context['current_page']['title']);

	// Template
	$context['page_title'] = $context['forum_name'] . ' - ' . sprintf($txt['revision_history'], $context['current_page']['title']);
	$context['sub_template'] = 'page_history';
}

?>