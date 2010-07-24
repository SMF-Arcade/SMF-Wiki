<?php
// Version: 0.1; WikiPage

function template_wikipage_above()
{
	global $context, $modSettings, $txt;

	echo '
	<div class="wikipage_top cat_bar">
		<h3 class="catbg">
			', $context['current_page_title'], '
		</h3>
	</div>
	<div class="wikicontent windowbg2 clearright">
		<span class="topslice"><span></span></span>
		<div class="content">
			<div class="post"><div class="inner">
				<div class="wikimenu buttonlist">
					<ul>';

	foreach ($context['wikimenu'] as $id => $item)
	{
		if (empty($item['show']))
			continue;

		echo '
						<li class="firstlevel"><a', $item['selected'] ? ' class="active"' : '', ' href="', $item['url'], '"><span class="firstlevel">', $item['title'], '</span></a></li>';
	}

	echo '
					</ul>
				</div>';
}

function output_toc($baseurl, $blevel, $toc)
{
	global $context, $modSettings, $txt;
	
	foreach ($toc as $item)
	{
		echo '
		<li><a href="', $baseurl, '#', $item['id'], '">', $blevel, !empty($blevel) ? '.' : '', $item['level'], ' ', $item['name'], '</a>';

		if (!empty($item['sub']))
			echo '
			<ul class="reset">',
				output_toc($baseurl, (!empty($blevel) ? $blevel . '.' . $item['level'] : $item['level']) , $item['sub']), '
			</ul>';

		echo '
		</li>';
	}
}

function template_view_page()
{
	global $context, $modSettings, $txt;
	
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
				$action[1] = $action[1];

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

	template_wiki_content($context['wiki_page']);
	
	if (!empty($context['category_members']))
	{
		echo '
		<h2>', sprintf($txt['wiki_pages_in_category'], $context['category_name']), '</h2>
		<ul>';
		
		foreach ($context['category_members'] as $category)
			echo '
			<li><a href="', wiki_get_url($category['page']), '">', $category['title'], '</a></li>';
		
		echo '
		</ul>';
	}
	
	if (!empty($context['wiki_page']->categories))
	{
		echo '
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			', $txt['wiki_categories'], ': ';
		
		$first = true;
		
		foreach ($context['wiki_page']->categories as $category)
		{
			// Make sure delimeter is only after first
			if (!$first)
				echo ' | ';
			else
				$first = false;
				
			echo '
			<a href="', $category['link'], '"', !$category['exists'] ? 'class=" redlink"' : '', '>', $category['name'], '</a>';
		}
		
		echo '
			<span class="botslice"><span></span></span>
		</div>';
	}
}

function template_wiki_content($wikiPage)
{
	global $context, $modSettings, $txt;
	
	foreach ($wikiPage->sections as $section)
	{
		if ($section['level'] > 1 && $section['level'] < 5)
		{
			echo '
			<h', $section['level'] + 1, ' id="', $section['id'], '">
				<span class="floatleft">', $section['name'], '</span>';

			if (!empty($context['can_edit_page']))
				echo '
				<span class="floatright smalltext">[<a href="', $section['edit_url'], '">', $txt['wiki_edit_section'], '</a>]</span>';

			echo '
				<br class="clearright" />
			</h', $section['level'] + 1, '>';
		}
		// Replace this
		elseif ($section['level'] >= 5)
		{
			echo '
			<h6 id="', $section['id'], '" class="clearfix">
				<span class="floatleft">', $section['title'], '</span>';

			if (!empty($context['can_edit_page']))
				echo '
				<span class="floatright smalltext">[<a href="', $section['edit_url'], '">', $txt['wiki_edit_section'], '</a>]</span>';

			echo '
				<br class="clearright" />
			</h6>';
		}

		if ($section['level'] == 1 && isset($context['current_file']))
			print_r($context['current_file']);

		echo $section['html'];
		
		if ($section['level'] == 1 && empty($wikiPage->pageSettings['hide_toc']) && !empty($wikiPage->tableOfContents))
			echo '
			<div class="wikitoc floatright">
				<ul class="reset">',
					output_toc($context['current_page_url'], '', $wikiPage->tableOfContents), '
				</ul>
			</div>';
	}
}

