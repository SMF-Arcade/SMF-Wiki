<?php

/**
 * Class that parsers wiki page
 * Basic Usage: $parser = new Wiki_Parser($page_info); $parser->parse($my_content);
 */
class WikiParser
{
	const TEXT = 1;
	const NEW_LINE = 2;
	const NEW_PARAGRAPH = 3;
	const SECTION_HEADER = 4;
	
	const NO_PARSE = 21;
	
	const BLOCK_LEVEL_OPEN = 22;
	const BLOCK_LEVEL_CLOSE = 23;
	
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
		
	private $page_info;
	private $parse_bbc;
	
	private $content;
	
	private $lineStart = null;
	private $linePointers = array();
		
	function __construct($page_info, $parse_bbc = true)
	{
		$this->page_info = $page_info;
		$this->parse_bbc = $parse_bbc;
	}
	
	public function parse($text)
	{
		$this->content = array();
		
		$this->__parse($this, $text, false);
	}
	
	// Adds content to this page
	public function __throwContent($type, $content, $unparsed = '', $additonal = array())
	{
		$i = count($this->content);
		
		// "Line" starts from this part
		if ($this->lineStart == null)
			$this->lineStart = $i;
		
		if ($i > 0 && $type = WikiParser::TEXT && $this->content[$i - 1]['type'] == WikiParser::TEXT && empty($this->content[$i - 1]['additional']) && empty($additonal))
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
		
		if ($type == WikiParser::NEW_LINE || $type == WikiParser::NEW_PARAGRAPH || $type == WikiParser::BLOCK_LEVEL_OPEN || $type == WikiParser::BLOCK_LEVEL_CLOSE)
		{
			$linePointers[] = array($this->lineStart, $i - 1);
			$this->lineStart = null;
		}
		
		$this->content[$i] = array(
			'type' => $type,
			'content' => $content,
			'unparsed' => $unparsed,
			'additional' => $additonal,
		);
	}
	
	function __parse(WikiParser $target, $text, $is_template = false)
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
		
		if ($this->parse_bbc)
			$text = parse_bbc($text);
		
		$text = str_replace(array("\r\n", "\r", '<br />', '<br>', '<br/>'), "\n", $text);

		$searchBase = "<[{#\n";

		$i = 0;

		$textLen = strlen($text);

		$sections = array();

		$blockLevelNesting = 0;

		$paragraph = array();
		$can_open_paragraph = true;
		$is_paragraph = true;
		
