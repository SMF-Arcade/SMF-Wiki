<?php
/**
 * Contains classes related to parsing of wiki page
 *
 * @package parser
 * @version 0.2
 * @license http://download.smfwiki.net/license.php SMF Wiki license
 * @since 0.2
 */

/**
 * Class that parsers wiki page
 * Basic Usage: $parser = new WikiParser($page); $parser->parse($my_content);
 */
class WikiParser
{
	// General
	const TEXT = 1;
	const NEW_LINE = 2;
	const NEW_PARAGRAPH = 3;
	const SECTION_HEADER = 4;
	
	// Parsing rules
	const NO_PARSE = 21;

	// Block level rules (for managing paragraphs)
	const BLOCK_LEVEL_OPEN = 38;
	const BLOCK_LEVEL_CLOSE = 39;
	
	// Rules for WikiElement (such as Wikilinks etc.)
	const ELEMENT = 40;
	const ELEMENT_OPEN = 41;
	const ELEMENT_NAME = 42;
	const ELEMENT_NEW_PARAM = 43;
	const ELEMENT_CLOSE = 49;
	
	// Behaviour Switch
	const BEHAVIOUR_SWITCH = 50;
	
	// XML style tag
	const TAG = 51;
	
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
	 *
	 */
	static public $xmlTags = array(
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
	static function toText($content, $single_line = true)
	{
		$return = '';
		foreach ($content as $c)
		{
			switch ($c['type'])
			{
				case WikiParser::TEXT:
					$return .= $c['content'];
					break;
				case WikiParser::NEW_LINE:
					$return .= $single_line ? ' ' : '<br />';
					break;
				case WikiParser::NEW_PARAGRAPH:
					$return .= $single_line ? ' ' : '<br /><br />';
					break;
				case WikiParser::ELEMENT:
					$return .= $c['content']->toText();
					break;
				default:
					die('Unknown part type ' . $c['type']);
					break;
			}
		}
		
		return $return;
	}
		
	/**
	 * Page variable containing WikiPage class.
	 */
	public $page;
	
	/**
	 *
	 */
	private $parse_bbc;
	
	/**
	 *
	 */
	private $content;
	
	/**
	 *
	 */
	private $tableOfContents;
	
	private $_htmlIDs = array();
	
	// Lines
	private $lineStart = null;
	private $linePointers = array();
	
	// Errors
	private $errors;
	private $_maxSeverity;
	
	/**
	 *
	 */
	function __construct(WikiPage $page, $parse_bbc = true)
	{
		$this->page = $page;
		$this->parse_bbc = $parse_bbc;
	}
	
	/**
	 * Parser page and returns results
	 */
	public function parse($text)
	{
		$this->content = array();
		
		$this->__parse($this, $text, false);
		
		return $this->content;
	}
	
	/**
	 * Adds content to this page
	 */
	public function throwContent($type, $content, $unparsed = '', $additonal = array())
	{
		$i = count($this->content);
		
		/*if (!is_int($type))
		{
			throw new Exception('Invalid type given for throwContent()', EXPECTION_INVALID_TYPE);
		}*/
		
		// "Line" starts from this part
		if ($this->lineStart == null)
			$this->lineStart = $i;
		
		if ($i > 0 && $type == WikiParser::TEXT && $this->content[$i - 1]['type'] == WikiParser::TEXT && empty($this->content[$i - 1]['additional']) && empty($additonal))
		{
			$this->content[$i - 1]['content'] .= $content;
			
			// Does this part have "unparsed" content?
			if (!empty($this->content[$i - 1]['unparsed']))
				$this->content[$i - 1]['unparsed'] .= empty($unparsed) ? $content : $unparsed;
			// Copy parsed as unparsed if we have but there is none. Done to save memory!
			elseif (empty($this->content[$i - 1]['unparsed']) && !empty($unparsed))
				$this->content[$i - 1]['unparsed'] = $this->content[$i - 1]['content'] . $unparsed;
				
			return;
		}
		
		if ($type == WikiParser::SECTION_HEADER)
		{
			$this->tableOfContents[] = array(
				'id' => $html_id,
				'level' => $additonal['level'],
				'title' => $content,
				'subtoc' => array(),
			);
		}
		
		if ($type == WikiParser::NEW_LINE || $type == WikiParser::NEW_PARAGRAPH || $type == WikiParser::BLOCK_LEVEL_OPEN || $type == WikiParser::BLOCK_LEVEL_CLOSE)
		{
			if (!empty($this->lineStart))
			{
				$this->linePointers[] = array($this->lineStart, $i - 1);
				$this->lineStart = null;
			}
		}
		
		$this->content[$i] = array(
			'type' => $type,
			'content' => $content,
			'unparsed' => $unparsed,
			'additional' => $additonal,
		);
	}
	
	/**
	 *
	 */
	public function throwWarning($severity = SEV_NOTICE, $type)
	{
		$this->errors[] = array(
			'severity' => $severity,
			'line' => count($this->linePointers) + 1,
			'type' => $type,
		);
	}
	
	/**
	 * Main parser function
	 */
	private function __parse(WikiParser $target, $text, $is_template = false)
	{
		global $context;
		
		$text = str_replace(
			array(
				'&lt;includeonly&gt;', '&lt;/includeonly&gt;',
				'&lt;noinclude&gt;', '&lt;/noinclude&gt;',
				'&lt;nowiki&gt;', '&lt;/nowiki&gt;',
				'[nobbc]', '[/nobbc]',
				'[code' , '[/code]',
				'[php]' , '[/php]',
			),
			array(
				'<includeonly>', '</includeonly>',
				'<noinclude>', '</noinclude>',
				'<nowiki>[nobbc]', '[/nobbc]</nowiki>',
				'<nowiki>[nobbc]', '[/nobbc]</nowiki>',
				'<nowiki>[code', '[/code]</nowiki>',
				'<nowiki>[php]', '[/php]</nowiki>',
			),
			$text
		);
		
		// Parse bbc if asked to
		if ($this->parse_bbc)
			$text = parse_bbc($text);
			
		$text = str_replace(array("\r\n", "\r", '<br />', '<br>', '<br/>'), "\n", $text);

		$searchBase = "<[{#\n";

		$textLen = strlen($text);

		$blockLevelNesting = 0;

		$can_open_paragraph = true;
		$is_paragraph = true;
		
		$stack = array();
		
		$i = 0;
		while ($i <= $textLen)
		{
			$charType = '';
			$search = $searchBase;
			$closeTag = '';

			if ($target instanceof WikiElement_Parser)
			{
				$search .= $target->rule['close'] . (empty($target->rule['no_param']) ? '|' : '') . ($target->rule['close'] == '}' ? ':' : '');
				$closeTag = $target->rule['close'];
			}
			else
			{
				$search .= '&=_';
			}

			// Skip to next might be special tag
			$skip = strcspn($text, $search, $i);

			// Normal text line
			if ($skip > 0)
			{
				$target->throwContent(WikiParser::TEXT, substr($text, $i, $skip));
				$i += $skip;
			}
			
			// nowiki tag
			if ($this->parse_bbc && substr($text, $i, 8) == '<nowiki>')
			{
				$i += 8;
				
				$endPos = strpos($text, '</nowiki>', $i);
	
				if ($endPos > 0)
				{
					$target->throwContent(WikiParser::NO_PARSE, substr($text, $i, $endPos - $i));
					$i = $endPos + 9;
				}
				else
					$target->throwContent(WikiParser::TEXT, '&lt;nowiki&gt;');
				
				continue;
			}
			// Skip <includeonly> if this is not template
			elseif (!$is_template && substr($text, $i, 13) == '<includeonly>')
			{
				$i += 13;
				
				$endPos = strpos($text, '</includeonly>', $i);
	
				if ($endPos !== false)
					$i = $endPos + 14;
					
				continue;
			}
			elseif ($is_template && substr($text, $i, 13) == '<includeonly>')
			{
				$i += 13;
				
				continue;
			}
			elseif ($is_template && substr($text, $i, 14) == '</includeonly>')
			{
				$i += 14;
				
				continue;
			}
			// Skip <noinclude> if this is template
			elseif ($is_template && substr($text, $i, 11) == '<noinclude>')
			{
				$i += 11;
				
				$endPos = strpos($text, '</noinclude>', $i);
	
				if ($endPos !== false)
					$i = $endPos + 12;
					
				continue;
			}
			elseif (!$is_template && substr($text, $i, 11) == '<noinclude>')
			{
				$i += 11;
						    
				continue;
			}
			elseif (!$is_template && substr($text, $i, 12) == '</noinclude>')
			{
				$i += 12;
				
				continue;
			}
			
			if ($i >= $textLen)
				break;

			$curChar = isset($text[$i]) ? $text[$i] : "\n";

			// Close char?
			if ($curChar == $closeTag)
			{
				$maxLen = $target->len;
				$len = strspn($text, $curChar, $i, $maxLen);
					
				$rule = $target->rule;

				if ($len > $rule['max'])
					$matchLen = $rule['max'];
				else
				{
					$matchLen = $len;

					while ($matchLen > 0 && !isset($target->rule['names'][$matchLen]))
						$matchLen--;
				}

				if ($matchLen <= 0)
				{
					$target->throwContent(WikiParser::TEXT, str_repeat($curChar, $len));
					$i += $len;
					continue;
				}
				
				// Tell element that it was closed
				$target->throwContent(WikiParser::ELEMENT_CLOSE, '', str_repeat($curChar, $len));
				$element = $target;
				
				// There's still opening tags left to search end for
				if ($matchLen < $element->len)
				{
					$open = $element->len - $matchLen;
					$element->modifyLen($matchLen);
					
					// Nested tag?
					if ($open >= $element->rule['min'])
					{
						$target = new WikiElement_Parser($this, $curChar, $open);
						$target->throwContent(WikiParser::ELEMENT_OPEN, str_repeat($element->char, $open));
					}
					// or just unnecassary character?
					else
					{
						$target = array_pop($stack);
						$target->throwContent(WikiParser::TEXT, str_repeat($element->char, $open));
					}
				}
				else
					$target = array_pop($stack);
				
				// Tell elment that it's really complete and let it finalize.
				$elm = $element->finalize();
				
				var_dump($elm);die();
				
				// Add element to page
				$target->throwContent(WikiParser::ELEMENT, $elm, $element->getUnparsed());
				
				// Not sure if necassary but let's do it anyway.
				unset($element);
				
				$i += $matchLen;
			}
			// Start character for WikiElement
			elseif ($this->parse_bbc && isset(WikiElement_Parser::$rules[$curChar]))
			{
				$rule = WikiElement_Parser::$rules[$curChar];
				
				$len = strspn($text, $curChar, $i);

				if ($len >= $rule['min'])
				{
					// Hash tag is special case
					if (!empty($target->rule['has_name']))
					{
						$nameLen = strcspn($text, ' ', $i);
						
						if (strpos($text, " ", $i) !== false && strpos($text, "\n", $i) !== false)
						{
							$item_name = strtolower(substr($text, $i + 1, $nameLen - 1));
							
							// If no such has tag exists 
							if (!isset(WikiParser::$hashTags[$item_name]))
							{
								$target->throwContent(WikiParser::TEXT, str_repeat($curChar, $len));							
							}
							else
							{
								$stack[] = $target;
								
								$target = new WikiElement_Parser($this, $curChar, $len);
								$target->throwContent(WikiParser::ELEMENT_NAME, $item_name);

								$i += $nameLen;
							}
						}
						else
						{
							$target->throwContent(WikiParser::TEXT, str_repeat($curChar, $len));						
						}
					}
					else
					{
						$stack[] = $target;
						$target = new WikiElement_Parser($this, $curChar, $len);
					}
				}
				else
				{
					$target->throwContent(WikiParser::TEXT, str_repeat($curChar, $len));
				}

				$i += $len;
			}
			// Parameter delimiter
			elseif ($curChar == '|')
				$target->throwContent(WikiParser::ELEMENT_NEW_PARAM, '', '|');
			// Function delimiter / variable value delimeter
			elseif ($curChar == ':')
				$target->throwContent(WikiParser::ELEMENT_NEW_PARAM, '', ':');
			// New Section 
			elseif (($i == 0 || $text[$i - 1] == "\n") && $curChar == '=')
			{
				$len = strcspn($text, "\n", $i);
			
				if ($len !== false)
					$header = substr($text, $i, $len);
				else
					$header = substr($text, $i);

				$c = strspn($header, '=');
				$c2 = strspn(strrev($header), '=');

				if ($c == $c2)
				{
					$target->throwContent(WikiParser::SECTION_HEADER, trim(substr($header, $c, -$c2)), $header, array('level' => strlen($c)));
					$i += $len;
				}
				else
				{
					$target->throwContent(WikiParser::TEXT, '=');
					$i += 1;
				}
				
				continue;
			}
			// New paragraph (2 * new line)
			elseif ($this->parse_bbc && $can_open_paragraph && $curChar == "\n" && $text[$i + 1] == "\n")
			{
				$target->throwContent(WikiParser::NEW_PARAGRAPH, "\n\n");

				$i += 2;
				
				continue;
			}
			elseif ($curChar == "\n")
			{
				$target->throwContent(WikiParser::NEW_LINE, "\n");
				$i++;
				
				continue;
			}
			// Start or end of tag 
			elseif ($this->parse_bbc && $curChar == '<')
			{
				$tagnameLen = strcspn($text, ' >', $i + 1) + 1;
				$tagLen = strcspn($text, ' >', $i + 1);
				$tag = '<' . substr($text, $i + 1, $tagnameLen) . '>';
				
				if (isset(WikiParser::$blockTags[$tag]))
				{
					if (WikiParser::$blockTags[$tag] === false)
					{
						$can_open_paragraph = false;
						$blockLevelNesting++;
						
						$target->throwContent(WikiParser::BLOCK_LEVEL_OPEN, substr($text, $i, $tagLen));
					}
					elseif (!$can_open_paragraph)
					{
						$blockLevelNesting--;
						
						$can_open_paragraph = $blockLevelNesting == 0;
						
						$target->throwContent(WikiParser::BLOCK_LEVEL_CLOSE, substr($text, $i, $tagLen));
					}
					else
						$target->throwContent(WikiParser::TEXT, substr($text, $i, $tagLen));

					
					$i += $tagLen;
					
					continue;
				}
				else
				{
					$target->throwContent(WikiParser::TEXT, htmlspecialchars($curChar));
					$i++;
				}
			}
			// Start or end of tag
			elseif ($this->parse_bbc && substr($text, $i, 4) == '&lt;')
			{
				$endPos = strpos($text, '&gt;', $i + 4);
				
				$tagCode = substr($text, $i + 4, $endPos - $i - 4);
				$tagLen = strcspn($tagCode, ' ');
				$tagName = strtolower(substr($tagCode, 0, $tagLen));
				
				if (isset(WikiParser::$xmlTags[$tagName]))
				{
					// Last > tag
					$endPos += 4;
					
					$attributes = array();
					
					$tagCode = un_htmlspecialchars(trim(substr($tagCode, $tagLen)));
					$tagContent = '';
										
					while (!empty($tagCode))
					{
						$atribLen = strcspn($tagCode, '=');
						
						$atrib = substr($tagCode, 0, $atribLen);
						
						// Find positions for euals and quotes
						$eqPos = strpos($tagCode, '=', $atribLen);
						$eqPos2 = strpos($tagCode, '=', $eqPos + 1);
						$quotePos = strpos($tagCode, '"', $atribLen);
						$quote2Pos = strpos($tagCode, '"', $quotePos + 1);
						
						if (strpos($tagCode, '"') !== false && $eqPos < $quotePos && ($eqPos2 < $quotePos && $quote2Pos < $eqPos2))
						{
							$valueStart = strpos($tagCode, '"');								
							$valueEnd = strpos($tagCode, '"', $valueStart + 1);
							$valueLen = $valueEnd - $valueStart - 1;
							
							$attributes[$atrib] = substr($tagCode, $valueStart + 1, $valueLen);
											
							$tagCode = trim(substr($tagCode, $valueEnd + 1));						
						}
						// Non quoted value
						// TODO: Add parser warning
						else
						{
							$valueStart = strpos($tagCode, '=');								
							$valueEnd = strpos($tagCode, ' ', $valueStart + 1);
							
							if ($valueEnd === false)
								$valueEnd = strlen($tagCode);
							
							$valueLen = $valueEnd - $valueStart - 1;
							
							$attributes[$atrib] = trim(substr($tagCode, $valueStart + 1, $valueLen));
											
							$tagCode = trim(substr($tagCode, $valueEnd + 1));	
						}
					}
					
					$endTag = '&lt;/' . $tagName . '&gt;';
					$endTagPos = strpos($text, $endTag, $i);
					
					if ($endTagPos !== false)
					{
						$tagContent = substr($text, $i + 2 + strlen($tagName));
						
						$endPos = $endTagPos + strlen($endTag);
					}
					
					$target->throwContent(WikiParser::TAG, new WikiTag($tagName, $attributes, $tagContent), substr($text, $i, $endPos - $i));
						
					$i = $endPos;
					
					continue;
				}
				else
				{
					$target->throwContent(WikiParser::TEXT, '&lt;');
					$i += 4;
				}
			}
			// Behaviour switch
			elseif ($this->parse_bbc && $curChar == '_' && $text[$i + 1] == '_')
			{
				// Find next space
				$bLen = strcspn($text, " \n", $i + 2);
				$bSwitch = substr($text, $i + 2, $bLen);
				
				if (substr($bSwitch, -2) == '__')
				{
					$target->throwContent(WikiParser::BEHAVIOUR_SWITCH, substr($bSwitch, 0, -2), substr($text, $i, $bLen + 2));
					$i += $bLen + 2;
					
					continue;
				}
			}
			else
			{
				$target->throwContent(WikiParser::TEXT, $curChar);
				$i++;
			}
		}
		
		// Empty stack
		while (!empty($stack))
		{
			$element = $target;
			$target = array_pop($stack);
			
			// Ask element to throw content to previous element
			$element->throwContentTo($target);
			
			unset($element);
		}
		
		/*while (!empty($this->_tocStack))
		{
			$toc = array_pop($this->_tocStack);
			$toc2 = array_pop($this->_tocStack);
			
			$html_id = WikiParser::html_id($content);
			
			$i = 1;
			
			// Make sure html_id is unique in page context
			while (in_array($html_id, $this->_htmlIDs))
				$html_id = WikiParser::html_id($content . '_'. $i++);
			$this->_htmlIDs[] = $html_id;
			
			if ($toc2['level'] < $toc['level'])
			{
				$toc2['subtoc'] = $toc;
			}
			elseif ($toc2['level'] == $toc['level'])
			{
				$this->tableOfContents[] = $toc;
				$this->_tocStack[] = $toc2;
			}
			elseif ($toc2['level'] > $toc['level'])
			{
				die('TOC2>TOC');
			}
		}*/
	}
}

/**
 * Parser for Square brackets, curly bracets and hash tags
 */
class WikiElement_Parser
{
	const WIKILINK = 1;
	const TEMPLATE = 2;
	const TEMPLATE_PARAM = 3;
	const HASHTAG = 3;
	
