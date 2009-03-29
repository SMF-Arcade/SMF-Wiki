<?php
/**********************************************************************************
* WikiParser.php                                                                  *
***********************************************************************************
* SMF Wiki                                                                        *
* =============================================================================== *
* Software Version:           SMF Wiki 0.1                                        *
* Software by:                Niko Pahajoki (http://www.madjoki.com)              *
* Copyright 2008-2009 by:     Niko Pahajoki (http://www.madjoki.com)              *
* Support, News, Updates at:  http://www.smfarcade.info                           *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

// Makes Table of Content
function do_toctable($tlevel, $toc, $main = true)
{
	$stack = array(
		'',
		array(),
	);
	$num = 0;
	$mainToc = array();

	foreach ($toc as $t)
	{
		list ($level, $title) = $t;

		if ($level == $tlevel)
		{
			if (!empty($stack[0]))
			{
				$mainToc[] = array(
					$num,
					$stack[0],
					!empty($stack[1]) ? do_toctable($tlevel + 1, $stack[1], false) : array(),
				);
			}

			$stack = array(
				$title,
				array()
			);

			$num++;
		}
		elseif ($level >= $tlevel)
			$stack[1][] = array($level, $title);
	}

	if (!empty($stack[0]))
	{
		$mainToc[] = array(
			$num,
			$stack[0],
			!empty($stack[1]) ? do_toctable($tlevel+1, $stack[1], false) : array(),
		);
	}

	return $mainToc;
}

// Callback for wikivariables
function wikivariable_callback($groups)
{
	global $context, $pageVariables;

	if (empty($groups[2]))
	{
		if (isset($pageVariables[$groups[1]]))
			return $pageVariables[$groups[1]];
		elseif (isset($context['wiki_variables'][$groups[1]]))
			return $context['wiki_variables'][$groups[1]];
	}
	else
	{
		if (isset($pageVariables))
			$pageVariables[$groups[1]] = $groups[2];
		return '';
	}

	return $groups[0];
}

// Parses variables from content
function wikiparse_variables($message)
{
	global $rep_temp, $pageVariables;

	$pageVariables = array();

	$message = preg_replace_callback('%{{([a-zA-Z]+):(.+?)}}%', 'wikivariable_callback', $message);

	$temp = $pageVariables;
	unset($pageVariables);

	return $temp;
}

class WikiParser
{
	function __construct()
	{

	}
	
	function parse($content, &$page_info, &$status, $mode = 'normal', $parameters = array())
	{
		global $context, $txt;
		
		if ($mode == 'normal')
		{
			// Run preprocessor to get array of items
			$content = WikiParser::__preprocess($content, $mode);
		}
		
		$sections = array();
		
		$status['paragraphOpen'] = isset($status['paragraphOpen']) ? $status['paragraphOpen'] : false;
		
		foreach ($content as $section)
		{
			$sectionID = count($sections);
			
			$sections[] = array(
				'name' => $section['name'],
				'html' => '',
			);
			
			// Make reference, since it's easier to read this way
			$currentHtml = &$sections[$sectionID]['html'];
			
			if (empty($section['part']))
				continue;
			
			foreach ($section['part'] as $part)
			{
				if (!empty($part['is_new_paragraph']))
				{
					if ($status['paragraphOpen'])
						$currentHtml .= '</p>';
					
					$currentHtml .= '<p>';
					$status['paragraphOpen'] = true;
				}
				if (!empty($part['inline']) && !$status['paragraphOpen'])
				{
					$currentHtml .= '<p>';
					$status['paragraphOpen'] = true;					
				}
				elseif (empty($part['inline']) && $status['paragraphOpen'])
				{
					$currentHtml .= '</p>';
					$status['paragraphOpen'] = false;					
				}
				
				foreach ($part['content'] as $item)
				{
					if (is_string($item))
						$currentHtml .= $item;
					// Replace templates
					elseif ($item['name'] == 'template')
					{
						$item['firstParam'] = trim(str_replace(array('<br />', '&nbsp;'), array("\n", ' '), $item['firstParam']));

						list ($namespace, $page) = __url_page_parse($item['firstParam']);

						// TODO: Make Template special namespace
						if (empty($namespace))
							$namespace = 'Template';
							
						$template_info = cache_quick_get('wiki-pageinfo-' .  $namespace . '-' . $page, 'Subs-Wiki.php', 'wiki_get_page_info', array($page, $context['namespaces'][$namespace]));

						if ($template_info['id'] !== null)
						{
							list ($template_data, $template_raw_content, $template_content) = cache_quick_get(
								'wiki-page-' . $template_info['id'] . '-rev' . $template_info['current_revision'],
								'Subs-Wiki.php', 'wiki_get_page_content',
								array($template_info, $context['namespaces'][$namespace], $template_info['current_revision'])
							);

							$currentHtml .= WikiParser::parse($template_raw_content, $page_info, $status, 'template', $item['params']);
						}
						else
							$currentHtml .= '<span style="color: red">' . sprintf($txt['template_not_found'], (!empty($namespace) ? $namespace . ':' . $page : $page)). '</span>';					
					}
					else
						WikiParser::__debug_die($currentHtml, $item);
				} // END content of part
			} // END part of section
			
			if ($status['paragraphOpen'])
			{
				$currentHtml .= '</p>';
				$status['paragraphOpen'] = false;					
			}
			
			unset($currentHtml);
		} // END section
		
		return $sections;
	}
	
	private function __debug_die($currentHtml, $item)
	{
		echo '
		PARSE ERROR!!
		Unable to parse item <pre>', var_dump($item), '</pre>
		Parsed HTML: <pre>', htmlspecialchars($currentHtml), '</pre>';
		
		die();
	}

	private function __preprocess($text, $mode = 'normal')
	{
		$stringTemp = '';
		$i = 0;
		
		$text = parse_bbc($text);
		$text = str_replace(array("\r\n", '<br />'), "\n", $text);

		$currentItem = array();

		$rules = array(
			'[' => array(
				'close' => ']',
				'min' => 2,
				'max' => 2,
				'names' => array(
					2 => 'wikilink',
				),
			),
			'{' => array(
				'close' => '}',
				'min' => 2,
				'max' => 3,
				'names' => array(
					2 => 'template',
					3 => 'template_param',
				),
			),
		);

		$stack = array();
		$piece = null;

		$searchBase = "[{\n";

		$textLen = strlen($text);

		$sections = array();

		$section = array('name' => '(root)', 'parts' => array());

		$paragraph = array();
		$is_new_paragraph = true;

		while (true)
		{
			$charType = '';
			$search = $searchBase;
			$closeTag = '';

			$stackIndex = count($stack) - 1;

			if (!empty($stack))
			{
				$piece = end($stack);
				$search .= $piece['closing_char'] . '|';// . '|';
				$closeTag = $piece['closing_char'];
				unset($piece);
			}

			$skip = strcspn($text, $search, $i);

			if ($skip > 0 && empty($stack))
			{
				$stringTemp .= substr($text, $i, $skip);
				$i += $skip;
			}
			elseif ($skip > 0)
			{
				$stack[$stackIndex]['current_param'] .= substr($text, $i, $skip);
				$i += $skip;
			}

			if ($i >= $textLen)
				break;
			else
			{
				$curChar = $text[$i];

				// Close char?
				if ($curChar == $closeTag)
					$charType = 'close';
				// Start char?
				elseif (isset($rules[$curChar]))
				{
					$rule = $rules[$curChar];
					$charType = 'open';
				}
				elseif ($curChar == '|')
					$charType = 'pipe';
				elseif ($curChar == "\n" && $text[$i + 1] == "=")
				{
					$charType = 'new-section';

					$i += 1;
				}
				elseif ($curChar == "\n" && $text[$i + 1] == "\n" && $text[$i + 2] != "=")
				{
					$charType = 'new-paragraph';

					$i += 2;
				}
			}

			if ($charType == 'new-paragraph')
			{
				if (!empty($stringTemp))
					$paragraph[] = $stringTemp;
				$stringTemp = '';

				$section['part'][] = array(
					'is_new_paragraph' => $is_new_paragraph,
					'inline' => true,
					'content' => $paragraph
				);
				
				$is_new_paragraph = true;

				$paragraph = array();
			}
			elseif ($charType == 'new-section')
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
					if (!empty($stringTemp))
						$paragraph[] = $stringTemp;
					if (!empty($paragraph))
						$section['part'][] = array(
							'is_new_paragraph' => $is_new_paragraph,
							'inline' => true,
							'content' => $paragraph,
						);
					if (!empty($section))
						$sections[] = $section;
					$stringTemp = '';
					$paragraph = array();

					$name = trim(substr($header, $c, -$c2));

					$section = array(
						'name' => $name,
						'parts' => array(),
					);
					
					$is_new_paragraph = true;
					
					$i += $len;
					
					continue;
				}
				// Not header
				else
				{
					$i--;
					$charType = '';
				}
			}
			// chartype may change above so this needs to be if instead of elseif
			if ($charType == 'open')
			{
				$len = strspn($text, $curChar, $i);

				if ($len >= $rule['min'])
				{
					if (!empty($stringTemp))
						$paragraph[] = $stringTemp;
					$stringTemp = '';

					$piece = array(
						'opening_char' => $curChar,
						'closing_char' => $rule['close'],
						'len' => $len,
						'contents' => '',
						'params' => array(),
						'children' => array(),
						'lineStart' => ($i > 0 && $text[$i-1] == "\n"),
					);

					$stack[] = $piece;
				}
				else
				{
					if (empty($stack))
						$stringTemp .= str_repeat($curChar, $len);
					else
						$stack[$stackIndex]['current_param'] .= str_repeat($curChar, $len);
				}

				$i += $len;
			}
			elseif ($charType == 'pipe')
			{
				if (!isset($stack[$stackIndex]['firstParam']))
					$stack[$stackIndex]['firstParam'] = $stack[$stackIndex]['current_param'];
				elseif ($stack[$stackIndex]['current_param_name'] == null)
				{
					if (strpos($stack[$stackIndex]['current_param'], '=') !== false)
					{
						list ($paramName, $paramValue) = explode('=', $stack[$stackIndex]['current_param'], 2);					
						$stack[$stackIndex]['params'][$paramName] = $paramValue;
						unset($paramName, $paramValue);
					}
					else
						$stack[$stackIndex]['params'][] = $stack[$stackIndex]['current_param'];
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

				$rule = $rules[$piece['opening_char']];

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
					$stack[$stackIndex]['current_param'] .= str_repeat($curChar, $len);
					$i += $len;
					continue;
				}

				if ($piece['current_param'] !== null)
				{
					if (!isset($stack[$stackIndex]['firstParam']))
						$stack[$stackIndex]['firstParam'] = $stack[$stackIndex]['current_param'];
					elseif ($stack[$stackIndex]['current_param_name'] == null)
						$stack[$stackIndex]['params'][] = $stack[$stackIndex]['current_param'];
					else
						$stack[$stackIndex]['params'][$stack[$stackIndex]['current_param_name']] = $stack[$stackIndex]['current_param'];

					$stack[$stackIndex]['current_param'] = null;
					$stack[$stackIndex]['current_param_name'] = null;
				}

				$name = $rule['names'][$matchLen];
				$params = array();

				if (!empty($piece['params']))
				{
					foreach ($piece['params'] as $p)
						$params[] = $p;
				}

				$thisElement = $piece;
				$thisElement['name'] = $name;

				$i += $matchLen;

				// Remove last item from stack
				array_pop($stack);

				if (!empty($stack))
				{
					$stackIndex = count($stack) - 1;

					if ($stack[$stackIndex]['current_param'] === null)
						$stack[$stackIndex]['current_param'] = $thisElement;
					else
					{
						$stack[$stackIndex]['current_param'] = trim($stack[$stackIndex]['current_param']);

						if (substr($stack[$stackIndex]['current_param'], -1) == '=')
						{
							$stack[$stackIndex]['current_param_name'] = substr($stack[$stackIndex]['current_param'], 0, -1);
							$stack[$stackIndex]['current_param'] = $thisElement;
						}
						else
							$stack[$stackIndex]['children'][] = $thisElement;
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
							$piece['current_param'] .= str_repeat($piece['opening_char'], $skips);

							$stack[] = $piece;
							break;
						}

						$piece['len']--;
						$skips++;
					}
				}

				if (empty($stack))
					$paragraph[] = $thisElement;
			}
			elseif ($charType == '' && empty($stack))
			{
				$stringTemp .= $curChar;
				$i++;
			}
			elseif ($charType == '')
			{
				$stack[$stackIndex]['current_param'] .= $curChar;
				$i++;
			}
		}

		if (!empty($stringTemp))
			$paragraph[] = $stringTemp;
		if (!empty($paragraph))
			$section['part'][] = array(
				'is_new_paragraph' => $is_new_paragraph,
				'inline' => true,
				'content' => $paragraph
			);
		if (!empty($section))
			$sections[] = $section;

		return $sections;
	}
}

class WikiParserOld
{
	var $page_info;
	var $namespace;
	var $params;
	var $parse_bbc = true;

	var $tableOfContents = array();
	var $pageSections = array();
	var $currentSection = array();

	function __construct($page_info, $namespace, $parse_bbc = true)
	{
		$this->page_info = $page_info;
		$this->namespace = $namespace;
		$this->parse_bbc = $parse_bbc;
	}

	function parse($message, $params = array())
	{
		$this->params = $params;

		if ($this->parse_bbc)
		{
			$message = $this->__parse__curls($message);
			$message = parse_bbc($message);
			$message = preg_replace_callback('/\[\[(.*?)(\|(.*?))?\]\](.*?)([.,\'"\s]|$|\r\n|\n|\r|<br( \/)?>|<)/', array($this, '__link_callback'), $message);
		}

		$parts = preg_split(
			'%(={2,5})\s{0,}(.+?)\s{0,}\1\s{0,}<br />|(<br /><br />|<br />|<!!!>|</!!!>|<div|<ul|<table|<code|</div>|</ul>|</table>|</code>)%',
			$message,
			null,
			PREG_SPLIT_DELIM_CAPTURE
		);

		$this->currentSection = array(
			'title' => $this->page_info['title'],
			'level' => 1,
			'content' => '',
			'parts' => array(),
			'edit_url' => wiki_get_url(array(
				'page' => wiki_urlname($this->page_info['title'], $this->namespace),
				'sa' => 'edit',
			)),
		);

		// Set current status for parser
		$para_open = false;
		$can_para = true;
		$in_bracket = false;
		$currentBracket = '';

		$toc = array();

		$contentTemp = '';

		$currentPart = array();
		$currentType = 'p';

		$i = 0;
		while ($i < count($parts))
		{
			// New Section
			if (substr($parts[$i], 0, 1) == '=' && strlen($parts[$i]) >= 2 && strlen($parts[$i]) <= 5 && str_replace('=', '', $parts[$i]) == '')
			{
				if (!empty($currentPart))
					$this->currentSection['parts'][] = $currentPart;
				$currentPart = array();

				$this->pageSections[] = $this->currentSection;

				$toc[] = array(strlen($parts[$i]), $parts[$i + 1]);

				$this->currentSection = array(
					'title' => $parts[$i + 1],
					'level' => strlen($parts[$i]),
					'content' => '',
					'parts' => array(),
					'edit_url' => wiki_get_url(array(
						'page' => wiki_urlname($this->page_info['title'], $this->namespace),
						'sa' => 'edit',
						'section' => count($this->pageSections),
					)),
				);

				$i += 1;
			}
			elseif (!$this->parse_bbc)
				$this->currentSection['content'] .= $parts[$i];
			// End of Paragraph?
			elseif ($parts[$i] == '<br /><br />')
			{
				if (!empty($currentPart))
					$this->currentSection['parts'][] = $currentPart;

				$currentType = 'p';
				$currentPart = array();
			}
			// Block tags can't be in paragraph
			elseif (in_array($parts[$i], array('<div', '<ul', '<table', '<code')))
			{
				if ($currentType != 'raw')
				{
					$this->currentSection['parts'][] = $currentPart;

					$currentType = 'raw';
					$currentPart = array(
						'type' => 'raw',
						'content' => '',
					);
				}

				$currentPart['content'] .= $parts[$i];
			}
			elseif (in_array($parts[$i], array('</div>', '</ul>', '</table>', '</code>')))
			{
				if (!empty($currentPart))
				{
					$currentPart['content'] .= $parts[$i];

					$this->currentSection['parts'][] = $currentPart;
				}

				$currentType = 'p';
				$currentPart = array();
			}
			// No paragraphs area
			elseif ($parts[$i] == '<!!!>')
			{
				if (!empty($currentPart))
					$this->currentSection['parts'][] = $currentPart;

				$currentType = 'raw';
				$currentPart = array(
					'type' => 'raw',
					'content' => '',
				);
			}
			// No paragraphs area
			elseif ($parts[$i] == '</!!!>')
			{
				if (!empty($currentPart))
					$this->currentSection['parts'][] = $currentPart;

				$currentType = 'p';
				$currentPart = array();
			}
			// Avoid starting paragraph with newline
			elseif ($parts[$i] == '<br />')
			{
				if (!empty($currentPart['content']))
					$currentPart['content'] .= $parts[$i];
			}
			elseif (!empty($parts[$i]))
			{
				if (empty($currentPart))
					$currentPart = array(
						'type' => $currentType,
						'content' => '',
					);

				$currentPart['content'] .= $parts[$i];
			}

			$i++;
		}

		$this->currentSection['parts'][] = $currentPart;
		$this->pageSections[] = $this->currentSection;
		$this->currentSection = null;

		$this->tableOfContents = do_toctable(2, $toc);
	}

	function __parse__curls($message, $params = array())
	{
		global $context, $txt;

		$parts = preg_split(
			'%(&lt;nowiki&gt;|&lt;/nowiki&gt;|{{{|}}}|{{|}}|\||&quot;|")%',
			$message,
			null,
			PREG_SPLIT_DELIM_CAPTURE
		);

		$inBracket = false;
		$inQuote = false;
		$wikiParseSection = true;
		$currentBracket = array(
			'name' => '',
			'params' => array(),
			'data' => array(),
			'non_parsed' => '',
		);

		$openBrackets = array();

		$message = '';

		$i = 0;

		while ($i < count($parts))
		{
			if (!$inBracket && $parts[$i] == '&lt;nowiki&gt;' && $wikiParseSection)
				$wikiParseSection = false;
			elseif (!$inBracket && $parts[$i] == '&lt;/nowiki&gt;' && !$wikiParseSection)
				$wikiParseSection = true;
			elseif (!$wikiParseSection && !$inBracket)
				$message .= $parts[$i];
			elseif (!$wikiParseSection)
			{
				$currentBracket['non_parsed'] .= $parts[$i];
				$currentBracket['data'][] = $parts[$i];
			}
			// Quotes
			elseif ($inQuote && ($parts[$i] == '&quot;' || $parts[$i] == '&quot;'))
			{
				$inQuote = false;
				$currentBracket['non_parsed'] .= $parts[$i];
			}
			elseif ($inQuote)
			{
				$currentBracket['non_parsed'] .= $parts[$i];
				$currentBracket['data'][] = $parts[$i];
			}
			elseif (!$inQuote && $inBracket && ($parts[$i] == '&quot;' || $parts[$i] == '&quot;'))
			{
				$inQuote = true;
				$currentBracket['non_parsed'] .= $parts[$i];
			}
			// Brackets
			elseif ($parts[$i] == '{{' && isset($parts[$i + 1]))
			{
				if ($inBracket)
				{
					$openBrackets[] = $currentBracket;
					$currentBracket = array(
						'name' => '',
						'params' => array(),
						'data' => array(),
						'non_parsed' => '',
					);
				}

				$currentBracket['non_parsed'] = $parts[$i];
				$i++;

				$inBracket = true;
				$currentBracket['type'] = 2;
				$currentBracket['name'] = $parts[$i];
				$currentBracket['non_parsed'] .= $parts[$i];
			}
			elseif ($parts[$i] == '{{{' && isset($parts[$i + 1]))
			{
				if ($inBracket)
				{
					$openBrackets[] = $currentBracket;
					$currentBracket = array(
						'name' => '',
						'params' => array(),
						'data' => array(),
						'non_parsed' => '',
					);
				}

				$currentBracket['non_parsed'] = $parts[$i];
				$i++;

				$inBracket = true;
				$currentBracket['type'] = 3;
				$currentBracket['name'] = $parts[$i];
				$currentBracket['non_parsed'] .= $parts[$i];
			}
			elseif ($inBracket && $parts[$i] == '|')
			{
				if (!empty($currentBracket['data']))
					$currentBracket['params'][] = $currentBracket['data'];
				$currentBracket['data'] = array('|');
				$currentBracket['non_parsed'] .= $parts[$i];
			}
			elseif (($currentBracket['type'] === 2 && $parts[$i] == '}}' || $currentBracket['type'] === 3 && $parts[$i] == '}}}'))
			{
				$currentBracket['non_parsed'] .= $parts[$i];

				// is there param?
				if (!empty($currentBracket['data']))
				{
					$currentBracket['params'][] = $currentBracket['data'];
					$currentBracket['data'] = array();
				}

				$currentBracket['parsed'] = '';

				if ($currentBracket['type'] == 3)
				{
					if (isset($params[$currentBracket['name']]))
					{
						$currentBracket['parsed'] .= $params[$currentBracket['name']];
						$currentBracket['boolean_value'] = true;
					}
					else
					{
						$currentBracket['parsed'] .= $currentBracket['non_parsed'];
						$currentBracket['boolean_value'] = false;
					}
				}
				elseif ($currentBracket['type'] == 2)
				{
					if (substr($currentBracket['name'], 0, 1) == '#')
					{
						$prams = array();

						list ($function, $param1) = explode(':', substr($currentBracket['name'], 1), 2);

						$funcParams = array();

						if (trim($param1) != '')
							$funcParams[] = trim($param1);

						foreach ($currentBracket['params'] as $temp)
						{
							$param = array();
							$dynamicParams = array();

							foreach ($temp as $ib => $part)
							{
								// Separator
								if ($ib == 0 && is_string($part) && $part == '|' && empty($funcParams))
									$funcParams[] = $param;
								elseif ($ib == 0 && is_string($part) && $part == '|')
									continue;
								elseif (is_string($part))
									$param[] = $part;
								elseif (is_array($part))
								{
									$dynamicParams[] = $part;
									$param[] = $part['parsed'];
								}
							}

							if (count($dynamicParams) == 1 && trim($dynamicParams[0]['parsed']) == trim(implode('', $param)))
								$funcParams[] = $dynamicParams[0];
							else
								$funcParams[] = trim(implode('', $param));
						}

						$function = trim($function);

						if ($function == 'if')
						{
							if (isset($funcParams[0]) && is_array($funcParams[0]))
							{
								if (isset($funcParams[0]['boolean_value']) && $funcParams[0]['boolean_value'] === true)
									$currentBracket['parsed'] .= isset($funcParams[1]) ? $funcParams[1] : '';
								else
									$currentBracket['parsed'] .= isset($funcParams[2]) ? $funcParams[2] : '';
							}
							elseif (isset($funcParams[0]))
							{
								if (trim($funcParams[0]) == true)
									$currentBracket['parsed'] .= isset($funcParams[1]) ? $funcParams[1] : '';
								else
									$currentBracket['parsed'] .= isset($funcParams[2]) ? $funcParams[2] : '';
							}
						}
					}
					elseif (isset($this->page_info['variables'][$currentBracket['name']]))
						$currentBracket['parsed'] .= $this->page_info['variables'][$currentBracket['name']];
					elseif (isset($context['wiki_variables'][$currentBracket['name']]))
						$currentBracket['parsed'] .= $context['wiki_variables'][$currentBracket['name']];
					else
					{
	
					}
				}
				else
					$currentBracket['parsed'] .= $currentBracket['non_parsed'];

				if (empty($openBrackets))
				{
					$inBracket = false;

					$message .= $currentBracket['parsed'];

					$currentBracket = array(
						'name' => '',
						'params' => array(),
						'non_parsed' => '',
					);
				}
				else
				{
					$parsedBracket = $currentBracket;

					$currentBracket = array_pop($openBrackets);
					$currentBracket['non_parsed'] .= $parsedBracket['non_parsed'];
					$currentBracket['data'][] = $parsedBracket;
				}
			}
			elseif ($inBracket && !empty($parts[$i]))
			{
				$currentBracket['non_parsed'] .= $parts[$i];
				$currentBracket['data'][] = $parts[$i];
			}
			elseif (!$inBracket && !empty($parts[$i]))
				$message .= $parts[$i];

			$i++;
		}

		// Try to fix mistakes
		if (!empty($currentBracket['non_parsed']))
		{
			foreach ($openBrackets as $brc)
				$message .= $brc['non_parsed'];
			$message .= $currentBracket['non_parsed'];
		}

		return $message;
	}

	function __link_callback($groups)
	{
		global $context;

		list ($namespace, $page) = __url_page_parse($groups[1]);

		$page_info = cache_quick_get('wiki-pageinfo-' .  $namespace . '-' . $page, 'Subs-Wiki.php', 'wiki_get_page_info', array($page, $context['namespaces'][$namespace]));

		if ($namespace == $context['namespace_images']['id'] && $page_info['id'] !== null)
		{
			if (!empty($groups[3]))
			{
				$options = explode('|', $groups[3]);
				$align = '';
				$size = '';
				$caption = '';
				$alt = '';

				// Size
				if (!empty($options[0]))
				{
					if ($options[0] == 'thumb')
						$size = ' width="180"';
					elseif (is_numeric($options[0]))
						$size = ' width="' . $options[0] . '"';
					elseif (strpos($options[0], 'x') !== false)
					{
						list ($width, $height) = explode('x', $options[0], 2);

						if (is_numeric($width) && is_numeric($height))
						{
							$size = ' width="' . $width . '" height="' . $height. '"';
						}
					}
				}

				// Align
				if (!empty($options[1]) && ($options[1] == 'left' || $options[1] == 'right'))
					$align = $options[1];

				// Alt
				if (!empty($options[2]))
					$alt = $options[2];

				// Caption
				if (!empty($options[3]))
					$caption = $options[3];

				if (!empty($align) || !empty($caption))
					$code = '<div' . (!empty($align) ? $code .= ' style="float: ' . $align . '; clear: ' . $align . '"' : '') . '>';

				$code .= '<a href="' . wiki_get_url(wiki_urlname($groups[1])) . '"><img src="' . wiki_get_url(array('page' => wiki_urlname($groups[1]), 'image')) . '" alt="' . $alt . '"' . $size . ' /></a>';

				if (!empty($align) || !empty($caption))
					$code .= '</div>';

				return $code;
			}

			return '<a href="' . wiki_get_url(wiki_urlname($groups[1])) . '"><img src="' . wiki_get_url(array('page' => wiki_urlname($groups[1]), 'image')) . '" alt="" /></a>';
		}
		else
		{
			$class = array();

			if ($page_info['id'] === null)
				$class[] = 'redlink';

			if (empty($groups[3]))
				$link = '<a href="' . wiki_get_url(wiki_urlname($groups[1])) . '"' . (!empty($class) ? ' class="'. implode(' ', $class) . '"' : '') . '>' . read_urlname($groups[1]) . $groups[4] . '</a>';
			else
				$link = '<a href="' . wiki_get_url(wiki_urlname($groups[1])) . '"' . (!empty($class) ? ' class="'. implode(' ', $class) . '"' : '') . '>' . $groups[3] . $groups[4] . '</a>';

			return $link . $groups[5];
		}
	}
}

// Callback for making wikilinks
function wikilink_callback($groups)
{
	if (empty($groups[3]))
		$link = '<a href="' . wiki_get_url(wiki_urlname($groups[1])) . '">' . read_urlname($groups[1]) . $groups[4] . '</a>';
	else
		$link = '<a href="' . wiki_get_url(wiki_urlname($groups[1])) . '">' . $groups[3] . $groups[4] . '</a>';

	return $link . $groups[5];
}

?>