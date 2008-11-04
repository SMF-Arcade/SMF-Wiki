<?php

/*
	This file is for running wiki in standalone mode, where Wiki is not located inside forum.
	Meant for having Wiki inside site rather than inside forum

	Eexample settings

		INSERT INTO `smf_settings` (`variable` ,`value`)
		VALUES
			('wikiStandalone', '2'),
			('wikiStandaloneUrl', 'http://www.smfarcade.info/wiki')

	wikiStandalone:
		1 - No "SEO" urls
		2 = "SEO" urls

	Example .htaccess rules for "SEO" urls
		RewriteEngine On
		RewriteRule ^wiki/(.*)$ wikistandalone.php?page=$1 [NC,QSA]

	wikiStandaloneUrl:


		Base url for SEO urls.

			eg. http://www.smfarcade.info/wiki

		url to wikistandalone.php for non SEO urls

			eg. http://www.smfarcade.info/wiki.php

*/

// Here you can add SSI settings or any settings for your site
$section = 'wiki';

// Path to SSI or file which includes SSI.php
require_once(dirname(__FILE__) . '/SSI.php');

// DON'T modify anything below unless you are sure what your doing
require_once($sourcedir . '/Wiki.php');

Wiki(true);

$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title']));

obExit(true);

?>