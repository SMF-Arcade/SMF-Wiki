<?php
/**********************************************************************************
* WikiParser.php                                                                  *
***********************************************************************************
* SMF Wiki                                                                        *
* =============================================================================== *
* Software Version:           SMF Wiki 0.2                                        *
* Software by:                Niko Pahajoki (http://www.madjoki.com)              *
* Copyright 2008-2010 by:     Niko Pahajoki (http://www.madjoki.com)              *
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

// Class for WikiPage
class WikiPage
{
	// Basic information about page
	public $page;
	public $title;
	public $namespace;
	
	// Status
	public $status;
	
	// Raw content
	public $raw_content;
	public $id_file;
	
	// Preparsed content
	private $preParsedContent;
	public $parameters;
	
	// Parser temporary
	public $currentSection;
	
	// Parsed content
	public $tableOfContents;
	public $sections;
	public $pageVariables;
	public $pageSettings;

	// Categories this page is assigned to
	public $categories = array();
	
	// Some settings
	private $mode = 'normal'; 
	public $parse_bbc = true;
	
	// Contains array parser errors
	private $parserErrors = array();
	
	// Little helper for parsing cerating parts like templatenames
	public $fakeStatus = array('paragraphOpen' => false, 'can_paragraph_open' => false);
	
	// Inline Tags
	public $inlineTags = array();
	// Block level tags
	public $blockTags = array(
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
	
	// Rules for tags
	public $rules = array(
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
		'#' => array(
			'close' => "\n",
			'min' => 1,
			'max' => 1,
			'names' => array(
				1 => 'hash_tag',
			),
			'no_param' => true,
			'has_name' => true,
		),
	);
	
	function __construct($page_info, $namespace, $content, $include = false)
	{
		$this->page = $page_info['name'];
		$this->title = $page_info['title'];
		$this->namespace = $namespace;
		$this->raw_content = $content;
		
		$this->page_tree = array();
		
		// Mode
		$this->mode = $include ? 'include' : 'normal';
		
		// Reset status
		$this->status = array();
	}
	
	// Helper for parser function, done like this so settings can be edited before parsing
	function parse($is_template = false)
	{
		// Preparsing
		$this->preParsedContent = $this->__preprocess($this->raw_content, $is_template);
		
		// Do actual parsing
		$this->__parse($is_template);
		
		// Make TOC
		$this->tableOfContents = $this->__parseTableOfContent($this->sections);
	}
	
	// Add file to this page
	function addFile($id_file)
	{
		$this->id_file = $id_file;
	}

	// This gives template code for including page
	function getTemplateCode($parameters)
	{
		$this->parameters = $parameters;
		unset($parameters);
		
		$currentHtml = '';
		
		$this->__parse(true);
		
		foreach ($this->sections as $section)
		{
			if ($section['level'] != 1)
				$currentHtml .= (!empty($currentHtml) ? '<br />' : '') . str_repeat('=', $section['level']) . ' ' . $section['name'] . ' ' . str_repeat('=', $section['level']) . '<br />';
			
			$currentHtml .= $section['html'];
		}
		
		return $currentHtml;
	}
	
	// Compare
	function compareTo($wikiparser)
	{
		return $this->__diff(explode("\n", un_preparsecode($wikiparser->raw_content)), explode("\n", un_preparsecode($this->raw_content)));
	}

	/*
			Paul's Simple Diff Algorithm v 0.1
			(C) Paul Butler 2007 <http://www.paulbutler.org/>
			May be used and distributed under the zlib/libpng license.
	
			This code is intended for learning purposes; it was written with short
			code taking priority over performance. It could be used in a practical
			application, but there are a few ways it could be optimized.
	
			Given two arrays, the function diff will return an array of the changes.
			I won't describe the format of the array, but it will be obvious
			if you use print_r() on the result of a diff on some test data.
	
			htmlDiff is a wrapper for the diff command, it takes two strings and
			returns the differences in HTML. The tags used are <ins> and <del>,
			which can easily be styled with CSS.
	*/

	private function __diff($old, $new)
	{
		$maxlen = 0;
	
		foreach($old as $oindex => $ovalue)
		{
			$nkeys = array_keys($new, $ovalue);
			foreach($nkeys as $nindex)
			{
				$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
					$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
				if ($matrix[$oindex][$nindex] > $maxlen)
				{
					$maxlen = $matrix[$oindex][$nindex];
					$omax = $oindex + 1 - $maxlen;
					$nmax = $nindex + 1 - $maxlen;
				}
			}
		}
	
		if ($maxlen == 0)
			return array(
				array('d' => $old, 'i'=> $new)
			);
	
		return array_merge(
			$this->__diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
			array_slice($new, $nmax, $maxlen),
			$this->__diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen))
		);
	}

	// Main parser function
	private function __parse($is_template = false)
	{
		global $context, $txt;
		
		$this->sections = array();
		
		$this->status['paragraphOpen'] = isset($this->status['paragraphOpen']) ? $this->status['paragraphOpen'] : false;
		$this->status['can_paragraph_open'] = true;
		
		$htmlIds = array();
		
		foreach ($this->preParsedContent as $section)
		{
			$html_id = $this->__make_html_id($section['name']);
			
			$i = 1;
			
			// Make sure html_id is unique in page context
			while (in_array($html_id, $htmlIds))
				$html_id = $this->__make_html_id($section['name'] . '_'. $i++);
			$htmlIds[] = $html_id;
				
			$this->currentSection = count($this->sections);
			$this->sections[] = array(
				'id' => $html_id,
				'name' => $section['name'],
				'level' => $section['level'],
				'edit_url' => wiki_get_url(array('page' => $this->page, 'sa' => 'edit', 'section' => $this->currentSection)),
				'html' => '',
			);
			
			if (empty($section['part']))
				continue;

			// Make reference, since it's easier to read this way
			$currentHtml = &$this->sections[$this->currentSection]['html'];
					
			foreach ($section['part'] as $part)
			{
				if (!$is_template)
					$this->status['can_paragraph_open'] = !empty($part['is_paragraph']);
				
				// Check if paragraph should be closed
				$this->__paragraph_handler($this->status, $currentHtml, 'check');
				
				// Parse section to html code
				$currentHtml .= $this->__parse_part($this->status, $part['content']);
				
				// Close paragaph if open
				$this->__paragraph_handler($this->status, $currentHtml, 'close');			
			} // END part of section
			
			unset($currentHtml);
		} // END section
	}
	
	// Parser parts of paragraph
	public function __parse_part(&$status, &$content, $condition_test = false)
	{
		global $smcFunc, $context, $txt;
		
		$currentHtml = '';
			
		foreach ($content as $item)
		{
			if (is_string($item))
			{
				$this->__paragraph_handler($status, $currentHtml, 'open');

				$currentHtml .= $item;
				
				continue;
			}
			// Behaviour Switch
			elseif ($item['name'] == 'behaviour_switch')
			{
				if (isset($context['wiki_parser_extensions']['behaviour_switch'][strtolower($item['switch'])]))
					$context['wiki_parser_extensions']['behaviour_switch'][strtolower($item['switch'])]($this, $item['switch']);
				else
				{
					$did_open = $this->__paragraph_handler($status, $currentHtml, 'open');
					$currentHtml .= (!empty($item['lineStart']) && !$did_open ? '<br />' : '') . '__' . $item['switch'] . '__' . (!empty($item['lineEnd']) ? '<br />' : '');
				}
			}
			elseif ($item['name'] == 'variable')
			{
				$return = null;
				
				// May it take parameter?
				if (!empty($item['firstParam']) && $context['wiki_parser_extensions']['variables'][$item['var_parsed']][1])
					$return = $context['wiki_parser_extensions']['variables'][$item['var_parsed']][0]($this, $item['var_parsed'], trim(str_replace(array('<br />', '&nbsp;'), array("\n", ' '), !empty($item['firstParam']) ? $this->__parse_part($this->fakeStatus, $item['firstParam']) : '')));
				elseif (empty($item['firstParam']))
					$return = $context['wiki_parser_extensions']['variables'][$item['var_parsed']][0]($this, $item['var_parsed']);
					
				if ($return !== false && $return !== true)
				{
					$did_open = $this->__paragraph_handler($status, $currentHtml, 'open');
					$currentHtml .= (!empty($item['lineStart']) && !$did_open ? '<br />' : '') . $return . (!empty($item['lineEnd']) ? '<br />' : '');
				}
			}
			// Parser functions like {{#if}}
			elseif ($item['name'] == 'function')
			{
				$did_open = $this->__paragraph_handler($status, $currentHtml, 'open');
				$currentHtml .= (!empty($item['lineStart']) && !$did_open ? '<br />' : '') .
					$context['wiki_parser_extensions']['functions'][$item['var_parsed']][0]($this, $item) .
					(!empty($item['lineEnd']) ? '<br />' : '');
			}
			// Replace templates
			elseif ($item['name'] == 'template')
			{
				// Replace entities and trim (if you have linebreak after template name it would use it in name otherwise)
				$firstParam = trim(str_replace(array('<br />', '&nbsp;'), array("\n", ' '), $this->__parse_part($this->fakeStatus, $item['firstParam'])));
			
				list ($namespace, $page) = wiki_parse_url_name($firstParam, true);

				// TODO: Make Template special namespace
				if (empty($namespace))
					$namespace = 'Template';
					
				$template_info = cache_quick_get('wiki-pageinfo-' .  wiki_cache_escape($namespace, $page), 'Subs-Wiki.php', 'wiki_get_page_info', array($page, $context['namespaces'][$namespace]));

				if ($template_info['id'] !== null)
				{
					$templatePage = cache_quick_get(
						'wiki-page-include-' . $template_info['id'] . '-rev' . $template_info['current_revision'],
						'Subs-Wiki.php', 'wiki_get_page_content',
						array($template_info, $context['namespaces'][$namespace], $template_info['current_revision'], true)
					);
					
					$templatePage->status = $this->status;
					$currentHtml .= (!empty($item['lineStart']) ? '<br />' : '') . $templatePage->getTemplateCode($item['params']) . (!empty($item['lineEnd']) ? '<br />' : '');
					$this->status = $templatePage->status;
					
					$this->categories += $templatePage->categories;
				}
				else
				{
					$did_open = $this->__paragraph_handler($status, $currentHtml, 'open');
					$currentHtml .= (!empty($item['lineStart']) && !$did_open ? '<br />' : '') . '<span style="color: red">' . sprintf($txt['template_not_found'], (!empty($namespace) ? $namespace . ':' . $page : $page)). '</span>' . (!empty($item['lineEnd']) ? '<br />' : '');					
				}
			}
			// Replace parameters
			elseif ($item['name'] == 'template_param')
			{
				// Replace entities and trim (if you have linebreak after template name it would use it in name otherwise)
				$param = trim(str_replace(array('<br />', '&nbsp;'), array("\n", ' '), $this->__parse_part($this->fakeStatus, $item['firstParam'])));
			
				if (isset($this->parameters[$param]))
					$currentHtml .= (!empty($item['lineStart']) ? '<br />' : '') . $this->__parse_part($status, $this->parameters[$param]) . (!empty($item['lineEnd']) ? '<br />' : '');
				elseif (!$condition_test)
				{					
					$did_open = $this->__paragraph_handler($status, $currentHtml, 'open');
					$currentHtml .= (!empty($item['lineStart']) && !$did_open ? '<br />' : '') . str_repeat($item['opening_char'], $item['len']);
					$currentHtml .= $this->__parse_part($status, $item['firstParam']);
					$currentHtml .= str_repeat($item['closing_char'], $item['len']) . (!empty($item['lineEnd']) ? '<br />' : '');
				}
			}
			elseif ($item['name'] == 'wikilink')
			{
				$parsedPage = $this->__parse_part($this->fakeStatus, $item['firstParam']);
				
				list ($linkNamespace, $linkPage) = wiki_parse_url_name($parsedPage, true);
		
				$realLink = wiki_get_url_name($linkPage, $linkNamespace);
		
				$link_info = cache_quick_get('wiki-pageinfo-' .  wiki_cache_escape($linkNamespace, $linkPage), 'Subs-Wiki.php', 'wiki_get_page_info', array($linkPage, $context['namespaces'][$linkNamespace]));
				
				if ($linkNamespace == $context['namespace_images']['id'] && $link_info['id'] !== null)
				{
					if (!empty($item['params']))
					{
						$align = '';
						$size = '';
						$caption = '';
						$alt = '';
		
						// Size
						if (!empty($item['params'][1]))
						{
							$size = $this->__parse_part($this->fakeStatus, $item['params'][1]);
							
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
						if (!empty($item['params'][2]))
						{
							$align = trim($this->__parse_part($this->fakeStatus, $item['params'][2]));
							$align = ($align == 'left' || $align == 'right') ? $align : '';
						}
		
						// Alt
						if (!empty($item['params'][3]))
							$alt = $this->__parse_part($this->fakeStatus, $item['params'][3]);
		
						// Caption
						if (!empty($item['params'][4]))
							$alt = $this->__parse_part($this->fakeStatus, $item['params'][4]);
								
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

							$currentHtml = '<div' . (!empty($class) ? ' class="' . implode(' ', $class) . '"' : '') . (!empty($style) ? ' style="' . implode('; ', $style) . '"' : '') . '>
								<span class="topslice"><span></span></span>
								<div style="padding: 5px">';
								
						}
						
						$currentHtml .= '<a href="' . wiki_get_url($realLink) . '"><img src="' . wiki_get_url(array('page' => $realLink, 'image')) . '" alt="' . $alt . '"' . (!empty($caption) ? ' title="' . $caption . '"' : '') . $size . ' /></a>';
		
						if (!empty($align) || !empty($caption))
							$currentHtml .= (!empty($caption) ? '<span style="text-align: center">' . $caption . '</span>' : '') . '
								</div>
								<span class="botslice"><span></span></span>
							</div>';
					}
					else
						$currentHtml .= (!empty($item['lineStart']) ? '<br />' : '') . '<a href="' . wiki_get_url($realLink) . '"><img src="' . wiki_get_url(array('page' => $realLink, 'image')) . '" alt="" /></a>' . (!empty($item['lineEnd']) ? '<br />' : '');
				}
				elseif ($parsedPage[0] !== ':' && $linkNamespace == $context['namespace_category']['id'])
				{
					$this->categories[$realLink] = array(
						'id' => $link_info['id'],
						'link' => wiki_get_url($realLink),
						'namespace' => $linkNamespace,
						'title' => $linkPage,
						'name' => get_default_display_title($linkPage, false),
						'exists' => $link_info['id'] !== null,
					);
				}
				else
				{
					$did_open = $this->__paragraph_handler($status, $currentHtml, 'open');
					
					$class = array();
		
					if ($link_info['id'] === null)
						$class[] = 'redlink';
						
					$currentHtml .= (!empty($item['lineStart']) && !$did_open ? '<br />' : '') . '<a href="' . wiki_get_url($realLink) . '"' . (!empty($class) ? ' class="'. implode(' ', $class) . '"' : '') . '>';
		
					if (isset($item['params'][1]))
						$currentHtml .= $this->__parse_part($this->fakeStatus, $item['params'][1]);
					else
						$currentHtml .= get_default_display_title($parsedPage, false);
						
					$currentHtml .= '</a>' . (!empty($item['lineEnd']) ? '<br />' : '');
				}				
			}
			elseif ($item['name'] == 'tag')
				$currentHtml .= $context['wiki_parser_extensions']['tags'][$item['tag_name']][0]($this, $item['content'], $item['attributes']);
			elseif ($item['name'] == 'hash_tag')
			{
				if (!$context['wiki_parser_extensions']['hash_tags'][$item['item_name']]($this, $item['firstParam'], $item['params']))
				{
					$did_open = $this->__paragraph_handler($status, $currentHtml, 'open');
					$currentHtml .= (!empty($item['lineStart']) && !$did_open ? '<br />' : '') . str_repeat($item['opening_char'], $item['len']);
					$currentHtml .= $this->__parse_part($status, $item['firstParam']);
					$currentHtml .= (!empty($item['lineEnd']) ? '<br />' : '');
				}
			}
			else
				$this->__logError('Unable to parse item!', array($item));
		} // END content of part
		
		return $currentHtml;
	}
	
	// Log error during parsing
	private function __logError($error, $data)
	{
		$this->parserErrors[] = array($error, $data);
	}
	
	// Make table of content from list of sections
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
			if ($section['level'] == $tlevel)
			{
				if (!empty($stack[0]))
					$mainToc[] = array(
						'id' => $stack[0]['id'],
						'level' => $num,
						'name' => $stack[0]['name'],
						'sub' => !empty($stack[1]) ? $this->__parseTableOfContent($stack[1], false, $tlevel + 1) : array(),
					);
	
				$stack = array(
					$section,
					array()
				);
	
				$num++;
			}
			elseif ($section['level'] >= $tlevel)
				$stack[1][] = $section;
		}
	
		if (!empty($stack[0]))
			$mainToc[] = array(
				'id' => $stack[0]['id'],
				'level' => $num,
				'name' => $stack[0]['name'],
				'sub' => !empty($stack[1]) ? $this->__parseTableOfContent($stack[1], false, $tlevel + 1) : array(),
			);
	
		return $mainToc;
	}
	
	// This function makes html id (anchor) from section name
	private function __make_html_id($name)
	{
		global $smcFunc;
		
		$name = str_replace(array('%3A', '+', '%'), array(':', '_', '.'), urlencode(un_htmlspecialchars($name)));
		
		while($name[0] == '.')
			$name = substr($name, 1);
		return $name;
	}
	
	// Opens and closes paragraph based on request if needed and possible
	private function __paragraph_handler(&$status, &$currentHtml, $mode = 'open')
	{
		if (!$this->parse_bbc)
			return;
		
		// Close if open and not allowed to be open
		if ($status['paragraphOpen'] && !$status['can_paragraph_open'])
		{
			$currentHtml .= '</p>';
			$status['paragraphOpen'] = false;
			
			return false;
		}
		// Open if asked to and possible
		elseif ($mode == 'open' && !$status['paragraphOpen'] && $status['can_paragraph_open'])
		{
			$currentHtml .= '<p>';
			$status['paragraphOpen'] = true;
			
			return true;
		}
		// Close
		elseif ($mode == 'close' && $status['paragraphOpen'])
		{
			$currentHtml .= '</p>';
			$status['paragraphOpen'] = false;
			
			return false;
		}
		
		return false;
	}
	
	// Cleans first and last linebreaks to make sure there won't be empty paragraphs
	private function __paragraphClean(&$text)
	{	
		if (strlen($text) >= 6 && substr($text, 0, 6) == '<br />')
			$text = substr($text, 6);
			
		if (strlen($text) >= 6 && substr($text, -6) == '<br />')
			$text = substr($text, 0, -6);
			
		if (trim($text) == '')
			return '';
		
		return $text;
	}

	// Preprocesses page
	private function __preprocess($text, $is_template = false)
	{
		global $context;
		
		$stringTemp = '';
		$i = 0;
		
		$parseSections = true;

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
		
		$text = str_replace(array("\r\n", "\r", '<br />', '<br>'), "\n", $text);
		
		if (substr($text, -1) != "\n")
			$text .= "\n";

		$stack = array();
		$piece = null;

		$searchBase = "<[{#\n";

		$textLen = strlen($text);

		$sections = array();

		$section = array('name' => '(root)', 'level' => 1, 'parts' => array());

		$blockLevelNesting = 0;

		$paragraph = array();
		$can_paragraph = true;
		$is_paragraph = true;

		while (true)
		{
			$charType = '';
			$search = $searchBase;
			$closeTag = '';

			$stackIndex = count($stack) - 1;

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

			if ($skip > 0 && empty($stack))
			{
				$stringTemp .= substr($text, $i, $skip);
				$i += $skip;
			}
			elseif ($skip > 0)
			{
				$stack[$stackIndex]['current_param'][] = substr($text, $i, $skip);
				$i += $skip;
			}
			
			// nowiki tag
			if ($this->parse_bbc && substr($text, $i, 8) == '<nowiki>')
			{
				$i += 8;
				
				$endPos = strpos($text, '</nowiki>', $i);
	
				if ($endPos > 0 && empty($stack))
				{
					$stringTemp .= substr($text, $i, $endPos - $i);
					$i = $endPos + 9;
				}
				elseif ($endPos > 0)
				{
					$stack[$stackIndex]['current_param'][] = substr($text, $i, $endPos - $i);
					$i = $endPos + 9;
				}
			}
			// <includeonly>
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
			// <noinclude> reverse of <includeonly>
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
				
			if ($i >= $textLen && (empty($stack) || $closeTag != "\n"))
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
					$rule = $this->rules[$curChar];
					$charType = 'open';
				}
				// Parameter delimiter
				elseif ($curChar == '|')
					$charType = 'pipe';
				// Function delimiter / variable value delimeter
				elseif ($curChar == ':')
					$charType = 'fdelim';
				elseif ($parseSections && ($i == 0 || $text[$i - 1] == "\n") && $curChar == '=')
					$charType = 'new-section';
				// There might be block level closing tag
				elseif ($parseSections && ($text[$i - 1] == '>') && $curChar == '=')
				{
					$pos = strrpos(substr($text, 0, $i - 1), '<');
					$tag = substr($text, $pos, $i - $pos);
					
					if (isset($this->blockTags[$tag]))
						$charType = 'new-section';
				}
				// Start or end of tag
				elseif ($this->parse_bbc && $curChar == '<')
				{
					$tagLen = strcspn($text, ' >', $i + 1);
					$tag = '<' . substr($text, $i + 1, $tagLen) . '>';
					
					if (isset($this->blockTags[$tag]))
					{
						if ($this->blockTags[$tag] === false)
						{
							$charType = 'new-paragraph-special';
							$can_paragraph = false;
							
							$blockLevelNesting++;
						}
						elseif (!$can_paragraph)
						{
							$blockLevelNesting--;
							
							$charType = '';
							$can_paragraph = $blockLevelNesting == 0;
							
							$stringTemp .= $tag;
							
							$i += $tagLen + 2;
							
							continue;
						}
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
				elseif ($this->parse_bbc && $this->status['can_paragraph_open'] && $curChar == "\n" && $text[$i + 1] == "\n")
				{
					$charType = 'new-paragraph';

					$i += 2;
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
			}

			if ($charType == 'new-paragraph' || $charType == 'new-paragraph-special' || ($can_paragraph != $is_paragraph))
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
					$this->__paragraphClean($stringTemp);
					
					if (!empty($stringTemp))
						$paragraph[] = $stringTemp;
					if (!empty($paragraph))
						$section['part'][] = array(
							'is_paragraph' => $is_paragraph,
							'content' => $paragraph,
						);
					if (!empty($section))
						$sections[] = $section;
					$stringTemp = '';
					$paragraph = array();
					
					$is_paragraph = $can_paragraph;

					$name = trim(substr($header, $c, -$c2));

					$section = array(
						'name' => $name,
						'level' => $c,
						'parts' => array(),
					);
								
					$i += $len;
					
					continue;
				}
				// Not header
				else
				{
					//$i--;
					$charType = '';
				}
			}
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
			}
			elseif ($charType == '' && $curChar == "\n" && empty($stack))
			{
				$stringTemp .= '<br />';
				$i++;
			}
			elseif ($charType == '' && empty($stack))
			{
				$stringTemp .= $curChar;
				$i++;
			}
			elseif ($charType == '' && $curChar == "\n")
			{
				$stack[$stackIndex]['current_param'][] = '<br />';
				$i++;
			}
			elseif ($charType == '')
			{
				$stack[$stackIndex]['current_param'][] = $curChar;
				$i++;
			}
		}

		$this->__paragraphClean($stringTemp);
		
		if (!empty($stringTemp))
			$paragraph[] = $stringTemp;
		if (!empty($paragraph))
			$section['part'][] = array(
				'is_paragraph' => $is_paragraph,
				'content' => $paragraph
			);
		if (!empty($section))
			$sections[] = $section;

		return $sections;
	}
}

?>