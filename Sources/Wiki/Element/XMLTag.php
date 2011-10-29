<?php
/**
 * 
 *
 * @package SMFWiki
 * @subpackage Element
 * @version 0.3
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 */

/**
 *
 */
abstract class Wiki_Element_XMLTag extends Wiki_Element
{
	/**
	 * @var 
	 */
	protected $target;
	
	/**
	 *
	 * @var string
	 */
	protected $tagName;
	
	/**
	 *
	 * @var array
	 */
	protected $attributes;
	
	/**
	 *
	 * @var string
	 */
	protected $tagContent;
	
	/**
	 *
	 */
	public function __construct($target, $tagName, $attributes, $tagContent)
	{
		$this->target = $target;
		$this->tagName = $tagName;
		$this->attributes = $attributes;
		$this->tagContent = $tagContent;
	}
}

?>