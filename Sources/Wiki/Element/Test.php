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
class Wiki_Element_Test extends Wiki_Element_XMLTag
{
	/**
	 *
	 */
	function getHtml()
	{
		return '<div><pre>' . print_r(array($this->attributes, $this->tagContent, $this->tagName), true) . '</pre></div>';
	}
	
	/**
	 *
	 */
	function is_block_level()
	{
		return true;
	}	
}

?>