	static public $rules = array(
		'[' => array(
			'close' => ']',
			'min' => 2,
			'max' => 2,
			'names' => array(
				2 => WikiElement_Parser::WIKILINK,
			),
		),
		'{' => array(
			'close' => '}',
			'min' => 2,
			'max' => 3,
			'names' => array(
				2 => WikiElement_Parser::TEMPLATE,
				3 => WikiElement_Parser::TEMPLATE_PARAM,
			),
		),
		'#' => array(
			'close' => "\n",
			'min' => 1,
			'max' => 1,
			'names' => array(
				1 => HASHTAG,
			),
			'no_param' => true,
			'has_name' => true,
		),
	);
	
	public $char;
	public $len;
	public $rule;
	
	public $type;
	
	private $content;
	private $is_complete;
	
	private $page;
	
	public function __construct(WikiParser $wikipage, $char, $len)
	{
		$this->rule = WikiElement_Parser::$rules[$char];
		$this->char = $char;
		$this->len = $len;
		$this->is_complete = false;
		$this->page = $wikipage;
		
		$this->throwContent(WikiParser::ELEMENT_OPEN, '', str_repeat($char, $len));
	}
	
	/**
	 * Adds content to this tag
	 */
	public function throwContent($type, $content, $unparsed = null, $additonal = array())
	{
		$i = count($this->content);
		
		// "Line" starts from this part
		if ($this->lineStart == null)
			$this->lineStart = $i;
		
		if ($i > 0 && $type == WikiParser::TEXT && $this->content[$i - 1]['type'] == WikiParser::TEXT && empty($this->content[$i - 1]['additional']) && empty($additonal))
		{
			$this->content[$i - 1]['content'] .= $content;
			
			// Does this part have "unparsed" content?
			if (!empty($this->content[$i - 1]['unparsed']))
				$this->content[$i - 1]['unparsed'] .= empty($unparsed) ? $content : $unparsed;
			// Copy parsed as unparsed if we have but there is none. Done to save memory!
			elseif (empty($this->content[$i - 1]['unparsed']) && !empty($unparsed))
				$this->content[$i - 1]['unparsed'] = $this->content[$i - 1]['content'] . $unparsed;
				
			return;
		}
		
		$this->is_complete = $type == WikiParser::ELEMENT_CLOSE;
		
		$this->content[$i] = array(
			'type' => $type,
			'content' => $content,
			'unparsed' => $unparsed,
			'additional' => $additonal,
		);
	}
	
