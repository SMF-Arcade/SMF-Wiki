<?php
// Version: 2.0 Beta 4; Wiki

function template_wiki_above()
{
	global $context, $baseurl, $modSettings, $txt, $user_info;

	echo '
	<div class="floatleft wikileft"><div>
		<h3><a href="#">', $txt['wiki_navigation'], '</a></h3>
		<ul>
			<li><a href="', $baseurl, '/Main_Page">', $txt['wiki_main_page'], '</a></li>
		</ul>
		<h3><a href="', $baseurl, '/Arcade_2.0:Index">SMF Arcade 2.0</a></h3>
		<ul>
			<li><a href="', $baseurl, '/Arcade_2.0:Index">Index</a></li>
			<li><a href="', $baseurl, '/Arcade_2.0:Install">Install</a></li>
		</ul>
		<h3><a href="', $baseurl, '/Arcade_2.5:Index">SMF Arcade 2.5</a></h3>
		<ul>
			<li><a href="', $baseurl, '/Arcade_2.5:Index">Index</a></li>
			<li><a href="', $baseurl, '/Arcade_2.5:Install">Install</a></li>
		</ul>
	</div></div>
	<div class="wikiright">';
}

function template_wiki_below()
{
	global $context, $modSettings, $txt, $user_info;

	echo '
	</div>';
}

?>