		while ($i <= $textLen)
		{
			$charType = '';
			$search = $searchBase;
			$closeTag = '';

			if (!empty($stack))
			{
				$piece = end($stack);
				$search .= $piece['closing_char'] . (empty($piece['no_param']) ? '|' : '') . ($piece['closing_char'] == '}' ? ':' : '');
				$closeTag = $piece['closing_char'];
				unset($piece);
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
				$target->__throwContent(WikiParser::TEXT, substr($text, $i, $skip));
				$i += $skip;
			}
			
			// nowiki tag
			if ($this->parse_bbc && substr($text, $i, 8) == '<nowiki>')
			{
				$i += 8;
				
				$endPos = strpos($text, '</nowiki>', $i);
	
				if ($endPos > 0)
				{
					$target->__throwContent(WikiParser::NO_PARSE, substr($text, $i, $endPos - $i));
					$i = $endPos + 9;
				}
				else
					$target->__throwContent(WikiParser::TEXT, '&lt;nowiki&gt;');
				
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
			else
			{
				$curChar = isset($text[$i]) ? $text[$i] : "\n";

				// Close char?
				if ($curChar == $closeTag)
					$charType = 'close';
				// Start char?
				elseif ($this->parse_bbc && isset($this->rules[$curChar]))
				{
					// TODO: Parse wiki link style tags
					$rule = $this->rules[$curChar];
					$charType = 'open';
					
				}
				// Parameter delimiter
				elseif ($curChar == '|')
					$charType = 'pipe';
				// Function delimiter / variable value delimeter
				elseif ($curChar == ':')
					$charType = 'fdelim';
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
						$target->__throwContent(WikiParser::SECTION_HEADER, trim(substr($header, $c, -$c2)), $header, array('level' => strlen($c)));
					else
					{
						// Todo: throw warning
						$target->__throwContent(WikiParser::TEXT, $header);
					}
					
					$i += $len;
					
					continue;
				}
				// New paragraph (2 * new line)
				elseif ($this->parse_bbc && $can_open_paragraph && $curChar == "\n" && $text[$i + 1] == "\n")
				{
					$target->__throwContent(WikiParser::NEW_PARAGRAPH, "\n\n");

					$i += 2;
					
					continue;
				}
				elseif ($curChar == "\n")
				{
					$target->__throwContent(WikiParser::NEW_LINE, "\n");
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
							
							$target->__throwContent(WikiParser::BLOCK_LEVEL_OPEN, substr($text, $i, $tagLen));
						}
						elseif (!$can_paragraph)
						{
							$blockLevelNesting--;
							
							$can_open_paragraph = $blockLevelNesting == 0;
							
							$target->__throwContent(WikiParser::BLOCK_LEVEL_CLOSE, substr($text, $i, $tagLen));
						}
						
						$i += $tagLen;
						
						continue;
					}
					else
					{
						$target->__throwContent(WikiParser::TEXT, htmlspecialchars($curChar));
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
					
					if (isset($context['wiki_parser_extensions']['tags'][$tagName]))
					{
						// Last > tag
						$endPos += 4;
						
						$tag = array(
							'name' => 'tag',
							'tag_name' => $tagName,
							'content' => '',
							'attributes' => array(),
						);
						
						$tagCode = un_htmlspecialchars(trim(substr($tagCode, $tagLen)));
											
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
								
								$tag['attributes'][$atrib] = substr($tagCode, $valueStart + 1, $valueLen);
												
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
								
								$tag['attributes'][$atrib] = trim(substr($tagCode, $valueStart + 1, $valueLen));
												
								$tagCode = trim(substr($tagCode, $valueEnd + 1));	
							}
						}
						
						$endTag = '&lt;/' . $tagName . '&gt;';
						$endTagPos = strpos($text, $endTag, $i);
						
						if ($endTagPos !== false)
						{
							$tag['content'] = substr($text, $i + 2 + strlen($tagName));
							
							$endPos = $endTagPos + strlen($endTag);
						}
						
						if (empty($stack))
						{	
							$this->__paragraphClean($stringTemp);
							if (!empty($stringTemp))
								$paragraph[] =  $stringTemp;
							$stringTemp = '';
							
							$paragraph[] = $tag;
						}
						else
							$stack[$stackIndex]['current_param'][] = $tag;
							
						$i = $endPos;
						
						continue;
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
						$tag = array(
							'name' => 'behaviour_switch',
							'switch' => substr($bSwitch, 0, -2),
							'lineStart' => isset($text[$i - 1]) && $text[$i - 1] == "\n",
							'lineEnd' => isset($text[$i + 2 + $bLen]) && $text[$i + 2 + $bLen] == "\n",
						);
						
						
						
						if (empty($stack))
						{	
							$this->__paragraphClean($stringTemp);
							if (!empty($stringTemp))
								$paragraph[] =  $stringTemp;
							$stringTemp = '';
							
							$paragraph[] = $tag;
						}
						else
							$stack[$stackIndex]['current_param'][] = $tag;
							
						$i += $bLen + 2;
						
						continue;
					}
				}
				else
				{
					die('AA');
					$stringTemp .= $curChar;
					$i++;
					
					continue;
				}
				
				// There might be block level closing tag
				/*elseif ($parseSections && ($text[$i - 1] == '>') && $curChar == '=')
				{
					$pos = strrpos(substr($text, 0, $i - 1), '<');
					$tag = substr($text, $pos, $i - $pos);
					
					if (isset($this->blockTags[$tag]))
						$charType = 'new-section';
				}*/
			}

			/*if ($charType == 'new-paragraph-special' || ($can_paragraph != $is_paragraph))
			{
				$this->__paragraphClean($stringTemp);
				if (!empty($stringTemp))
					$paragraph[] = $stringTemp;
				if (!empty($paragraph))
					$section['part'][] = array(
						'is_paragraph' => $is_paragraph,
						'content' => $paragraph
					);
				
				$stringTemp = '';

				$is_paragraph = $can_paragraph;

				$paragraph = array();
				
				if ($charType == 'new-paragraph-special')
					$charType = '';
			}*/
			
			/*
			// chartype may change above so this needs to be if instead of elseif
			if ($charType == 'open')
			{
				$len = strspn($text, $curChar, $i);

				if ($len >= $rule['min'])
				{
					$this->__paragraphClean($stringTemp);
					if (!empty($stringTemp))
						$paragraph[] =  $stringTemp;
					$stringTemp = '';

					$piece = array(
						'opening_char' => $curChar,
						'closing_char' => $rule['close'],
						'len' => $len,
						'contents' => '',
						'params' => array(),
						'num_index' => 1,
						'lineStart' => ($i > 0 && $text[$i-1] == "\n"),
						'lineEnd' => false,
						'no_param' => !empty($rule['no_param']),
					);
					
					if (!empty($rule['has_name']))
					{
						$nameLen = strcspn($text, ' ', $i);
						
						if (strpos($text, " ", $i) !== false && strpos($text, "\n", $i) !== false)
						{
							$piece['item_name'] = strtolower(substr($text, $i + 1, $nameLen - 1));
							
							if (!isset($context['wiki_parser_extensions']['hash_tags'][$piece['item_name']]))
							{
								if (empty($stack))
									$stringTemp .= str_repeat($curChar, $len);
								else
									$stack[$stackIndex]['current_param'][] = str_repeat($curChar, $len);							
							}
							else
							{
								$i += $nameLen;
								$stack[] = $piece;
							}
						}
						else
						{
							if (empty($stack))
								$stringTemp .= str_repeat($curChar, $len);
							else
								$stack[$stackIndex]['current_param'][] = str_repeat($curChar, $len);							
						}
					}
					else
						$stack[] = $piece;
				}
				else
				{
					if (empty($stack))
						$stringTemp .= str_repeat($curChar, $len);
					else
						$stack[$stackIndex]['current_param'][] = str_repeat($curChar, $len);
				}

				$i += $len;
			}
			elseif ($charType == 'fdelim' && !isset($stack[$stackIndex]['var']))
			{
				$stack[$stackIndex]['var'] = $stack[$stackIndex]['current_param'];				
				$stack[$stackIndex]['current_param'] = null;
				$stack[$stackIndex]['current_param_name'] = null;
				
				$i++;
			}
			// Fdelim as normal char
			elseif ($charType == 'fdelim')
			{
				$stack[$stackIndex]['current_param'][] = $curChar;
				$i++;
			}
			elseif ($charType == 'pipe')
			{			
				if (!isset($stack[$stackIndex]['firstParam']))
					$stack[$stackIndex]['firstParam'] = $stack[$stackIndex]['current_param'];
				elseif ($stack[$stackIndex]['current_param_name'] == null)
				{
					if (strpos($stack[$stackIndex]['current_param'][0], '=') !== false)
					{
						list ($paramName, $paramValue) = explode('=', $stack[$stackIndex]['current_param'][0], 2);					
						$stack[$stackIndex]['params'][$paramName] = array($paramValue) + $stack[$stackIndex]['current_param'];
						unset($paramName, $paramValue);
					}
					else
						$stack[$stackIndex]['params'][$stack[$stackIndex]['num_index']++] = $stack[$stackIndex]['current_param'];
				}
				else
					$stack[$stackIndex]['params'][$stack[$stackIndex]['current_param_name']] = $stack[$stackIndex]['current_param'];

				$stack[$stackIndex]['current_param'] = null;
				$stack[$stackIndex]['current_param_name'] = null;

				$i++;
			}
			elseif ($charType == 'close')
			{
				$piece = &$stack[$stackIndex];

				$maxLen = $piece['len'];
				$len = strspn($text, $curChar, $i, $maxLen);

				$rule = $this->rules[$piece['opening_char']];

				if ($len > $rule['max'])
					$matchLen = $rule['max'];
				else
				{
					$matchLen = $len;

					while ($matchLen > 0 && !isset($rule['names'][$matchLen]))
						$matchLen--;
				}

				if ($matchLen <= 0)
				{
					$piece['current_param'][] = str_repeat($curChar, $len);
					$i += $len;
					continue;
				}

				if ($piece['current_param'] !== null)
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
				}

				$name = $rule['names'][$matchLen];
				
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
				}

				$thisElement = $piece;
				$thisElement['name'] = $name;
				
				$i += $matchLen;
				
				if ($thisElement['lineStart'] && (isset($text[$i + 1]) && $text[$i + 1] == "\n") && (!isset($text[$i + 2]) || $text[$i + 2] != "\n"))
				{
					$thisElement['lineEnd'] = true;
					$i++;
				}

				// Remove last item from stack
				array_pop($stack);

				if (!empty($stack))
				{
					$stackIndex = count($stack) - 1;
					
					if (!isset($stack[$stackIndex]['current_param']) || $stack[$stackIndex]['current_param'] === null)
						$stack[$stackIndex]['current_param'] = array($thisElement);
					else
					{				
						if (substr($stack[$stackIndex]['current_param'], -1) == '=')
						{
							$stack[$stackIndex]['current_param_name'] = substr($stack[$stackIndex]['current_param'], 0, -1);
							$stack[$stackIndex]['current_param'] = array($thisElement);
						}
						else
							$stack[$stackIndex]['current_param'][] = $thisElement;
					}
				};

				if ($matchLen < $piece['len'])
				{
					$skips = 0;
					$piece['len'] -= $matchLen;

					while ($piece['len'] > 0)
					{
						if (isset($rule['names'][$piece['len']]))
						{
							$piece['current_param'][] = str_repeat($piece['opening_char'], $skips);

							$stack[] = $piece;
							break;
						}

						$piece['len']--;
						$skips++;
					}
				}

				if (empty($stack))
					$paragraph[] = $thisElement;
			}*/
		}

		/*$this->__paragraphClean($stringTemp);
		
		if (!empty($stringTemp))
			$paragraph[] = $stringTemp;
		if (!empty($paragraph))
			$section['part'][] = array(
				'is_paragraph' => $is_paragraph,
				'content' => $paragraph
			);
		if (!empty($section))
			$sections[] = $section;*/
	}
}

?>