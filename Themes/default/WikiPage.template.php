<?php
// Version: 0.1; WikiPage

function template_wikipage_above()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<ul class="wikimenu">';

	foreach ($context['wikimenu'] as $id => $item)
	{
		if (empty($item['show']))
			continue;

		$classes = array(
			$id,
		);

		if ($item['selected'])
			$classes[] = 'selected';

		echo '
		<li class="',  implode(' ', $classes), '"><a href="', $item['url'], '">', $item['title'], '</a></li>';
	}

	echo '
	</ul>
	<div class="wikicontent">
		<h2>', $context['current_page_title'], '</h2>
		<div class="post">';
}

function output_toc($baseurl, $blevel, $toc)
{
	global $context, $modSettings, $txt, $user_info;

	foreach ($toc as $x)
	{
		list ($level, $title, $subtoc) = $x;

		echo '
		<li><a href="', $baseurl, '#', make_html_safe($title), '">', $blevel, !empty($blevel) ? '.' : '', $level, ' ', $title, '</a>';

		if (!empty($subtoc))
		{
			echo '
			<ul>';

			output_toc($baseurl, (!empty($blevel) ? $blevel . '.' . $level : $level) , $subtoc);

			echo '
			</ul>';
		}

		echo '
		</li>';
	}
}

function template_view_page()
{
	global $context, $modSettings, $txt, $user_info;

	if (isset($context['diff']))
	{
		echo '
	<div class="tborder diff">
		<div class="windowbg">';

		foreach ($context['diff'] as $action)
		{
			$style = '';

			if (trim($action[1]) == '')
				$action[1] = '&nbsp;';
			else
				$action[1] = htmlspecialchars($action[1]);

			if (empty($action[0]))
				$style = '';
			elseif ($action[0] == 'a')
				$style .= 'background-color: #DDFFDD';
			elseif ($action[0] == 'd')
				$style .= 'background-color: #FFDDDD';

			echo '
			<dl class="clearfix">
				<dt>', $action[2], '</dt>
				<dt>', $action[3], '</dt>
				<dd class="windowbg2" style="', $style, '">', $action[1], '</dd>
			</dl>';
		}

		echo '
		</div>
	</div>';
	}

	template_wiki_content();
}

function template_wiki_content()
{
	global $context, $modSettings, $txt, $user_info;

	foreach ($context['page_content']['sections'] as $section)
	{
		if ($section['level'] > 1 && $section['level'] < 5)
		{
			echo '
			<h', $section['level'] + 1, ' id="', make_html_safe($section['title']), '" class="clearfix">
				<span class="floatleft">', $section['title'], '</span>';

			if (!empty($context['can_edit_page']))
				echo '
				<span class="floatright smalltext">[<a href="', $section['edit_url'], '">', $txt['wiki_edit_section'], '</a>]</span>';

			echo '
			</h', $section['level'] + 1, '>';
		}
		// Replace this
		elseif ($section['level'] >= 5)
		{
			echo '
			<h6 id="', make_html_safe($section['title']), '" class="clearfix">
				<span class="floatleft">', $section['title'], '</span>';

			if (!empty($context['can_edit_page']))
				echo '
				<span class="floatright smalltext">[<a href="', $section['edit_url'], '">', $txt['wiki_edit_section'], '</a>]</span>';

			echo '
			</h6>';
		}

		if ($section['level'] == 1)
		{
			if (!empty($context['page_content']['toc']))
				echo '
			<div class="wikitoc floatright">
				<ul>',
					output_toc($context['current_page']['url'], '', $context['page_content']['toc']), '
				</ul>
			</div>';
		}

		echo '
			', $section['content'];
	}
}

function template_talk_page()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<div style="width: 90%; text-align; center; margin: auto;">';

	foreach ($context['comments'] as $news)
	{
		echo '
		<div class="comment wikibg2">
			', $news['body'], '
		</div>
		<div class="commentinfo smalltext">', $news['time'], ' ', $txt['by'], ' ', $news['poster']['link'], '</div>';
	}

	echo '
	</div>
	<form action="', $context['form_url'], '" method="post">';

	if (!empty($context['talk_errors']))
	{
		echo '
		<ul>
			<li>', implode('</li>
			<li>', $context['talk_errors']), '</li>
		</ul>';
	}

	echo '
		<div style="text-align: center">
			<textarea id="message" name="message" cols="55" rows="10" style="width: 95%">', isset($context['talk_message']) ? $context['talk_message'] : '', '</textarea><br />
			<input type="submit" name="send" value="', $txt['add_comment'], '" />
		</div>

		<input type="hidden" name="sc" value="', $context['session_id'], '" />
		<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
	</form>';
}

