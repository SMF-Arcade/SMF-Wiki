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

	if (empty($modSettings['wikiAttachmentsDir']) || !is_dir($modSettings['wikiAttachmentsDir']) || !is_writeable($modSettings['wikiAttachmentsDir']))
		fatal_lang_error('wiki_file_not_found', false);

	isAllowedTo('wiki_upload');

	if (isset($_POST['submit_upload']))
	{
		if (empty($_FILES['file']))
			fatal_lang_error('wiki_file_upload_failed');
		elseif ($_FILES['file']['error'] != UPLOAD_ERR_OK)
			fatal_lang_error('wiki_file_upload_failed');
		elseif (!is_uploaded_file($_FILES['file']['tmp_name']))
			fatal_lang_error('wiki_file_upload_failed');

		$isImage = false;

		$tempName = substr(sha1($_FILES['file']['name'] . mt_rand()), 0, 255) . '.tmp';

		move_uploaded_file($_FILES['file']['tmp_name'], $modSettings['wikiAttachmentsDir'] . '/' . $tempName);

		$imageSize = getimagesize($modSettings['wikiAttachmentsDir'] . '/' . $tempName);

		if ($imageSize)
			$isImage = true;

		$fileName = clean_pagename($_FILES['file']['name']);

		$namespace = !$isImage ? $context['namespace_files'] : $context['namespace_images'];

		$request = $smcFunc['db_query']('', '
			SELECT id_file
			FROM {db_prefix}wiki_pages
			WHERE namespace = {string:namespace}
				AND title = {string:name}',
			array(
				'name' => $fileName,
				'namespace' => $namespace['id'],
			)
		);

		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if ($row)
		{
			unlink($modSettings['wikiAttachmentsDir'] . '/' . $tempName);
			fatal_lang_error('wiki_file_exists');
		}

		$fileext = strtolower(strrpos($fileName, '.') !== false ? substr($fileName, strrpos($fileName, '.') + 1) : '');
		if (strlen($fileext) > 8 || '.' . $fileext == $fileName)
			$fileext = '';

		$smcFunc['db_insert']('insert',
			'{db_prefix}wiki_pages',
			array(
				'title' => 'string-255',
				'namespace' => 'string-255',
			),
			array(
				$fileName,
				$namespace['id'],
			),
			array('id_page')
		);

		$context['page_info']['id'] = $smcFunc['db_insert_id']('{db_prefix}wiki_pages', 'id_article');

		$smcFunc['db_insert']('', '{db_prefix}wiki_files',
			array(
				'id_page' => 'int',
				'localname' => 'string-255',
				'mime_type' => 'string-255',
				'file_ext' => 'string-10',
				'is_current' => 'int',
				'id_member' => 'int',
				'timestamp' => 'int',
				'filesize' => 'int',
				'img_width' => 'int',
				'img_height' => 'int',
			),
			array(
				$context['page_info']['id'],
				substr($tempName, 0, -4),
				$isImage ? $imageSize['mime'] : '',
				$fileext,
				1,
				$user_info['id'],
				time(),
				$_FILES['file']['size'],
				$isImage ? $imageSize[0] : 0,
				$isImage ? $imageSize[1] : 0,
			),
			array('id_file')
		);

		$id_file = $smcFunc['db_insert_id']('{db_prefix}wiki_pages', 'id_article');

		$smcFunc['db_insert']('insert',
			'{db_prefix}wiki_content',
			array(
				'id_page' => 'int',
				'id_author' => 'int',
				'timestamp' => 'int',
				'content' => 'string',
				'comment' => 'string-255',
			),
			array(
				$context['page_info']['id'],
				$user_info['id'],
				time(),
				'',
				'',
			),
			array('id_revision')
		);

		$id_revision = $smcFunc['db_insert_id']('{db_prefix}articles_content', 'id_revision');

		$smcFunc['db_query']('' ,'
			UPDATE {db_prefix}wiki_pages
			SET
				id_revision_current = {int:revision},
				id_file = {int:file}
			WHERE id_page = {int:page}',
			array(
				'page' => $context['page_info']['id'],
				'revision' => $id_revision,
				'file' => $id_file,
			)
		);

		rename($modSettings['wikiAttachmentsDir'] . '/' . $tempName, substr($modSettings['wikiAttachmentsDir'] . '/' . $tempName, 0, -4));

		redirectexit(wiki_get_url(wiki_urlname($fileName, $namespace['id'])));
	}

	loadTemplate('WikiFiles');
	$context['sub_template'] = 'wiki_file_upload';
}

?>