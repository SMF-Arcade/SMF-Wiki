<?php

// Build info
$build_info = array(
	'branch' => 'trunk',
	'version' => '0.1',
	'version_str' => '0.1',
	'build_replaces' => 'build_replaces_wiki01',
	'extra_files' => array(
		'changelog.txt',
		'modification.xsl' => 'Package/modification.xsl',
		'package-info.xsl' => 'Package/package-info.xsl',
		'Themes/default/languages/Modifications.english.php',
	),
);

if (!function_exists('build_replaces_project01'))
{
	function build_replaces_project01(&$content, $filename, $rev, $svnInfo)
	{
		global $build_info;

		if ($rev && ($filename == 'Sources/Project.php' || $filename == 'Sources/ProjectDatabase.php'))
			$content = str_replace('$project_version = \'0.2\';', '$project_version = \'0.2 rev' . $rev . '\';', $content);
		elseif (in_array($filename, array('readme.txt', 'install.xml',  'package-info.xml')))
		{
			$content = strtr($content, array(
				'{version}' => $rev ? $build_info['version_str'] . ' rev' . $rev : $build_info['version_str']
			));
		}
	}
}

?>