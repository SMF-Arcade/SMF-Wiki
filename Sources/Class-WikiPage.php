<?php
/**
 * Contains WikiPage class
 *
 * @package parser
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.2
 */

/**
 * Wiki Page class
 * Basic Usage: $page = new WikiPage($page_info);
 */
class WikiPage
{
	/**
	 * Enter description here ...
	 * @param array $namespace
	 * @param string $page
	 */
	static function getPageInfo(array $namespace, $page)
	{
		global $smcFunc;
		
		$wiki_page = new WikiPage($namespace, $page);
		
		$request = $smcFunc['db_query']('', '
			SELECT id_page, display_title, title, id_revision_current, id_topic, is_locked, is_deleted
			FROM {wiki_prefix}pages
			WHERE title = {string:page}
				AND namespace = {string:namespace}',
			array(
				'page' => $page,
				'namespace' => $namespace['id'],
			)
		);
		
		// Add additional info if page exists
		if ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$wiki_page->exists = true;
			$wiki_page->id = $row['id_page'];

			// Set page as it might have been in different case
			$wiki_page->page = $row['title'];
			$wiki_page->url_name = wiki_get_url_name($wiki_page->page, $wiki_page->namespace['id']);
			
			// Set display title 
			if (!empty($row['display_title']))
				$wiki_page->title = $row['display_title'];
				
			$wiki_page->topic = $row['id_topic'];
			$wiki_page->locked = !empty($row['is_locked']);
			$wiki_page->deleted = !empty($row['is_deleted']);
			
			$wiki_page->current_revision = $row['id_revision_current'];
		}
		$smcFunc['db_free_result']($request);
		
		return $wiki_page;
	}
	
	/**
	 * Enter description here ...
	 * @param string $page
	 */
	static function getSpecialPageInfo($page)
	{
		global $context;
		
		$wiki_page = new WikiPage($context['namespace_special'], $page);

		$wiki_page->specialPage = WikiExtension::getSpecialPage($page);

		if (!$wiki_page->specialPage)
		{
			$wiki_page->exists = false;
		}
		else
		{
			$wiki_page->exists = true;
			$wiki_page->title = $wiki_page->specialPage['title'];
		}

		return $wiki_page;
	}
	
	public $id = 0;
	public $namespace;
	public $page;
	public $title;
	public $url_name;

	public $exists = false;
	public $locked = false;
	public $deleted = false;
	
	public $is_current = true;
	public $revision = 0;
	public $current_revision = 0;
	public $topic = 0;
	
	public $page_tree;

	public $parser;

	/**
	 *
	 */
	public $specialPage;
	
	public $categories = array();
	public $variables = array();

	/**
	 * File if this page contains one
	 */
	public $file;

	/**
	 * @param array $namespace
	 * @param string $page
	 */
	function __construct(array $namespace, $page)
	{
		$this->namespace = $namespace;
		$this->page = $page;
		$this->title = get_default_display_title($page, $namespace['id']);
		$this->page_tree = get_page_parents($page, $namespace);
		
		$this->url_name = wiki_get_url_name($page, $namespace['id']);
	}
	
	/**
	 * Adds category to page. Used by WikiParser class
	 * @param $category
	 */
	function addCategory(WikiPage $category)
	{
		$this->categories[$category->url_name] = array(
			'id' => $category->id,
			'link' => wiki_get_url($category->url_name),
			'namespace' => $category->namespace,
			'page' => $category->page,
			'title' => $category->title,
			'exists' => $category->exists,
		);
	}


	/**
	 * Adds file to this page
	 */
	public function addFile($id_file)
	{
		global $smcFunc;
		
		$request = $smcFunc['db_query']('', '
			SELECT localname, mime_type, file_ext, filesize, timestamp, img_width, img_height
			FROM {wiki_prefix}files
			WHERE id_file = {int:file}',
			array(
				'file' => $id_file,
			)
		);

		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!$row)
			return false;

		$this->file = array(
			'id' => $id_file,
			'name' => $this->url_name,
			'local_name' => $row['localname'],
			'mime_type' => $row['mime_type'],
			'file_ext' => $row['file_ext'],
			'time' => timeformat($row['timestamp']),
			'filesize' => $row['filesize'] / 1024,
			'width' => $row['img_width'],
			'height' => $row['img_height'],
			'view_url' => !empty($row['mime_type']) ? wiki_get_url(array('page' => $this->url_name, 'image')) : null,
			'download_url' => wiki_get_url(array('page' => $this->url_name, 'sa' => 'download')),
			'is_image' => !empty($row['mime_type']),
		);

		return true;
	}

	// Compare
	function compareTo(Wikipage $target)
	{
		return $this->__diff(
			preg_split('@(\[|\]|=| |[\s, ]|<br />|\.|{|})@', ($target->parser->getRawContent()), null, PREG_SPLIT_DELIM_CAPTURE),
			preg_split('@(\[|\]|=| |[\s, ]|<br />|\.|{|})@', ($this->parser->getRawContent()), null, PREG_SPLIT_DELIM_CAPTURE)
		);
	}

	/*
			Paul's Simple Diff Algorithm v 0.1
			(C) Paul Butler 2007 <http://www.paulbutler.org/>
			May be used and distributed under the zlib/libpng license.

			This code is intended for learning purposes; it was written with short
			code taking priority over performance. It could be used in a practical
			application, but there are a few ways it could be optimized.

			Given two arrays, the function diff will return an array of the changes.
			I won't describe the format of the array, but it will be obvious
			if you use print_r() on the result of a diff on some test data.

			htmlDiff is a wrapper for the diff command, it takes two strings and
			returns the differences in HTML. The tags used are <ins> and <del>,
			which can easily be styled with CSS.
	*/
	private function __diff($old, $new)
	{
		$maxlen = 0;

		foreach($old as $oindex => $ovalue)
		{
			$nkeys = array_keys($new, $ovalue);
			foreach($nkeys as $nindex)
			{
				$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
					$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
				if ($matrix[$oindex][$nindex] > $maxlen)
				{
					$maxlen = $matrix[$oindex][$nindex];
					$omax = $oindex + 1 - $maxlen;
					$nmax = $nindex + 1 - $maxlen;
				}
			}
		}

		if ($maxlen == 0)
			return array(
				array('d' => $old, 'i'=> $new)
			);

		return array_merge(
			$this->__diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
			array_slice($new, $nmax, $maxlen),
			$this->__diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen))
		);
	}
}

?>