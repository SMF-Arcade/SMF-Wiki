<?php

function ViewPageHistory()
{
	global $context, $scripturl, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

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
			'href' => $context['url'] . '?revision=' . $row['id_revision'],
			'diff_current_href' => $context['url'] . '?action=diff;old_revision=' . $row['id_revision'],
			'diff_prev_href' => $context['url'] . '?action=diff;revision=' . $row['id_revision'] . ';old_revision=' . $row['id_prev_revision'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Template
	loadTemplate('WikiPage');
	$context['page_title'] = $context['forum_name'] . ' - ' . sprintf($txt['revision_history'], $context['current_page']['title']);
	$context['wiki_title'] = sprintf($txt['revision_history'], $context['current_page']['title']);

	$context['sub_template'] = 'page_history';
}

?>