	/**
	 * Re-throws content into upper level.
	 */
	public function throwContentTo($target)
	{
		// If this is incomplete throw as original
		if (!$this->is_complete)
		{
			foreach ($this->content as $c)
			{
				if ($c['type'] == WikiParser::ELEMENT_OPEN)
					$target->throwContent(WikiParser::TEXT, $c['unparsed']);			
				else
					$target->throwContent(
						$c['type'],
						$c['content'],
						$c['unparsed'],
						$c['additional']
					);
			}
		}
	}
	
	/**
	 * Return original unparsed content
	 */
	public function getUnparsed()
	{
		$return = '';
		foreach ($this->content as $c)
			$return .= $c['unparsed'] !== null ? $c['unparsed'] : $c['content'];
		return $return;
	}
	
	/**
	 * Sets lenght of start tag to actual lenght if it wasn't expected lenght.
	 * @param int $lenght Actual lenght of start tag
	 */
	public function modifyLen($lenght)
	{
		$this->len = $lenght;
		$this->content[0]['unparsed'] = str_repeat($this->char, $lenght);
	}
	
	/**
	 * Function to finalize content so it can be added to page (or another element)
	 */
	public function finalize()
	{		
		$param = 0;
		$params = array();
		
		foreach ($this->content as $c)
		{
			switch ($c['type'])
			{
				case WikiParser::ELEMENT_OPEN:
				case WikiParser::ELEMENT_CLOSE:
					break;
				
				case WikiParser::ELEMENT_NEW_PARAM:
					$param++;
					break;
				
				default:
					$params[$param][] = $c;
					break;
			}
		}
		
		$this->type = $this->rule['names'][$this->len];
		
		if ($this->type == WikiElement_Parser::WIKILINK)
			return new WikiLink($this->page, $params);
		else
			die(var_dump($this));
	}
	
	
		/*if ($piece['current_param'] !== null)
					{
						if (!isset($piece['firstParam']))
							$piece['firstParam'] = $piece['current_param'];
						elseif ($piece['current_param_name'] == null)
						{
							if (strpos($piece['current_param'][0], '=') !== false)
							{
								list ($paramName, $paramValue) = explode('=', $piece['current_param'][0], 2);					
								$piece['params'][$paramName] = array($paramValue) + $piece['current_param'];
								unset($paramName, $paramValue);
							}
							else
								$piece['params'][$piece['num_index']++] = $piece['current_param'];
						}
						else
							$piece['params'][$piece['current_param_name']] = $piece['current_param'];
	
						$piece['current_param'] = null;
						$piece['current_param_name'] = null;
					}*/
	
