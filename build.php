<?php

// Version of this file
$build_version = '1.0.2';
	
if (!defined('SMF'))
{
	define('SMF', 'SSI');
	$use_default = true;
}

$buildBaseDir = dirname(__FILE__);
$outputDir = $buildBaseDir . '/builds/';

if (!class_exists('xmlArray') && !file_exists($buildBaseDir . '/Class-Package.php'))
	trigger_error(sprintf('Please copy Class-Package.php to %1$s!', $buildBaseDir), E_USER_ERROR);
		
if (isset($use_default))
{
	// Default Settings for commandline
	$commandLineSettings = array(
		'build' => 'DEFAULT',
		'svn' => in_array('--svn', $_SERVER['argv']),
	);
	
	// Revision specified
	if (($rev = array_search('--rev', $_SERVER['argv'])) !== false)
	{
		if (!isset($_SERVER['argv'][$rev + 1]) || !is_numeric(trim($_SERVER['argv'][$rev + 1])))
			trigger_error('Invalid value for --rev argument');
		else
			$commandLineSettings['rev'] = (int) trim($_SERVER['argv'][$rev + 1]);
	}
	
	buildHandler($buildBaseDir, $outputDir, $commandLineSettings);
}

function buildHandler($baseDir, $outputDir, $commandLineSettings)
{
	if (!file_exists($baseDir . '/Build.xml'))
		trigger_error(sprintf('Build.xml not found in %1$s!', $buildBaseDir), E_USER_ERROR);
	if (!file_exists($outputDir) && !is_dir($outputDir) && !mkdir($outputDir))
		trigger_error(sprintf('Unable to create directory %1$s!', $outputDir), E_USER_ERROR);
	
	// We need this from SMF
	if (!class_exists('xmlArray'))
		require_once($baseDir . '/Class-Package.php');
		
	// Where is Build.xml
	$buildFile = $baseDir . '/Build.xml';
	
	$buildInformation = new xmlArray(file($buildFile));
	
	$currentBuild = false;
	
	foreach ($buildInformation->set('/builds/build') as $build)
	{
		$buildName = $build->exists('@name') ? $build->fetch('@name') : 'DEFAULT';
		
		if ($buildName == $commandLineSettings['build'])
		{
			$currentBuild = $build;
			break;
		}
	}
	
	if (!$currentBuild)
		trigger_error(sprintf('Build %1$s not defined in Builds.xml!', $commandLineSettings['build']), E_USER_ERROR);
	
	// Run main builder (done like this to make it easy to make multiple builds)
	return build_main($baseDir, $outputDir, $currentBuild, $commandLineSettings, $buildFile);
}

