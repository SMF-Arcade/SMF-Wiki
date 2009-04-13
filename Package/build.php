<?php
/**********************************************************************************
* build.php                                                                       *
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

// Build info
$build_info = array(
	'branch' => 'trunk',
	'version' => '0.1',
	'version_str' => '0.1',
	'build_replaces' => 'build_replaces_wiki01',
	'extra_files' => array(
		'changelog.txt',
		'wikistandalone.php',
		'modification.xsl' => 'Package/modification.xsl',
		'package-info.xsl' => 'Package/package-info.xsl',
		'extra/Themes/default/languages/Modifications.english.php' => 'Themes/default/languages/Modifications.english.php',
	),
);

if (!function_exists('build_replaces_wiki01'))
{
	function build_replaces_wiki01(&$content, $filename, $rev, $svnInfo)
	{
		global $build_info;

		if (in_array($filename, array('readme.txt', 'install.xml',  'package-info.xml')))
		{
			$content = strtr($content, array(
				'{version}' => $rev ? $build_info['version_str'] . ' rev' . $rev : $build_info['version_str']
			));
		}
		elseif ($rev && in_array($filename, array('Sources/Wiki.php', 'Sources/WikiDatabase.php')))
		{
			$content = strtr($content, array(
				'$wiki_version = \'' . $build_info['version_str'] . '\'' => '$wiki_version = \'' . $build_info['version_str'] . ' rev' . $rev . '\'',
			));
		}
	}
}

?>