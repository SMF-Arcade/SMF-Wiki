<?php
/**
 *
 */

/**
 *
 */
class Wiki_Parser
{
	/**
	 * Page variable containing WikiPage class.
	 */
	public $page;

	/**
	 * Contains parameters given to page if it's template
	 */
	public $parameters;
	
	/**
	 *
	 */
	public $parse_bbc = true;

	/**
	 *
	 */
	public $pageOptions = array();

	/**
	 *
	 */
	public $tableOfContents;

	/**
	 * Contains used html ids to prevent duplicates
	 */
	private $_htmlIDs = array();

	// Errors
	private $errors;
	private $_maxSeverity;
	
	/**
	 *
	 */
	function __construct(WikiPage $page, $parameters = array(), $parse_bbc = true, $is_template = false)
	{
		$this->page = $page;
		$this->parameters = $parameters;
		$this->parse_bbc = $parse_bbc;
	}
	
	/**
	 * Parses context
	 *
	 * @var Wiki_Parser_SectionContainer
	 */
	public function parse($text)
	{
		$sc = new Wiki_Parser_SectionContainer($this);
		$sc->addNew(1, $this->page->title, 'wikitop'); // add: id = wikitop
		
		$this->__parse($sc, $text);
		
		// Finalize parsing (remove parser references)
		$sc->ParseFinalize();
		
		return array($sc, $this->__parseTableOfContent($sc));
	}

	/**
	 *
	 */
	/*public function getRawContentSection($section = null)
	{
		if ($this->parse_bbc && !$this->raw_parser instanceof Wiki_Parser)
		{
			$this->raw_parser = new Wiki_Parser($this->page, array(), false, true);
			$this->raw_parser->parse($this->raw_content);
			return $this->raw_parser->getRawContentSection($section);
		}
		elseif ($this->parse_bbc)
			return $this->raw_parser->getRawContentSection($section);
		else
		{
			if ($section == null)
			{
				$sections = array();
				foreach ($this->sections as $sec)
				{
					$sections[] = array(
						'title' => $sec['title'],
						'level' => $sec['level'],
						'content' => Wiki_Parser_Core::getUnparsed($sec['content'])
					);
				}

				return $sections;
			}
			elseif (!isset($this->sections[$section]))
				return false;
			else
				return array(
					'title' => $this->sections[$section]['title'],
					'level' => $this->sections[$section]['level'],
					'content' => Wiki_Parser_Core::getUnparsed($this->sections[$section]['content'])
				);
		}
	}*/

	/**
	 *
	 */
	private function __parseTableOfContent($sections, $main = true, $tlevel = 2)
	{
		$stack = array(
			array(),
			array(),
		);
		$num = 0;
		$mainToc = array();
		
		foreach ($sections as $section)
		{
			if ($section->getLevel() == $tlevel)
			{
				if (!empty($stack[0]))
					$mainToc[] = array(
						'id' => $stack[0]->getID(),
						'level' => $num,
						'name' => $stack[0]->getTitle(),
						'sub' => !empty($stack[1]) ? $this->__parseTableOfContent($stack[1], false, $tlevel + 1) : array(),
					);

				$stack = array(
					$section,
					array()
				);

				$num++;
			}
			else
				$stack[1][] = $section;
		}

		if (!empty($stack[0]))
			$mainToc[] = array(
				'id' => $stack[0]->getID(),
				'level' => $num,
				'name' => $stack[0]->getTitle(),
				'sub' => !empty($stack[1]) ? $this->__parseTableOfContent($stack[1], false, $tlevel + 1) : array(),
			);

		return $mainToc;
	}