					/*$name = $rule['names'][$matchLen];
					
					if ($name == 'template')
					{
						$source = isset($piece['var']) ? $piece['var'] : $piece['firstParam'];
						$piece['var_parsed'] = strtolower(trim(str_replace(array('<br />', '&nbsp;'), array("\n", ' '), $this->__parse_part($this->fakeStatus, $source))));
						
						if (isset($context['wiki_parser_extensions']['variables'][$piece['var_parsed']]))
						{
							$name = 'variable';
							
							if (!isset($piece['var']))
								unset($piece['firstParam']);
						}
						elseif (isset($context['wiki_parser_extensions']['functions'][$piece['var_parsed']]))
						{
							$name = 'function';
							
							if (!isset($piece['var']))
								unset($piece['firstParam']);
						}
						elseif (isset($piece['var']))
						{
							$piece['var'][] = ':';
							$piece['firstParam'] = array_merge($piece['var'], $piece['firstParam']);
							unset($piece['var']);
							unset($piece['var_parsed']);
						}
					}*/
}

/**
 * WikiElement base class
 */
class WikiElement
{
	
}

/**
 * Wikilink class
 */
class WikiLink extends WikiElement
{
	private $link_info;
	private $linkNamespace;
	
	private $link;
	private $linkPage;
	private $linkText;
	
