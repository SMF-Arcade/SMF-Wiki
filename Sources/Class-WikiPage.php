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

	public $file = 0;
	
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

	public $raw_content = '';
	
	/**
	 * @param array $namespace
	 * @param string $page
	 */
	function __construct(array $namespace, $page)
	{
		$this->namespace = $namespace;
		$this->page = $page;
		$this->title = get_default_display_title($page, $namespace['id']);
		$this->page_tree = get_page_parents($page, $namespace['id']);
		
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

}

?>