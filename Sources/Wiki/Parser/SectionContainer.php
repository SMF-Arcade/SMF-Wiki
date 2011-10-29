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
class Wiki_Parser_SectionContainer
{
	/**
	 * 
	 */
	protected $sections = array();
	
	/**
	 *
	 */
	protected $parser;
	
	/**
	 *
	 */
	public function __construct(Wiki_Parser $parser)
	{
		$this->parser = $parser;
	}
	
	/**
	 *
	 */
	public function add(Wiki_Parser_Section $section)
	{
		$this->sections[] = $section;
	}
	
	/**
	 *
	 */
	public function addNew($name, $level, $id = null)
	{
		$this->sections[] = new Wiki_Parser_Section($this->parser, $name, $level, $id);
	}
	
	/**
	 * @var Wiki_Parser_Section
	 */
	public function getCurrent()
	{
		return $this->sections[count($this->sections) - 1];
	}
	
	/**
	 * @var Wiki_Parser_Section
	 */
	public function get($num)
	{
		return $this->sections[$num];
	}
	
	/**
	 * @var Wiki_Parser_Section
	 */
	public function getAll()
	{
		return $this->sections;
	}
	
	/**
	 *
	 */
	public function throwContent($type, $content = '', $unparsed = '', $additonal = array())
	{
		$current = $this->getCurrent();
		$current->throwContent($type, $content, $unparsed, $additonal);
	}
	
	/**
	 *
	 */
	public function throwContentArray($array)
	{
		$current = $this->getCurrent();
		foreach ($array as $cont)
			$current->throwContent($cont['type'], $cont['content'], $cont['unparsed'], $cont['additional']);
	}
	
	/**
	 *
	 */
	public function ParseFinalize()
	{
		$this->parser = null;
		foreach ($this->sections as $section)
			$section->ParseFinalize();
	}
}

?>