<?php
/**********************************************************************************
* WikiFiles.php                                                                   *
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
***********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

function WikiFileView()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc;

	$context['no_last_modified'] = true;

	$filename = $modSettings['wikiAttachmentsDir'] . '/' . $context['current_file']['local_name'];
	$mime_type = $context['current_file']['mime_type'];
	$file_ext = $context['current_file']['extension'];

	// This is logged because it should be there
	if (!file_exists($filename))
		fatal_lang_error('wiki_file_not_found', 'general', $context['current_file']['local_name']);

	$filesize = filesize($filename);

	// This is based on attachments code form SMF
	$do_gzip = !empty($modSettings['enableCompressedOutput']) && $filesize <= 4194304;

	ob_end_clean();
	if ($do_gzip)
		@ob_start('ob_gzhandler');
	else
	{
		ob_start();
		header('Content-Encoding: none');
	}

	// Check if it's not modified
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (strtotime($modified_since) >= filemtime($filename))
		{
			ob_end_clean();

			// Answer the question - no, it hasn't been modified ;).
			header('HTTP/1.1 304 Not Modified');
			exit;
		}
	}

	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filename)) . ' GMT');

	if ($mime_type && (isset($_REQUEST['image']) || !in_array($file_ext, array('jpg', 'gif', 'jpeg', 'bmp', 'png', 'psd', 'tiff', 'iff'))))
		header('Content-Type: ' . $mime_type);
	else
	{
		//header('Content-Type: ' . $context['browser']['is_ie'] || $context['browser']['is_opera'] ? 'application/octetstream' : 'application/octet-stream');
		if (isset($_REQUEST['image']))
			unset($_REQUEST['image']);
	}

	if (!$do_gzip)
		header('Content-Length: ' . $filesize);

	// Try to buy some time...
	@set_time_limit(0);

	if (!$do_gzip)
	{
		// Forcibly end any output buffering going on.
		if (function_exists('ob_get_level'))
		{
			while (@ob_get_level() > 0)
				@ob_end_clean();
		}
		else
		{
			@ob_end_clean();
			@ob_end_clean();
			@ob_end_clean();
		}

		$fp = fopen($filename, 'rb');
		while (!feof($fp))
		{
			if (isset($callback))
				echo $callback(fread($fp, 8192));
			else
				echo fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}
	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (isset($callback) || @readfile($filename) == null)
		echo isset($callback) ? $callback(file_get_contents($filename)) : file_get_contents($filename);

	obExit(false);
}

function WikiFileUpload()
{
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc;

	isAllowedTo('wiki_upload');

	if (isset($_POST['submit_upload']))
	{

	}

	loadTemplate('WikiFiles');
	$context['sub_template'] = 'wiki_file_upload';
}

?>