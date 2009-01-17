<?php
/**********************************************************************************
* wikistandalone.php                                                              *
***********************************************************************************
* SMF Wiki                                                                        *
* =============================================================================== *
* Software Version:           SMF Wiki 0.1                                        *
* Software by:                Niko Pahajoki (http://www.madjoki.com)              *
* Copyright 2008-2009 by:     Niko Pahajoki (http://www.madjoki.com)              *
* Support, News, Updates at:  http://www.smfarcade.info                           *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

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
$ssi_on_error_method = true;

// DON'T modify anything below unless you are sure what your doing
require_once($sourcedir . '/Wiki.php');

Wiki(true);

$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title']));

obExit(true);

?>