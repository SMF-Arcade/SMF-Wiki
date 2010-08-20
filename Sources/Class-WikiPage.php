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
	public $id = 0;
	public $namespace;
	public $page;
	
	public $title;
	
	public $url_name;
	
	public $exists = false;
	public $locked = false;
	public $deleted = false;
	
	public $current_revision = 0;
	public $topic = 0;
	
	public $page_tree;
	
	function __construct($namespace, $page)
	{
		$this->namespace = $namespace;
		$this->page = $page;
		$this->title = get_default_display_title($page, $namespace['id']);
		$this->page_tree = get_page_parents($page, $namespace['id']);
		
		$this->url_name = wiki_get_url_name($page, $namespace);
		
	
		/*
		$this->topic = $page_info['topic'];
		$this->is_locked = $page_info['is_locked'];
		$this->is_deleted = $page_info['is_deleted'];
		$this->current_revision = $page_info['current_revision'];
		$this->page_tree = $page_info['page_tree'];*/
	}
	
	static function getPageInfo($namespace, $page)
	{
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
		
		if ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$wiki_page->id = $row['id_page'];
			
			// Set display title 
			if (!empty($row['display_title']))
				$wiki_page->title = $row['display_title'];
				
		}
		$smcFunc['db_free_result']($request);
		
		return $wiki_page;
		
		
		$page_tree = array();
		
		
		return array(
			'data' => array(
				'id' => ,
				'title' => !empty($row['display_title']) ? $row['display_title'] : get_default_display_title($row['title'], $namespace['id']),
				'name' => wiki_urlname($row['title'], $namespace['id']),
				'topic' => $row['id_topic'],
				'is_locked' => !empty($row['is_locked']),
				'is_deleted' => !empty($row['is_deleted']),
				'current_revision' => $row['id_revision_current'],
				'page_tree' => $page_tree,
			),
			'expires' => time() + 3600,
			'refresh_eval' => 'return isset($_REQUEST[\'sa\']) && $_REQUEST[\'sa\'] == \'purge\';',
		);
	}
	
	static function getSpecialPageInfo($page)
	{
		global $context;
		
		$wiki_page = new WikiPage($namespace, $page);
		
		die('not implemnted');
	}
}

?>