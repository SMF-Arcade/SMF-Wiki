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
	private $_namespace;
	private $title;
	private $topic;
	private $is_locked;
	private $is_deleted;
	private $current_revision;
	private $page_tree;
	
	function __construct($namespace, $page_info)
	{
		$this->_namespace = $namespace;
		$this->title = $page_info['title'];
		$this->topic = $page_info['topic'];
		$this->is_locked = $page_info['is_locked'];
		$this->is_deleted = $page_info['is_deleted'];
		$this->current_revision = $page_info['current_revision'];
		$this->page_tree = $page_info['page_tree'];
	}
}

?>