function build_main($baseDir, $outputDir, xmlArray $currentBuild, $commandLineSettings, $buildFile)
{
	global $buildBaseDir, $buildLog;
	global $sBuildBaseDir, $sBaseDir, $sOutputDir;
	
	$sBuildBaseDir = $buildBaseDir;
	$sBaseDir = $baseDir;

	$buildLog = new BuildLog();
	
	$build_info = array(
		'name' => $currentBuild->fetch('name'),
		'version' => $currentBuild->fetch('version'),
		'version_str' => $currentBuild->fetch('version-str'),
		'version_int' => $currentBuild->fetch('version-int'),
	);
	
	$buildLog->addMessage('Building %s', array($build_info['name']));
	$buildLog->addMessage('Building "%s" from %s/%s', array($currentBuild->fetch('name'), $buildFile, $currentBuild->exists('@name') ? $currentBuild->fetch('@name') : 'DEFAULT'));

	// SVN Build?		
	if ($currentBuild->exists('allow-svn') && $currentBuild->fetch('allow-svn') && $commandLineSettings['svn'])
	{
		if (!isset($commandLineSettings['rev']))
		{
			$svnInfo = svn_wrapper('info', $baseDir, true);
			$commandLineSettings['rev'] = (int) $svnInfo['max_revision'];
			unset($svnInfo);
		}
		
		$build_info['version'] .= ' rev' .$commandLineSettings['rev'];
	}
	
	// Get package info location
	$packageInfo_loc = __parseDirectory($currentBuild->fetch('package-info'));
	
	// Where does files come from?
	$files_base = __parseDirectory($currentBuild->fetch('package-info/@base'));

	// Parse package info
	$packageInfo_content = file_get_contents($packageInfo_loc);
	$packageInfo = new xmlArray($packageInfo_content);
	
	// Package info is required
	$required_files = array(
		array(
			'type' => 'package-info',
			'source' => $packageInfo_loc,
			'filename' => basename($packageInfo_loc),
		),
	);
	
	// Get list of files required to install/upgrade package
	packageRequiredFiles($required_files, $packageInfo, $files_base);
	
	$packageInfo_extra = array();
	
	// Extra files asked by build file
	foreach ($currentBuild->set('file-include') as $file)
		$required_files[] = array(
			'source' => __parseDirectory($file->fetch('@source')),
			'filename' => $file->fetch('.'),
		);
		
	// Translations
	if ($currentBuild->exists('language-info') && $currentBuild->exists('language'))
	{
		foreach ($currentBuild->set('language') as $language)
		{
			$langSourcedir = __parseDirectory($language->fetch('@source'), $build_info, $currentBuild);
			
			$is_utf8 = $language->exists('@utf8') ? $language->fetch('@utf8') == 'true' : false;
				
			foreach (array('local', 'utf8') as $encoding)
			{
				if ($is_utf8 && $encoding != 'utf8' && $language->fetch('@name') != 'english')
					$utf8decode = true;
				else
					$utf8decode = false;
					
				$lang = $language->fetch('@name') . ($encoding == 'utf8' ? '-utf8' : '');
				
				foreach ($currentBuild->set('language-info/*') as $lngItem)
				{
					$itemName = $lngItem->name();
					
					$fName = $itemName == 'modification' ? $lngItem->fetch('.') : $lngItem->fetch('@name');
					
					$fileNameSrc = __langnameReplaces($fName, $language->fetch('@name'), $build_info, $currentBuild);
					$fileName = __langnameReplaces($fName, $lang, $build_info, $currentBuild);
					
					if ($itemName == 'modification')
						$packageInfo_extra[] = array('modification', $fileName, 'format' => $lngItem->fetch('@format'), 'type' => $lngItem->fetch('@type'));
					elseif ($itemName == 'require-file')
						$packageInfo_extra[] = array('require-file', $fileName, 'destination' => $lngItem->fetch('@destination'));
					else
						trigger_error('Language-info is erroreus', E_USER_ERROR);
						
					if ($lang !== 'english')
						$langFileContent = preg_replace('@<file name="([^"]+)">@', '<file name="$1" error="skip">', strtr(file_get_contents($langSourcedir . '/' . $fileNameSrc), array(
							'.' . $language->fetch('@name') . '.php"' => '.' . $lang . '.php"',
							'<version>{version}</version>' => '<version>' . $build_info['version_int'] . '</version>',
						)));
					else
						$langFileContent = strtr(file_get_contents($langSourcedir . '/' . $fileNameSrc), array(
							'.' . $language->fetch('@name') . '.php">' => '.' . $lang . '.php">',
							'<version>{version}</version>' => '<version>' . $build_info['version_int'] . '</version>',
						));
					
					if ($utf8decode)
						$langFileContent = iconv('UTF-8', $language->fetch('@encoding'), $langFileContent);
						
					$required_files[] = array(
						'type' => 'content',
						'filename' => $fileName,
						'content' => $langFileContent,
					);
				}
			}
		}
	}
	
	$outputs = array();

	// Do builds now
	foreach ($currentBuild->set('output') as $output)
	{
		$filename = __filenameReplaces($output->fetch('.'), $build_info, $currentBuild);
		
		$type = strtolower($output->fetch('@type'));
		
		if ($type == 'zip')
		{
			// Extension is required
			$filename .= '.zip';
			
			$outputs[] = $filename;
			
			$buildLog->addMessage('Writing: %1$s to %2$s', array($filename, $outputDir));
				
			$zip = new ZipArchive();
			$zip->open($outputDir . '/' . $filename, ZIPARCHIVE::CREATE);
			
			foreach ($required_files as $file)
			{
				if (empty($file['type']))
				{
					$fileContent = __fileContentReplaces($file['filename'], file_get_contents($file['source'] . '/' . $file['filename']), $build_info, $currentBuild);
					
					$zip->addFromString($file['filename'], $fileContent);
				}
				elseif ($file['type'] == 'content')
				{
					$zip->addFromString($file['filename'], $file['content']);
				}
				elseif ($file['type'] == 'package-info')
				{
					$fileContent = __packageInfoReplace($packageInfo_content, $build_info, $currentBuild, $packageInfo_extra);
					$zip->addFromString($file['filename'], $fileContent);
				}
				else
					die($file['type']);
			}
		
			$zip->close();
		}
	}
	
	$buildLog->save($outputDir . '/' . $filename . '.txt');
	
	unset($buildLog);
	
	return $outputs;
}