function template_recent_changes()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<form action="', $context['form_url'], '">
		<ul class="recent_changes">';

	foreach ($context['recent_changes'] as $item)
	{
		echo '
			<li>
				<span class="difflinks">
					(', !$item['current'] ? '<a href="' . $item['diff_current_href'] . '">' . $txt['wiki_diff_cur'] . '</a>' : $txt['wiki_diff_cur'], ')
					(', !empty($item['previous']) ? '<a href="' . $item['diff_prev_href'] . '">' . $txt['wiki_diff_prev'] . '</a>' : $txt['wiki_diff_prev'], ')
				</span>
				<span class="diffselect">
					<input type="radio" name="revision" value="', $item['revision'], '" />
					<input type="radio" name="old_revision" value="', $item['revision'], '" ', $item['current'] ? 'disabled="disabled" ' : '', '/>
				</span>
				<span class="date"><a href="', $item['href'], '">', $item['date'], '</a></span>
				<span class="author">', $item['author']['link'], '</span>
				<span class="page">', $item['link'], '</span>
				<span class="comment">', $item['comment'], '</span>
			</li>';
	}

	echo '
		</ul>
	</form>';
}

function template_page_history()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<form action="', $context['form_url'], '">
		<ul class="wikihistory">';

	foreach ($context['history'] as $item)
	{
		echo '
			<li>
				<span class="difflinks">
					(', !$item['current'] ? '<a href="' . $item['diff_current_href'] . '">' . $txt['wiki_diff_cur'] . '</a>' : $txt['wiki_diff_cur'], ')
					(', !empty($item['previous']) ? '<a href="' . $item['diff_prev_href'] . '">' . $txt['wiki_diff_prev'] . '</a>' : $txt['wiki_diff_prev'], ')
				</span>
				<span class="diffselect">
					<input type="radio" name="revision" value="', $item['revision'], '" />
					<input type="radio" name="old_revision" value="', $item['revision'], '" ', $item['current'] ? 'disabled="disabled" ' : '', '/>
				</span>
				<span class="date"><a href="', $item['href'], '">', $item['date'], '</a></span>
				<span class="author">', $item['author']['link'], '</span>
				<span class="comment">', $item['comment'], '</span>
			</li>';
	}

	echo '
		</ul>

		<input type="submit" value="', $txt['compare_versions'], '" />
		<input type="hidden" name="sa" value="diff" />
	</form>';
}

function template_edit_page()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<script language="JavaScript" type="text/javascript"><!-- // --><![CDATA[
		function saveEntities()
		{
			var textFields = ["comment", "', $context['post_box_name'], '"];
			for (i in textFields)
				if (document.forms.editarticle.elements[textFields[i]])
					document.forms.editarticle[textFields[i]].value = document.forms.editarticle[textFields[i]].value.replace(/&#/g, "&#38;#");
			for (var i = document.forms.editarticle.elements.length - 1; i >= 0; i--)
				if (document.forms.editarticle.elements[i].name.indexOf("options") == 0)
					document.forms.editarticle.elements[i].value = document.forms.editarticle.elements[i].value.replace(/&#/g, "&#38;#");
		}
	// ]]></script>';

	if (isset($context['page_content']))
	{
		template_wiki_content();

		echo '
		<hr />';
	}

	echo '
	<form action="', $context['form_url'], '" method="post" accept-charset="', $context['character_set'], '" name="editarticle" id="editarticle" onsubmit="submitonce(this);saveEntities();" enctype="multipart/form-data">
		<div style="width: 95%; margin: auto">
			<div>
				', template_control_richedit($context['post_box_name'], 'bbc'), '
			</div>
			<div>
				', template_control_richedit($context['post_box_name'], 'smileys'), '<br />
			</div>
			<div>
				', template_control_richedit($context['post_box_name'], 'message'), '
			</div>
			<div style="text-align: center">
				<span class="smalltext"><br />', $txt['shortcuts'], '</span><br />
				', template_control_richedit($context['post_box_name'], 'buttons'), '
			</div>
			<div>
				', $txt['edit_summary'], ': <br />
				<input type="text" name="comment" value="', $context['comment'], '" size="65" /><br />';

			if ($context['can_lock_page'])
				echo '
				<input type="checkbox" name="lock_page" value="1"', $context['current_page']['is_locked'] ? ' checked="checked"' : '', '/> ', $txt['lock_page'];

			echo '
				</div>
		</div>

		<input type="hidden" name="section" value="', $context['edit_section'], '" />
		<input type="hidden" name="sc" value="', $context['session_id'], '" />
	</form>';
}

function template_not_found()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
			', $txt['wiki_page_not_found'], '';
}

function template_wikipage_below()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
		</div>
	</div>';
}

?>