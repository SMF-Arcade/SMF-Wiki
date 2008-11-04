<?php
// Version: 0.1; Wiki

function template_wiki_above()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	<div class="floatleft wikileft"><div>
		<h3><a href="#">', $txt['wiki_navigation'], '</a></h3>
		<ul>
			<li><a href="', wiki_get_url(array('page' => 'Main_Page')), '">', $txt['wiki_main_page'], '</a></li>
		</ul>
		<h3><a href="', wiki_get_url(array('page' => 'Arcade_2.0:Index')),'">SMF Arcade 2.0</a></h3>
		<ul>
			<li><a href="', wiki_get_url(array('page' => 'Arcade_2.0:Index')),'">Index</a></li>
			<li><a href="', wiki_get_url(array('page' => 'Arcade_2.0:Install')),'">Install</a></li>
		</ul>
		<h3><a href="', wiki_get_url(array('page' => 'Arcade_2.5:Index')),'">SMF Arcade 2.5</a></h3>
		<ul>
			<li><a href="', wiki_get_url(array('page' => 'Arcade_2.5:Index')),'">Index</a></li>
			<li><a href="', wiki_get_url(array('page' => 'Arcade_2.5:Install')),'">Install</a></li>
		</ul>
	</div></div>
	<div class="wikiright">';
}

function template_wiki_below()
{
	global $context, $modSettings, $txt, $user_info, $wiki_version;

	echo '
	</div>
	<div id="project_bottom" class="smalltext" style="text-align: center; clear: both;">
		Powered by: SMF Wiki ', $wiki_version, '</a> &copy; <a href="http://www.madjoki.com/" target="_blank">Niko Pahajoki</a> 2008
	</div>';
}

?>