// Build Log class
class BuildLog
{
	private $log = array();
	
	function __construct()
	{
		global $build_version;
		
		$this->addMessage('Build log started (Niko\'s Build system %1$s)', array($build_version));
	}
	
	function addMessage($message, $vars = array())
	{
		$this->log[] = date('H:m:s') . ':' . vsprintf($message, $vars);
	}
	
	function save($filename)
	{
		file_put_contents($filename, implode("\r\n", $this->log));
	}
}

// Helper Functions
function packageRequiredFiles(&$files, xmlArray $packageInfo, $file_source)
{
	// Install scripts
	foreach ($packageInfo->set('package-info[0]/*') as $script)
	{	
		if ($script->name() != 'install' && $script->name() != 'upgrade')
			continue;
		
		foreach ($script->set('*') as $action)
		{
			$type = $action->name();

			if ($type == 'modification' || $type == 'readme')
				$files[] = array(
					'source' => $file_source,
					'filename' => $action->fetch('.')
				);

			elseif ($type == 'require-file')
				$files[] = array(
					'source' => $file_source,
					'filename' => $action->fetch('@name')
				);

			elseif ($type == 'require-dir')
				__dirFiles($files, $file_source, $action->fetch('@name'));
			elseif ($type == 'code' || $type == 'database')
				$files[] = array(
					'source' => $file_source,
					'filename' => $action->fetch('.')
				);
		}
	}
	
	return $files;
}

function __dirFiles(&$files, $file_source, $sub_dir)
{
	$list = scandir($file_source . '/' . $sub_dir . '/');
	
	foreach ($list as $f)
	{
		if ($f == '.' || $f == '..')
			continue;
		
		if (is_dir($file_source . '/' . $sub_dir . '/' . $f))
			__dirFiles($files, $file_source . '/' . $sub_dir . '/' . $f);
		else
			$files[] = array(
				'source' => $file_source,
				'filename' => $sub_dir . '/' . $f,
			);
	}
}

// Parses real directory
function __parseDirectory($string)
{
	global $sBuildBaseDir, $sBaseDir;
	
	return strtr($string, array('{BASEDIR}' => $sBaseDir, '{BUILDBASE}' => $sBuildBaseDir));
}

// Replaces placeholders in filename
function __filenameReplaces($string, array $build_info, xmlArray $currentBuild)
{
	return str_replace(' ', '_', strtr($string, array(
		'{NAME}' => $build_info['name'],
		'{VERSION}' => $build_info['version'],
	)));
}

// Replaces placeholders in filename
function __langnameReplaces($string, $language, array $build_info, xmlArray $currentBuild)
{
	return str_replace(' ', '_', strtr($string, array(
		'{LANGUAGE}' => $language,
		'{NAME}' => $build_info['name'],
		'{VERSION}' => $build_info['version'],
	)));
}

