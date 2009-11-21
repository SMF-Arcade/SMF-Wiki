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
	global $context, $modSettings, $settings, $txt, $user_info, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Editor.php');

	if (empty($modSettings['wikiAttachmentsDir']) || !is_dir($modSettings['wikiAttachmentsDir']) || !is_writeable($modSettings['wikiAttachmentsDir']))
		fatal_lang_error('wiki_file_not_found', false);

	isAllowedTo('wiki_upload');

	// Submit?
	if (isset($_POST[$context['session_var']]))
	{
		checkSession('post');

		if (empty($_FILES['file']))
			fatal_lang_error('wiki_file_upload_failed');
		elseif ($_FILES['file']['error'] != UPLOAD_ERR_OK)
			fatal_lang_error('wiki_file_upload_failed');
		elseif (!is_uploaded_file($_FILES['file']['tmp_name']))
			fatal_lang_error('wiki_file_upload_failed');

		if (!empty($_REQUEST['file_description_mode']) && isset($_REQUEST['file_description']))
		{
			$_REQUEST['file_description'] = html_to_bbc($_REQUEST['file_description']);
			$_REQUEST['file_description'] = un_htmlspecialchars($_REQUEST['file_description']);
			$_POST['file_description'] = $_REQUEST['file_description'];
		}

		if (htmltrim__recursive(htmlspecialchars__recursive($_POST['file_description'])) == '')
			$_POST['file_description'] = '';
		else
		{
			$_POST['file_description'] = $smcFunc['htmlspecialchars']($_POST['file_description'], ENT_QUOTES);

			preparsecode($_POST['file_description']);
		}

		$isImage = false;

		$tempName = substr(sha1($_FILES['file']['name'] . mt_rand()), 0, 255) . '.tmp';

		// Make sure file doesn't exist
		while (file_exists($modSettings['wikiAttachmentsDir'] . '/' . $tempName))
			$tempName = substr(sha1($_FILES['file']['name'] . mt_rand()), 0, 255) . '.tmp';

		move_uploaded_file($_FILES['file']['tmp_name'], $modSettings['wikiAttachmentsDir'] . '/' . $tempName);

		$imageSize = getimagesize($modSettings['wikiAttachmentsDir'] . '/' . $tempName);

		if ($imageSize)
			$isImage = true;

		$fileName = clean_pagename($_FILES['file']['name']);
		if (!empty($_REQUEST['sub_page']))
			$fileName = $_REQUEST['sub_page'];

		$namespace = !$isImage ? $context['namespace_files'] : $context['namespace_images'];

		$request = $smcFunc['db_query']('', '
			SELECT id_page, id_file
			FROM {wiki_prefix}pages
			WHERE namespace = {string:namespace}
				AND title = {string:name}',
			array(
				'name' => $fileName,
				'namespace' => $namespace['id'],
			)
		);

		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		$id_page = null;
		$id_file_old = null;

		// New file?
		if (!$row)
			$id_page = createPage($fileName, $namespace);
		// Updating existing file?
		elseif ($row && !empty($_REQUEST['sub_page']))
		{
			$id_page = $row['id_page'];
			$id_file_old = $row['id_file'];
		}
		// Error
		else
		{
			unlink($modSettings['wikiAttachmentsDir'] . '/' . $tempName);
			fatal_lang_error('wiki_file_exists');
		}

		$fileext = strtolower(strrpos($fileName, '.') !== false ? substr($fileName, strrpos($fileName, '.') + 1) : '');
		if (strlen($fileext) > 8 || '.' . $fileext == $fileName)
			$fileext = '';

		// Insert file into database
		$smcFunc['db_insert']('',
			'{wiki_prefix}files',
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
				$id_page,
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

		$id_file = $smcFunc['db_insert_id']('{wiki_prefix}files', 'id_file');

		$pageOptions = array();
		$revisionOptions = array(
			'file' => $id_file,
			'body' => $_POST['file_description'],
			'comment' => '',
		);
		$posterOptions = array(
			'id' => $user_info['id'],
		);

		createRevision($id_page, $pageOptions, $revisionOptions, $posterOptions);

		rename($modSettings['wikiAttachmentsDir'] . '/' . $tempName, substr($modSettings['wikiAttachmentsDir'] . '/' . $tempName, 0, -4));

		if ($id_file_old !== null)
			$smcFunc['db_query']('' ,'
				UPDATE {wiki_prefix}files
				SET is_current = {int:not_current}
				WHERE id_file = {int:file}',
				array(
					'not_current' => 0,
					'file' => $id_file_old,
				)
			);

		redirectexit(wiki_get_url(wiki_urlname($fileName, $namespace['id'])));
	}

	$editorOptions = array(
		'id' => 'file_description',
		'value' => '',
		'labels' => array(
			'post_button' => $txt['wiki_upload_button'],
		),
		'width' => '100%',
		'height' => '250px',
		'preview_type' => 0,
	);
	create_control_richedit($editorOptions);

	$context['post_box_name'] = 'file_description';

	loadTemplate('WikiFiles');
	$context['sub_template'] = 'wiki_file_upload';
}

?>