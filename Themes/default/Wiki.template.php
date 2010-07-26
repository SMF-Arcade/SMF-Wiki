<?php
// Version: 0.1; Wiki

function template_wiki_above()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<div class="floatleft wikileft"><div>';

	foreach ($context['wiki_navigation'] as $group)
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg', $group['selected'] ? ' selected' : '', '">';

		if (!empty($group['url']))
			echo '
				<a href="', $group['url'], '">', $group['title'], '</a>';
		else
			echo '
				', $group['title'];

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
}

function template_wiki_below()
{
	global $context, $modSettings, $txt, $user_info, $wiki_version;

	echo '
	</div>
	<div id="project_bottom" class="smalltext" style="text-align: center; clear: both;">
		Powered by: <a href="http://smfwiki.net">SMF Wiki ', $wiki_version, '</a> &copy; <a href="http://www.madjoki.com/" target="_blank">Niko Pahajoki</a> 2008-2009
	</div>';
}

?>