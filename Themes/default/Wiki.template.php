<?php
/**
 * 
 *
 * @package SMFWiki
 * @version 0.3
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 */

function wiki_render(array $content)
{
	global $txt;
	
	switch ($content['type'])
	{
		case Wiki_Parser_Core::ELEMENT:
			echo $content['content']->getHtml();
			break;
		case Wiki_Parser_Core::WARNING:
			echo '<span class="wiki_warning" title="' . $content['unparsed'] . '">' . vsprintf($txt['parser_' . $content['content']], $content['additional']) . '</span>';
			break;
		default:
			echo $content['content'];
			break;
	}
}

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

		if (!empty($group['wiki_title']))
		{
			foreach ($group['wiki_title']['wiki'] as $item)
				wiki_render($item);
		}
		elseif (!empty($group['title']) && !empty($group['url']))
			echo '
				<a href="', $group['url'], '">', $group['title'], '</a>';
		elseif (!empty($group['title']))
			echo '
				', $group['title'];

		if ($is_first)
		{
			echo '
			<span class="floatright"><a href="', wiki_get_url('SMFWiki:Sidebar'), '">', $txt['edit'], '</a></span>';

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
				if (!empty($item['wiki']))
				{
					echo '<li>';
					
					foreach ($item['wiki'] as $item)
						wiki_render($item);
					
					echo '</li>';
				}
				elseif (!empty($item['title']))
				{
					if (!empty($item['url']))
						echo '
					<li><a href="', $item['url'], '"', $item['selected'] ? ' class="current_page"' : '', '>', $item['title'], '</a></li>';
					else
						echo '
					<li>', $item['title'], '</li>';
				}
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
		Powered by: <a href="http://smfwiki.net">SMF Wiki ', $wiki_version, '</a> &copy; <a href="http://www.madjoki.com/" target="_blank">Niko Pahajoki</a> 2008-2011
	</div>';
}

?>