function template_talk_page()
{
	global $context, $modSettings, $txt;

	if (!empty($context['comments']))
	{
		echo '
	<div style="width: 90%; text-align; center; margin: auto;">';

		foreach ($context['comments'] as $comment)
		{
			echo '
		<div class="comment wikibg2">
			', $comment['body'], '
		</div>
		<div class="commentinfo smalltext">', $comment['time'], ' ', $txt['by'], ' ', $comment['poster']['link'], '</div>';
		}

		echo '
	</div>';
	}
	
	echo '
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

		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
	</form>';
}

function template_recent_changes()
{
	global $context, $modSettings, $txt;

	echo '
	<form action="', $context['form_url'], '">
		<ul class="reset recent_changes">';

	foreach ($context['recent_changes'] as $item)
	{
		echo '
			<li>
				<span class="difflinks">
					(', $item['previous'] ? '<a href="' . $item['diff_href'] . '">' . $txt['wiki_diff_short'] . '</a>' : $txt['wiki_diff_short'], ')
					(<a href="' . $item['history_href'] . '">', $txt['wiki_history_short'], '</a>)
				</span>
				<span class="page">', $item['link'], '</span>
				<span class="author">', $item['author']['link'], '</span>
				<span class="date">', $item['date'], '</span>
				<span class="comment">', $item['comment'], '</span>
			</li>';
	}

	echo '
		</ul>
	</form>';
}

function template_page_history()
{
	global $context, $modSettings, $txt;

	echo '
	<form action="', $context['form_url'], '" method="post">
		<ul class="reset wikihistory">';

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
				if (document.forms.editpage.elements[textFields[i]])
					document.forms.editpage[textFields[i]].value = document.forms.editpage[textFields[i]].value.replace(/&#/g, "&#38;#");
			for (var i = document.forms.editpage.elements.length - 1; i >= 0; i--)
				if (document.forms.editpage.elements[i].name.indexOf("options") == 0)
					document.forms.editpage.elements[i].value = document.forms.editpage.elements[i].value.replace(/&#/g, "&#38;#");
		}
	// ]]></script>';

	if (isset($context['wiki_page_preview']))
	{
		template_wiki_content($context['wiki_page_preview']);

		echo '
		<hr />';
	}

	echo '
	<form action="', $context['form_url'], '" method="post" accept-charset="', $context['character_set'], '" name="editpage" id="editpage" onsubmit="submitonce(this);saveEntities();" enctype="multipart/form-data">
		<div style="width: 95%; margin: auto">
			<div id="bbcBox_message"></div>
			<div id="smileyBox_message"></div>
			', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
			<div style="text-align: center">
				<span class="smalltext"><br />', $txt['shortcuts'], '</span><br />
				', template_control_richedit_buttons($context['post_box_name']), '
			</div>
			<div>
				', $txt['edit_summary'], ': <br />
				<input type="text" name="comment" value="', $context['comment'], '" size="65" /><br />';

			if ($context['can_lock_page'])
				echo '
				<input type="checkbox" name="lock_page" value="1"', $context['page_info']['is_locked'] ? ' checked="checked"' : '', '/> ', $txt['lock_page'];

			echo '
			</div>
		</div>

		<input type="hidden" name="section" value="', $context['edit_section'], '" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';
}

function template_delete_page()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<form action="', $context['form_url'], '" method="post" accept-charset="', $context['character_set'], '" name="deletepage" id="deletepage">
		<div style="width: 95%; margin: auto">
			<div>';

	if ($context['can_delete_permanent'])
		echo '
				<input type="checkbox" name="permanent_delete" value="1" /> ', $txt['delete_page_permanent'], '<br />';
		
	echo '
			</div>
			<div style="text-align: center">
				<input class="button_submit" type="submit" name="delete" value="', $txt['delete_page_button'], '" />
			</div>
		</div>

		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';	
}

function template_restore_page()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<form action="', $context['form_url'], '" method="post" accept-charset="', $context['character_set'], '" name="restorepage" id="restorepage">
		<div style="width: 95%; margin: auto">
			<div style="text-align: center">
				<input class="button_submit" type="submit" name="delete" value="', $txt['restore_page_button'], '" />
			</div>
		</div>

		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';	
}

function template_not_found()
{
	global $context, $txt;

	echo '
		<p>', $txt['wiki_page_not_found'], '</p>';
			
	if (!empty($context['create_message']))
		echo '
		<p>', $context['create_message'], '</p>';
}

function template_page_deleted()
{
	global $context, $txt;

	echo '
		<p>', $txt['wiki_page_deleted'], '</p>';
}


function template_wikipage_below()
{
	global $context, $txt;

	echo '
			</div></div>
		</div>
		<span class="botslice"><span></span></span>
	</div>';
}

?>