// Parses general things
function __generalParse($string, array $build_info, xmlArray $currentBuild)
{
	return strtr($string, array(
		'{NAME}' => $build_info['name'],
		'{VERSION}' => $build_info['version'],
		'{VERSION_INT}' => $build_info['version_int'],
	));	
}

// File content parse
function __fileContentReplaces($filename, $string, array $build_info, xmlArray $currentBuild)
{
	if (!$currentBuild->exists('replaces'))
		return $string;

	$replaces = array();	

	foreach ($currentBuild->set('replaces') as $replace)
	{
		$files = explode(',', $replace->fetch('@filename'));
		
		$doReplace = false;
		
		foreach ($files as $f)
		{
			if ($f == $filename)
			{
				$doReplace = true;
				break;
			}
			elseif (substr($f, 0, 1) == '*')
			{
				$search = substr($f, 1);
				
				if (substr($filename, -strlen($search)) != $search)
					continue;

				$doReplace = true;
				break;
			}
		}
		
		$replaces[$replace->fetch('@from')] = __generalParse($replace->fetch('@to'), $build_info, $currentBuild);
	}
	
	if (empty($replaces))
		return $string;
	
	return strtr($string, $replaces);	
}

function __packageInfoReplace($string, array $build_info, xmlArray $currentBuild, $packageInfo_extra)
{
	$string = strtr($string, array(
		'<version>{version}</version>' => '<version>' . $build_info['version_int'] . '</version>',
	));
	
	$installCode = array();
	$uninstallCode = array();
	$upgradeCode = array();
	
	if (!empty($packageInfo_extra))
	{
		foreach ($packageInfo_extra as $ext)
		{
			if ($ext[0] == 'modification')
			{
				$installCode[] = '$1' . "\t" . '<modification' . (!empty($ext['format']) ? ' format="'. $ext['format']. '"' : '') . (!empty($ext['type']) ? ' type="' . $ext['type'] . '"' : '') . '>' . $ext[1] . '</modification>';
				$uninstallCode[] = '$1' . "\t" . '<modification' . (!empty($ext['format']) ? ' format="'. $ext['format']. '"' : '') . (!empty($ext['type']) ? ' type="' . $ext['type'] . '"' : '') . ' reverse="true">' . $ext[1] . '</modification>';
			}
			elseif ($ext[0] == 'require-file')
			{
				//
				$installCode[] = '$1' . "\t" . '<require-file name="' . $ext[1] . '" destination="' . $ext['destination'] . '" />';
				$uninstallCode[] = '$1' . "\t" . '<remove-file name="' . $ext['destination'] . '/' . $ext[1] . '" />';
			}
		}
	}
	
	$string = preg_replace('@'. "\n" . '(\s+?)(</install>)@', "\n" . implode("\n", $installCode) . "\n" . '$1$2', $string);
	$string = preg_replace('@'. "\n" . '(\s+?)(</uninstall>)@', "\n" . implode("\n", $uninstallCode) . "\n" . '$1$2', $string);
	
	return $string;
}

// Wrapper for SVN
function svn_wrapper($function, $params, $username = '', $password = '')
{
	global $svn_command;
	
	if (!isset($svn_command))
		$svn_command = 'svn';

	static $param_rename = array(
		'Last Changed Author' => 'author',
		'Last Changed Rev' => 'revision',
		'Last Changed Date' => 'date',
		'Revision' => 'max_revision'
	);

	$output = array();
	
	$parse = in_array($function, array('info'));

	if (!in_array($function, array('commit')) || empty($username))
		$command = "$svn_command $function $params";
	else
		$command = "$svn_command $function --username \"$username\" --password \"$password\" $params";

	exec($command, $output);

	if (!$parse)
		return $output;

	else
	{
		$info = array();

		foreach ($output as $line)
		{
			if (strpos($line, ':') === false)
				continue;

			list($param, $value) = explode(':', $line, 2);
			$param = trim($param);

			if (isset($param_rename[$param]))
				$param = $param_rename[$param];

			$info[$param] = trim($value);
		}

		return $info;
	}
}

?>