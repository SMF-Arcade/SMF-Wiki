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
abstract class Wiki_Element
{
	/**
	 * Tell if Element is block level (for example <div>)
	 */
	abstract function is_block_level();

	/**
	 * Returns html to display element
	 */
	abstract function getHtml();
}

?>