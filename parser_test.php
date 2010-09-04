<?php

$db_show_debug=true;
require_once('./SSI.php');
require_once($sourcedir . '/Wiki.php');

loadWiki();

$tests = array(
"{{#IF:{{DISPLAYTITLE}}|yes|no}}<br/><br /><br/><br />
Should be yes: {{#IF: {{DISPLAYTITLE}} |yes|no}}<br/><br /><br/><br />
Should be yes: {{#IF:
	{{DISPLAYTITLE}}
	|yes|no}}<br/><br /><br/><br />

{{DISPLAYTITLE:aaa}}<br /><br />[[Main_Page]]. [[Main_Page|click here]]"
);

ini_set('xdebug.var_display_max_depth', 10);

foreach ($tests as $test)
{
	$page = new WikiPage(array('id' => ''), 'test_page');
	$parser = new WikiParser($page);

	$parser->parse(str_replace(array("\r","\n"), '', $test));

	echo '
	<h2>Org</h2>
	<pre>', $test, '</pre>
	<h2>Parsed</h2>
	<pre>', var_dump($parser->tableOfContents), '</pre>';
;
}

?>