	private $params;
	
	private $html = '';
	private $is_block_level = false;
	
	function __construct(Wikiparser $wikiparser, $params)
	{
		global $context;
		
		$parsedPage = WikiParser::toText(array_shift($params));
		$force_link = false;
		
		if ($parsedPage[0] == ':')
		{
			$parsedPage = substr($parsedPage, 1);
			$force_link = true;
		}
		
		list ($this->linkNamespace, $this->linkPage) = wiki_parse_url_name($parsedPage, true);
		
		$this->link = wiki_get_url_name($this->linkNamespace, $this->linkPage);
		
		$this->link_info = cache_quick_get('wiki-pageinfo-' .  wiki_cache_escape($this->linkNamespace, $this->linkPage), 'Subs-Wiki.php', 'wiki_get_page_info', array($this->linkPage, $context['namespaces'][$this->linkNamespace]));
		
		if (!empty($params))
			$this->linkText = WikiParser::toText(array_shift($params));
		else
			$this->linkText = $this->link_info->title;
			
		$this->params = $params;
		
		if ($this->linkNamespace == $context['namespace_images']['id'] && $this->link_info->exists)
		{
			if (!empty($this->params))
			{
				$align = '';
				$size = '';
				$caption = '';
				$alt = '';

				// Size
				if (!empty($this->params[0]))
				{
					$size = WikiParser::toText($this->params[0]);
					
					if ($size == 'thumb')
						$size = ' width="180"';
					elseif (is_numeric($size))
						$size = ' width="' . $size . '"';
					elseif (strpos($size, 'x') !== false)
					{
						list ($width, $height) = explode('x', $size, 2);

						if (is_numeric($width) && is_numeric($height))
							$size = ' width="' . $width . '" height="' . $height. '"';
					}
					else
						$size = '';
				}

				// Align
				if (!empty($this->params[1]))
				{
					$align = trim(WikiParser::toText($this->params[1]));
					$align = ($align == 'left' || $align == 'right') ? $align : '';
				}

				// Alt
				if (!empty($this->params[2]))
					$alt = WikiParser::toText($this->params[2]);

				// Caption
				if (!empty($this->params[3]))
					$alt = WikiParser::toText($this->params[3]);
						
				if (!empty($align) || !empty($caption))
				{
					$this->__paragraph_handler($status, $currentHtml, 'close');
					
					$style = array();
					$class = array();
						
					if (!empty($align))
					{
						$style[] = 'float: ' . $align;
						$style[] = 'clear: ' . $align;
					}
					
					$this->is_block_level = true;

					$this->html = '<div' . (!empty($class) ? ' class="' . implode(' ', $class) . '"' : '') . (!empty($style) ? ' style="' . implode('; ', $style) . '"' : '') . '>
						<span class="topslice"><span></span></span>
						<div style="padding: 5px">';
						
				}
				
				$this->html .= '<a href="' . wiki_get_url($this->link) . '"><img src="' . wiki_get_url(array('page' => $this->link, 'image')) . '" alt="' . $alt . '"' . (!empty($caption) ? ' title="' . $caption . '"' : '') . $size . ' /></a>';

				if (!empty($align) || !empty($caption))
					$this->html .= (!empty($caption) ? '<span style="text-align: center">' . $caption . '</span>' : '') . '
						</div>
						<span class="botslice"><span></span></span>
					</div>';
			}
			else
				$this->html .= '<a href="' . wiki_get_url($this->link) . '"><img src="' . wiki_get_url(array('page' => $this->link, 'image')) . '" alt="" /></a>';
		}
		elseif (!$force_link && $this->linkNamespace == $context['namespace_category']['id'])
			$wikiparser->page->addCategory($link_info);
		else
		{
			$class = array();
		
			if (!$this->link_info->exists)
				$class[] = 'redlink';
						
			$this->html .= '<a href="' . wiki_get_url($this->link) . '"' . (!empty($class) ? ' class="'. implode(' ', $class) . '"' : '') . '>' . $this->linkText . '</a>';
		}
	}
	
	function __toString()
	{
		return $this->html;
	}
}

/**
 *
 */
class WikiTag extends WikiElement
{
	public $tag;
	public $attributes;
	public $content;
	public $html;
	
	function __construct($tag, $attributes, $content)
	{
		$this->tag = $tag;
		$this->attributes = $attributes;
		$this->content = $content;
	}
	
	function __toString()
	{
		return $this->html;
	}
}

?>