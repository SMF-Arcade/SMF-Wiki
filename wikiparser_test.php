<?php
$db_show_debug = true;
require_once('SSI.php');
require_once($sourcedir . '/Wiki.php');
LoadWiki();
require_once($sourcedir . '/WikiParser.php');

$page_info = array(
	'title' => 'Main_Page',
);
$namespace = array();

$values = array();

$tests = array(
	array(
		'text' => '[html]<div class="infobox{{#if: {{{color}}}| ibcol{{{color}}}}}">&#13;{{#if:{{{title}}}|<h2>{{{title}}}</h2>}}&#13;{{{1}}}&#13;</div>[/html]',
		'params' => array('color' => 'blue', 'title' => 'Title', 1 => 'noob'),
	),
	'testing [table][tr][td]table!!![/td][/tr][/table]{{InfoBox|Test conent|d|title=Yes|param2=[[test]]}} tests aaa!!!<br />line 2<br /><br />second paragraph<br />test<br /><br />third...<br /><br />=== daa ===',
	'[[Category:Test|test [[Image:test.jpg]]]]',
	'{{Template:Test}}}}',
	'{}',
	'{{}}',
	'[[quote]test[/quote]]',
);

foreach ($tests as $test)
{
	$time = microtime(true);
	
	if (is_string($test))
		$test = array('text' => $test, 'params' => array());
	
	$status = array();

	echo '<hr />
	In:<br />
	<pre>', $test['text'], '</pre><br />
	Out: <br />
	<pre>', var_dump(WikiParser::parse($test['text'], $page_info, $status, 'normal', $test['params'])), '</pre>
	<br /> (took: ', microtime(true) - $time, 's';

	echo '
	<hr />';
}

/*
$content = str_replace("\n", '<br />',
'This is first line
This is second
== Test Section ==
=== Subsection ===
test
=== Nowiki parsing ===
This section should contain "=== Subsection 2 ==="
&lt;nowiki&gt;
=== Subsection 2 ===
&lt;/nowiki&gt;');

for ($i = 0; $i < 100; $i++)
{
	$time = microtime(true);

	$wikiParser = new WikiParser($page_info, $namespace, $content);

	//$wikiParser->parse($content);
	//$result = $wikiParser->pageSections;

	$result = $wikiParser->pageSections;

	$values[] = microtime(true) - $time;
}

echo '
WikiParser tests: <br />
Min: ', min($values), ' s<br />
Max: ', max($values), ' s<br />
Avg: ', array_sum($values) / count($values), ' s<br />
Times: ', count($values), '
<hr /><pre>', var_dump($result), '</pre>';*/

?>