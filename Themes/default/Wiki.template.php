<?php
// Version: 0.2; Wiki

function template_wiki_above()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<div class="floatleft wikileft"><div>';

	$is_first = true;

	foreach ($context['wiki_navigation'] as $group)
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg', !empty($group['selected']) ? ' selected' : '', '">';

		if (!empty($group['url']))
			echo '
				<a href="', $group['url'], '">', $group['title'], '</a>';
		else
			echo '
				', $group['title'];

		if ($is_first)
		{
			echo '
			<span class="floatright"><a href="', wiki_get_url('SMFWiki:Sidebar'), '">', $txt['edit'], '</span>';

			$is_first = false;
		}

		echo '
			</h3>
		</div>';
		
		if (!empty($group['items']))
		{
			echo '
		<div class="windowbg2">
			<span class="topslice"><span></span></span>
			<ul class="windowbg2">';

			foreach ($group['items'] as $item)
			{
				if (!empty($item['url']))
					echo '
				<li', $item['selected'] ? ' class="selected"' : '', '><a href="', $item['url'], '">', $item['title'], '</a></li>';
				else
					echo '
				<li>', $item['title'], '</li>';
			}

			echo '
			</ul>
			<span class="botslice"><span></span></span>
		</div>';
		}
		elseif (!empty($group['template']))
		{
			$template = 'template_' . $group['template'];
			$template();
		}

	}

	echo '
	</div></div>
	<div class="wikiright">';

	echo '
		<div class="wikipage_top cat_bar">
			<h3 class="catbg">
			', $context['current_page_title'], '
			</h3>
		</div>
		<div class="wikimenu buttonlist">
			<ul>';

	foreach ($context['wikimenu'] as $id => $item)
		echo '
				<li class="firstlevel"><a', !empty($item['selected']) ? ' class="active"' : '', ' href="', $item['url'], '"><span class="firstlevel">', $item['title'], '</span></a></li>';

	echo '
			</ul>
		</div>';
}

function template_wiki_below()
{
	global $context, $modSettings, $txt, $user_info, $wiki_version;

	echo '
	</div>
	<div id="project_bottom" class="smalltext" style="text-align: center; clear: both;">
		Powered by: <a href="http://smfwiki.net">SMF Wiki ', $wiki_version, '</a> &copy; <a href="http://www.madjoki.com/" target="_blank">Niko Pahajoki</a> 2008-2010
	</div>';
}

?>