	/**
	 * Parser page into another Wiki_Parser (used for templates)
	 */
	public function parseTo($target, $text, $is_template = true)
	{
		$this->__parse($target, $text, $is_template);
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
	 *
	 * @todo Parse BBC here
	 * @todo Refactor so this doesn't contain parser specific code
	 */
	private function __parse(Wiki_Parser_SectionContainer $target, $text, $is_template = false)
	{
		global $context;
		
		$text = strtr(
			$text,
			array(
				'&lt;includeonly&gt;' => '<includeonly>',
				'&lt;/includeonly&gt;' => '<includeonly>',
				'&lt;noinclude&gt;' => '<noinclude>',
				'&lt;/noinclude&gt;' => '</noinclude>',
				'&lt;nowiki&gt;' => '<nowiki>[nobbc]',
				'&lt;/nowiki&gt;' => '[/nobbc]</nowiki>',
				'[nobbc]' => '[nobbc]<nowiki>',
				'[/nobbc]' => '[/nobbc]</nowiki>',
				'[code' => '<nowiki>[code',
				'[/code]' => '[/code]</nowiki>',
				'[php]' => '<nowiki>[php]',
				'[/php]' => '[/php]</nowiki>',
			)
		);
		
		// Parse bbc if asked to
		if ($this->parse_bbc)
			$text = parse_bbc($text);
			
		$text = str_replace(array("\r\n", "\r", '<br />', '<br>', '<br/>'), "\n", $text);

		$searchBase = "<[{#\n_";

		$textLen = strlen($text);
		
		$stack = array();
		
		$i = 0;
		while ($i <= $textLen)
		{
			$search = $searchBase;
			$closeTag = '';

			if ($target instanceof WikiElement_Parser)
			{
				$search .= $target->rule['close'] . (empty($target->rule['no_param']) ? '|=' : '') . ($target->rule['close'] == '}' ? ':' : '');
				$closeTag = $target->rule['close'];
			}
			else
			{
				$search .= '&=';
			}

			// Never skip first character
			if ($i > 0)
			{
				// Skip to next might be special tag
				$skip = strcspn($text, $search, $i);
	
				// Normal text line
				if ($skip > 0)
				{
					$target->throwContent(Wiki_Parser_Core::TEXT, substr($text, $i, $skip));
					$i += $skip;
				}
			}
			
			// nowiki tag
			if ($this->parse_bbc && substr($text, $i, 8) == '<nowiki>')
			{
				$i += 8;
				
				$endPos = strpos($text, '</nowiki>', $i);
	
				if ($endPos > 0)
				{
					$target->throwContent(Wiki_Parser_Core::NO_PARSE, str_replace("\n", '<br />', substr($text, $i, $endPos - $i)));
					$i = $endPos + 9;
				}
				else
					$target->throwContent(Wiki_Parser_Core::TEXT, '&lt;nowiki&gt;');
				
				continue;
			}
			// Skip <includeonly> if this is not template
			elseif ($this->parse_bbc && !$is_template && substr($text, $i, 13) == '<includeonly>')
			{
				$i += 13;
				
				$endPos = strpos($text, '</includeonly>', $i);
	
				if ($endPos !== false)
					$i = $endPos + 14;
					
				continue;
			}
			elseif ($this->parse_bbc && $is_template && substr($text, $i, 13) == '<includeonly>')
			{
				$i += 13;
				
				continue;
			}
			elseif ($this->parse_bbc && $is_template && substr($text, $i, 14) == '</includeonly>')
			{
				$i += 14;
				
				continue;
			}
			// Skip <noinclude> if this is template
			elseif ($this->parse_bbc && $is_template && substr($text, $i, 11) == '<noinclude>')
			{
				$i += 11;
				
				$endPos = strpos($text, '</noinclude>', $i);
	
				if ($endPos !== false)
					$i = $endPos + 12;
					
				continue;
			}
			elseif ($this->parse_bbc && !$is_template && substr($text, $i, 11) == '<noinclude>')
			{
				$i += 11;
						    
				continue;
			}
			elseif ($this->parse_bbc && !$is_template && substr($text, $i, 12) == '</noinclude>')
			{
				$i += 12;
				
				continue;
			}
			
			if ($i >= $textLen)
				break;
			
			$whitespace = 0;
			
			if ($text[$i] === "\n")
				$whitespace = strspn($text, "\n", $i);

			$is_new_line = $i === 0 || $whitespace >= 1;
			$is_new_paragraph = $i === 0 || $whitespace >= 2;
			
			$i += $whitespace;
			
			if ($i >= $textLen)
				break;
				
			// WHITESPACE PARSE

			// Close list if prefix doesnt match
			/*if ($is_new_line && $target instanceof WikiList_Parser && (!in_array($text[$i], WikiList_Parser::$listTypes) || $is_new_paragraph))
			{
				// Close all lists
				while ($target instanceof WikiList_Parser)
				{
					$target->throwContent(Wiki_Parser_Core::LIST_ITEM_CLOSE, '</li>', '');
					$element = $target;
					$target = array_pop($stack);
					$element->throwContentTo($target);
					unset($element);
				}
			}*/
			
			$curChar = isset($text[$i]) ? $text[$i] : "\n";
			
			// CONTENT PARSE
			
			/*		if ($type == Wiki_Parser_Core::SECTION_HEADER || $type == Wiki_Parser_Core::END_PAGE)
		{
			while (isset($this->content[$i - 1]) && in_array($this->content[$i - 1]['type'], array(Wiki_Parser_Core::NEW_LINE)))
			{
				unset($this->content[$i - 1]);
				$i--;
			}

			if ($this->blockNestingLevel == 0 && $this->paragraphOpen == true)
				$this->throwContent(Wiki_Parser_Core::END_PARAGRAPH, '</p>');
			$this->paragraphOpen = false;

			unset($this->content);

			if ($type == Wiki_Parser_Core::END_PAGE)
				return;
			
			$html_id = Wiki_Parser_Core::html_id($content);

			$i2 = 1;

			// Make sure html_id is unique in page context
			while (in_array($html_id, $this->_htmlIDs))
				$html_id = Wiki_Parser_Core::html_id($content . '_'. $i2++);
			$this->_htmlIDs[] = $html_id;

			$this->sections[] = array(
				'id' => $this->html_id($html_id),
				'level' => $additonal['level'],
				'title' => $content,
				'edit_url' => wiki_get_url(array('page' => $this->page->url_name, 'sa' => 'edit', 'section' => count($this->sections))),
				'content' => array(),
			);
			$this->content = &$this->sections[count($this->sections) - 1]['content'];

			$this->hasContent = false;

			return;
		}*/

			/**
			 * Parse new section
			 */
			if ($target instanceof Wiki_Parser_SectionContainer && $is_new_line && $curChar == '=')
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
					// Create new section
					$target->addNew(trim(substr($header, $c, -$c2)), $c);
					$i += $len;
				}
				else
				{
					$target->throwContent(Wiki_Parser_Core::TEXT, '=');
					$i += 1;
				}
				
				continue;
			}
			// New paragraph
			elseif ($is_new_paragraph)
				$target->throwContent(Wiki_Parser_Core::END_PARAGRAPH, '</p>', "\n\n");
			// New line
			elseif ($is_new_line && !$target instanceof WikiList_Parser)
			{
				if ($i > 0)
					$target->throwContent(Wiki_Parser_Core::NEW_LINE, '<br />', "\n");
			}
			
			if (false)
			{
				
			}
			/*
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
					$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($curChar, $len));
					$i += $len;
					continue;
				}
				
				// Tell element that it was closed
				$target->throwContent(Wiki_Parser_Core::ELEMENT_CLOSE, '', str_repeat($curChar, $matchLen));
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
						$target->throwContent(Wiki_Parser_Core::ELEMENT_OPEN, str_repeat($element->char, $open));
					}
					// or just unnecassary character?
					else
					{
						$target = array_pop($stack);
						$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($element->char, $open));
					}
				}
				else
					$target = array_pop($stack);
				
				// Tell elment that it's really complete and let it finalize.
				$element->throwContentTo($target);
				
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
							if (!isset(Wiki_Parser_Core::$hashTags[$item_name]))
							{
								$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($curChar, $len));							
							}
							else
							{
								$stack[] = $target;
								
								$target = new WikiElement_Parser($this, $curChar, $len);
								$target->throwContent(Wiki_Parser_Core::ELEMENT_NAME, $item_name);

								$i += $nameLen;
							}
						}
						else
						{
							$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($curChar, $len));						
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
					$target->throwContent(Wiki_Parser_Core::TEXT, str_repeat($curChar, $len));
				}

				$i += $len;
			}
			// Handle lists
			elseif ($this->parse_bbc && $is_new_line && in_array($curChar, WikiList_Parser::$listTypes))
			{
				// Default only one character
				$maxLen = 1;
				
				// If list open can start new level
				if ($target instanceof WikiList_Parser)
					$maxLen = strlen($target->prefix) + 1;
				
				$prefixLen = strspn($text, implode(WikiList_Parser::$listTypes), $i, $maxLen);
				
				$prefix = substr($text, $i, $prefixLen);
				$type = substr($prefix, -1);

				if ($target instanceof WikiList_Parser)
				{
					// Close previous and open new item
					if ($prefix == $target->prefix)
					{
						$target->throwContent(Wiki_Parser_Core::LIST_ITEM_CLOSE, '</li>', "\n");
						$target->throwContent(Wiki_Parser_Core::LIST_ITEM_OPEN, '<li>', $type);
						$i += $prefixLen;
					}
					// New level possibly
					elseif (strlen($target->prefix) < $prefixLen)
					{
						$current = $target->prefix;
						$new = $prefix;
						
						$x = 0;
						while (isset($current[$x]) && isset($new[$x]) && $current[$x] == $new[$x])
							$x++;
							
						// New level
						if ($x == strlen($current))
						{
							// Create new parser
							$stack[] = $target;
							$target = new WikiList_Parser($this, $type, $prefix);
							$target->throwContent(Wiki_Parser_Core::LIST_ITEM_OPEN, '<li>');
							
							$i += $prefixLen;
						}
						// Invalid, abandon the ship!
						else
						{
							while ($target instanceof WikiList_Parser)
							{
								$target->throwContent(Wiki_Parser_Core::LIST_ITEM_CLOSE, '</li>', '');
								$element = $target;
								$target = array_pop($stack);
								$element->throwContentTo($target);
								unset($element);
							}
							
							$i += $prefixLen;
							$target->throwContent(Wiki_Parser_Core::TEXT, $prefix);
						}
						
						continue;
					}
					else
					{
						$current = $target->prefix;
						$new = $prefix;
						
						$x = 0;
						while (isset($current[$x]) && isset($new[$x]) && $current[$x] == $new[$x])
							$x++;
							
						$toClose = strlen($current) - $x;
							
						while ($toClose > 0 && $target instanceof WikiList_Parser)
						{
							$target->throwContent(Wiki_Parser_Core::LIST_ITEM_CLOSE, '</li>', '');
							$element = $target;
							$target = array_pop($stack);
							$element->throwContentTo($target);
							unset($element);
							
							$toClose--;
						}
						
						if ($target instanceof WikiList_Parser)
						{
							$target->throwContent(Wiki_Parser_Core::LIST_ITEM_CLOSE, '</li>');
							$target->throwContent(Wiki_Parser_Core::LIST_ITEM_OPEN, '<li>');
						}
						
						$i += $prefixLen;
						continue;
					}
				}
				// New list starts
				elseif ($prefixLen == 1)
				{
					$stack[] = $target;
					$target = new WikiList_Parser($this, $type, $prefix);

					$target->throwContent(Wiki_Parser_Core::LIST_ITEM_OPEN, '<li>');

					$i += $prefixLen;
				}
				// It was invalid
				else
				{
					$target->throwContent(Wiki_Parser_Core::TEXT, $curChar);
					$i++;
				}
			}
			// Parameter delimiter
			elseif ($this->parse_bbc && $curChar == '|')
			{
				$target->throwContent(Wiki_Parser_Core::ELEMENT_NEW_PARAM, '|');
				$i++;
			}
			// Function delimiter / variable value delimeter
			elseif ($this->parse_bbc && $curChar == ':')
			{
				$target->throwContent(Wiki_Parser_Core::ELEMENT_SEMI_COLON, ':');
				$i++;
			}
			// Function delimiter / variable value delimeter
			elseif ($target instanceof WikiElement_Parser && empty($target->rule['no_param']) && $curChar == '=')
			{
				$target->throwContent(Wiki_Parser_Core::ELEMENT_PARAM_NAME, '=');
				$i++;
			}
			// Start or end of html tag from parse bbc
			elseif ($this->parse_bbc && $curChar == '<')
			{
				$tagnameLen = strcspn($text, ' >', $i + 1);
				$tagLen = strcspn($text, '>', $i + 1) + 1;
				$tag = '<' . substr($text, $i + 1, $tagnameLen) . '>';

				if (isset(Wiki_Parser_Core::$blockTags[$tag]))
				{
					if (Wiki_Parser_Core::$blockTags[$tag] === false)
					{
						$target->throwContent(Wiki_Parser_Core::CONTROL_BLOCK_LEVEL_OPEN);
						$target->throwContent(Wiki_Parser_Core::NO_PARSE, substr($text, $i, $tagnameLen + 1));
						$i += $tagnameLen + 1;
					}
					else
					{
						$target->throwContent(Wiki_Parser_Core::NO_PARSE, substr($text, $i, $tagLen + 1));
						$target->throwContent(Wiki_Parser_Core::CONTROL_BLOCK_LEVEL_CLOSE);
						$i += $tagLen + 1;
					}
					
					continue;
				}
				else
				{
					$target->throwContent(Wiki_Parser_Core::TEXT, $curChar);
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
				
				if (WikiExtension::isXMLTag($tagName))
				{
					$tag = WikiExtension::getXMLTag($tagName);
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
						$tagContent = substr($text, $endPos, $endTagPos - $endPos);
						$endPos = $endTagPos + strlen($endTag);
					}
					
					$target->throwContent(Wiki_Parser_Core::ELEMENT,
						new $tag['class']($target, $tagName, $attributes, $tagContent),
						substr($text, $i, $endPos - $i)
					);
						
					$i = $endPos;
					
					continue;
				}
				else
				{
					$target->throwContent(Wiki_Parser_Core::TEXT, '&lt;');
					$i += 4;
				}
			}
			// Behaviour switch
			elseif ($this->parse_bbc && $curChar == '_' && $text[$i + 1] == '_')
			{
				// Find next space or new line
				$bLen = strcspn($text, " \n" . ($target instanceof WikiElement_Parser ? $target->rule['close'] : ''), $i + 2);
				$bSwitch = substr($text, $i + 2, $bLen);
				
				if (substr($bSwitch, -2) == '__' && WikiExtension::isMagicword(substr($bSwitch, 0, -2)))
				{
					$magicWord = WikiExtension::getMagicword(substr($bSwitch, 0, -2));

					if (isset($magicWord['callback']))
						call_user_func($magicWord['callback'], $this);
					else
						$target->throwContent(Wiki_Parser_Core::TEXT, $magicWord['txt']);

					$i += $bLen + 2;
					
					continue;
				}
				else
				{
					$target->throwContent(Wiki_Parser_Core::TEXT, substr($text, $i, $bLen + 2));
					$i += $bLen + 2;
				}
			}*/
			// Else add it as text
			else
			{
				$target->throwContent(Wiki_Parser_Core::TEXT, $curChar);
				$i++;
			}
		}
		
		// Empty stack
		while (!empty($stack))
		{
			if ($target instanceof WikiList_Parser)
				$target->throwContent(Wiki_Parser_Core::LIST_ITEM_CLOSE, '</li>', '');
			
			$element = $target;
			$target = array_pop($stack);
			
			// Ask element to throw content to previous element
			$element->throwContentTo($target);
			
			unset($element);
		}

		// Todo: fix
		//if (!$is_template)
		//	$this->throwContent(Wiki_Parser_Core::END_PAGE, '');
	}
}

?>