<?php
// Version: 0.1; WikiAdmin

function template_wiki_admin_main()
{
	global $context, $modSettings, $txt, $wiki_version;

	echo '
	<div class="tborder floatleft" style="width: 69%;">
		<h3 class="catbg headerpadding">', $txt['wiki_latest_news'], '</h3>
		<div id="wiki_news" style="overflow: auto; height: 18ex;" class="windowbg2 smallpadding">
			', $txt['wiki_news_unable_to_connect'], '
		</div>
	</div>
	<div class="tborder floatright" style="width: 30%;">
		<h3 class="catbg headerpadding">', $txt['wiki_version_info'], '</h3>
		<div style="overflow: auto; height: 18ex;" class="windowbg2 smallpadding">
			', $txt['wiki_installed_version'], ': <span id="wiki_installed_version">', $wiki_version, '</span><br />
			', $txt['wiki_latest_version'], ': <span id="wiki_latest_version">???</span>
		</div>
	</div>
	<div style="clear: both"></div>
	<script language="JavaScript" type="text/javascript"><!-- // --><![CDATA[
		function setWikiNews()
		{
			if (typeof(window.wikiNews) == "undefined" || typeof(window.wikiNews.length) == "undefined")
					return;

				var str = "<div style=\"margin: 4px; font-size: 0.85em;\">";

				for (var i = 0; i < window.wikiNews.length; i++)
				{
					str += "\n	<div style=\"padding-bottom: 2px;\"><a href=\"" + window.wikiNews[i].url + "\">" + window.wikiNews[i].subject + "</a> on " + window.wikiNews[i].time + "</div>";
					str += "\n	<div style=\"padding-left: 2ex; margin-bottom: 1.5ex; border-top: 1px dashed;\">"
					str += "\n		" + window.wikiNews[i].message;
					str += "\n	</div>";
				}

				setInnerHTML(document.getElementById("wiki_news"), str + "</div>");
		}

		function setWikiVersion()
		{
			if (typeof(window.wikiCurrentVersion) == "undefined")
				return;

			setInnerHTML(document.getElementById("wiki_latest_version"), window.wikiCurrentVersion);
		}
	// ]]></script>
	<script language="JavaScript" type="text/javascript" src="http://service.smfwiki.net/news.js?v=', urlencode($wiki_version), '" defer="defer"></script>';
}

?>