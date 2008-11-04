<?php

/*
	This file is for running wiki in standalone mode, where Wiki is not located inside forum.
	Meant for having Wiki inside site rather than inside forum
*/

// Here you can add SSI settings

// Path to SSI
require_once(dirname(dirname(__FILE__)) . '/SSI.php');

// DON'T modify anything below unless you are sure what your doing
require_once($sourcedir . '/Wiki.php');

Wiki(true);

?>