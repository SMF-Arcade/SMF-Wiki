<?php
/**
 *
 */

/**
 *
 */
class Wiki_Parser_Core
{
	// General
	const TEXT = 1;
	const NEW_LINE = 2;
	const COMMENT = 3;
	const HTML_COMMENT = 4;
	const WARNING = 5;

	const SECTION_HEADER = 11;
	const NEW_PARAGRAPH = 12;
	const END_PARAGRAPH = 13;
	const END_PAGE = 14;
	
	// Parsing rules
	const NO_PARSE = 21;

	// Block level rules (for managing paragraphs)
	const CONTROL_BLOCK_LEVEL_OPEN = 38;
	const CONTROL_BLOCK_LEVEL_CLOSE = 39;
	
	// Rules for WikiElement (such as Wikilinks etc.)
	const ELEMENT = 40;
	/*const ELEMENT_OPEN = 41;
	const ELEMENT_NAME = 42;
	const ELEMENT_PARAM_NAME = 43;
	const ELEMENT_NEW_PARAM = 44;
	const ELEMENT_SEMI_COLON = 45;
	const ELEMENT_CLOSE = 49;*/
	
	// Behaviour Switch
	const BEHAVIOUR_SWITCH = 50;

	//
	const LIST_OPEN = 52;
	const LIST_CLOSE = 53;
	const LIST_ITEM_OPEN = 54;
	const LIST_ITEM_CLOSE = 55;

	// Parser Warnings
	const SEV_NOTICE = 1;
	const SEV_WARNING = 2;
	const SEV_ERROR = 3;
	
	/**
	 * Defines Block level tags.
	 * This is used for managing paragraphs
	 */
	static public $blockTags = array(
		// DIV
		'<div>' => false,
		'</div>' => true,
		// UL
		'<ul>' => false,
		'</ul>' => true,
		// CODE
		'<code>' => false,
		'</code>' => true,
		// Marguee
		'<marquee>' => false,
		'</marquee>' => true,
		// HR
		'<hr />' => true,
		// Quote
		'<blockquote>' => false,
		'</blockquote>' => true,
		'<table>' => false,
		'</table>' => true,
	);
	
	/**
	 *
	 */
	static public $hashTags = array(
		'test' => array(),
	);
	
	/**
	 * Makes html id for section
	 */
	static function html_id($name)
	{
		global $smcFunc;
		
		$name = str_replace(array('%3A', '+', '%'), array(':', '_', '.'), urlencode(un_htmlspecialchars($name)));
		
		while($name[0] == '.')
			$name = substr($name, 1);
		return $name;
	}
	
	/**
	 * Parser content into text for use in parameters etc.
	 */
	static public function toText($content, $single_line = true)
	{
		$return = '';
		
		foreach ($content as $c)
		{
			switch ($c['type'])
			{
				case Wiki_Parser_Core::ELEMENT_SEMI_COLON:
				case Wiki_Parser_Core::CONTROL_BLOCK_LEVEL_OPEN:
				case Wiki_Parser_Core::NO_PARSE:
				case Wiki_Parser_Core::TEXT:
					$return .= $c['content'];
					break;
				case Wiki_Parser_Core::NEW_LINE:
					$return .= $single_line ? ' ' : '<br />';
					break;
				case Wiki_Parser_Core::NEW_PARAGRAPH:
					$return .= $single_line ? ' ' : '<br /><br />';
					break;
				case Wiki_Parser_Core::ELEMENT:
					$return .= $c['content']->getHtml();
					break;
				case Wiki_Parser_Core::WARNING:
					$return .= $c['unparsed'];
					break;
				default:
					die('toText: Unknown part type ' . $c['type']);
					break;
			}
		}
		
		return $return;
	}

	/**
	 * Parser content into text for use in parameters etc.
	 */
	static function getUnparsed($content, $single_line = true)
	{
		$return = '';
		foreach ($content as $c)
			$return .= !empty($c['unparsed'])? $c['unparsed'] : $c['content'];
		return $return;
	}

	/**
	 * Prepares content array for boolean conversion
	 * 
	 * @param array $content
	 * @return array
	 */
	static protected function __boolean_trim($content)
	{
		$return = array();
		foreach ($content as $c)
		{
			switch ($c['type'])
			{
				case Wiki_Parser_Core::ELEMENT_SEMI_COLON:
				case Wiki_Parser_Core::TEXT:
					$c['content'] = trim($c['content']);
					if ($c['content'] !== '')
						$return[] = $c;
					break;
				case Wiki_Parser_Core::NEW_LINE:
				case Wiki_Parser_Core::NEW_PARAGRAPH:
					break;
				case Wiki_Parser_Core::ELEMENT:
					$return[] = $c;
					break;
				case Wiki_Parser_Core::WARNING:
					$return[] = $c;
					break;
				default:
					die('__boolean_trim: Unknown part type ' . $c['type']);
					break;
			}
		}

		return $return;
	}
	
	/**
	 * Convert content array to boolean
	 * 
	 * @param array $content content array to compare
	 * @return boolean Result
	 */
	static function toBoolean($content)
	{
		$content = Wiki_Parser_Core::__boolean_trim($content);
		
		if (count($content) != 1 || ($content[0]['type'] != Wiki_Parser_Core::ELEMENT && $content[0]['type'] != Wiki_Parser_Core::WARNING))
		{
			$result = Wiki_Parser_Core::toText($content);
			return !empty($result);
		}
		elseif ($content[0]['type'] == Wiki_Parser_Core::WARNING)
			return false;
		else
		{
			return $content[0]['content']->toBoolean();
		}
	}
}

?>