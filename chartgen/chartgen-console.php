<?php

    error_reporting(E_ALL);
	

	define("WIDTH", 1010);
	define("PXPERBEAT", 60);
	define("STAFFHEIGHT", 12);
	define("DRAWPLAYERLINES", 0);
	define("CHARTGENVERSION", "0.0.0");

	require_once "parselib.php";
	require_once "notevalues.php";
	require_once "songnames.php";
	require_once "chartlib.php";
	

    $file = "dontfearthereaper";
    $game = "rb";
    $diff = "expert";
    $instrument = "drums";

/*	
	if (!isset($_GET["file"])) {
		echo "File not specified - Use query string parameter, i.e., chartgen.php?file=danicalifornia (no extension)";
		exit;
	}
	
	$file = preg_replace("-[\./]-", "", $_GET["file"]);
	if (!file_exists("../mids/" . $file . ".mid")) {
	   die("Specified file does not exist.");
	}


	if (!isset($_GET["difficulty"])) {
		echo "Difficulty not specified - Use query string paramenter, i.e., chartgen.php?difficulty=expert";
		exit;
	}
	
	$diff = strtolower($_GET["difficulty"]);
	// don't ask why I typed those out of order
	if (!($diff == "easy" || $diff == "medium" || $diff == "expert" || $diff == "hard")) {
	   die("Invalid difficulty -- specify one of easy, medium, hard, or expert.");
	}
	

	
	if (!isset($_GET["game"])) {
		echo "Game not specified - Use query string parameter, i.e., chartgen.php?game=rb (or gh1, or gh2, or gh3)";
		exit;
	}
	global $game;
	$game = strtolower($_GET["game"]);
	if (!($game == "gh1" || $game == "gh2" || $game == "gh3" || $game == "rb")) {
	   die("Invalid game -- specify one of gh1, gh2, gh3, or rb.");
	}
	$game = strtoupper($game);
	
	
	
	if (!isset($_GET["instrument"])) {
		echo "Game not specified - Use query string parameter, i.e., chartgen.php?instrument=guitar (or bass (will read rhythm charts), or drums (RB), or vox (RB), or coop (GH))";
		exit;
	}
	global $instrument;
	$instrument = strtolower($_GET["instrument"]);
	if ($game == "RB") {
	   if (!($instrument == "guitar" || $instrument == "bass" || $instrument == "drums" || $instrument == "vox")) {
	       die("Invalid instrument for rock band -- specify one of guitar, bass, drums, or vox.");
	   }
	   if ($instrment == "vox") die("Not yet implemented.");
	}
	else {
	   if (!($instrument == "guitar" || $instrument == "bass" || $instrument == "coop")) {
	       die("Invalid instrument for guitar hero -- specify one of guitar, bass (also reads rhythm), or coop (for lead/rhythm songs).");
	   }
	   if ($instrument != "guitar") die ("Not yet implemented.");
	}
	
	*/
	
	list ($measures, $notetrack, $songname) = parseFile("../mids/" . $file . ".mid", strtoupper($diff), strtoupper($game), strtoupper($instrument));
	
	
	$x = 25;
	$y = 75;
	
	// something needs done about evenly matched measures
	for ($i = 0; $i < count($measures) - 1; $i++) {
   	    // this looks really weird doesn't it?
   	   if ($x + PXPERBEAT * $meas["numerator"] > WIDTH - 25) {
	       $x = 25;
	       $y += 110 + 5*DRAWPLAYERLINES;
	   }
	   if ($x + PXPERBEAT * $measures[$i]["numerator"] > WIDTH - 50 && $i != count($measures) - 1) {
	       $x = 25;
	       $y += 110 + 5*DRAWPLAYERLINES;
	   }
	   else {
	       $x += PXPERBEAT * $measures[$i]["numerator"];
	   }
	}
	
	global $HEIGHT;
	$HEIGHT = $y + 125;
	
		
	
	$im = imagecreate(WIDTH, $HEIGHT) or die("Cannot intialized new GD image");
	

	global $black;
	$white = imagecolorallocate($im, 255, 255, 255);
	$black = imagecolorallocate($im, 0, 0, 0);
	$red = imagecolorallocate($im, 255, 0, 0);
	$gray = imagecolorallocate($im, 134, 134, 134);
	imagefill($im, 0, 0, $white);
	
	imagestring($im, 5, 0, 0, (isset($NAMES[$file]) ? $NAMES[$file] : $file), $black);
	imagestring($im, 5, 0, 15, $diff, $black);
	imagestring($im, 5, 0, 30, $instrument, $black);
	imagestring($im, 3, 0, $HEIGHT - 15, "WARNING: Work in progress. This information has NOT BEEN VERIFIED and MAY BE INCORRECT.", $red);
	imagestring($im, 3, WIDTH - 200, $HEIGHT - 27, "Generated by chartgen " . CHARTGENVERSION . ".", $gray);
	imagestring($im, 2, WIDTH - 200, $HEIGHT - 13, "chartlib " . CHARTLIBVERSION . " -- parselib " . PARSELIBVERSION, $gray);
	
	// key
	$solo; if (!$solo) $solo = imagecolorallocate($im, 134, 134, 255);
	$phrase; if (!$phrase) $phrase = imagecolorallocate($im, 134, 134, 134);
	$fill; if (!$fill) $fill = imagecolorallocate($im, 255, 127, 0);
	$player1; if (!$player1) $player1 = imagecolorallocate($im, 255, 0, 0);
	$player2; if (!$player2) $player2 = imagecolorallocate($im, 0, 0, 255);

    imagestring($im, 3, WIDTH-200, 0, "Overline Key", $black);
    imagestring($im, 2, WIDTH-100, 0, "Phrase", $phrase);
    imagestring($im, 2, WIDTH-60, 0, "Solo", $solo);
    imagestring($im, 2, WIDTH-30, 0, "Fill", $fill);
    if (DRAWPLAYERLINES) {
        imagestring($im, 2, WIDTH-100, 15, "Player 1", $player1);
        imagestring($im, 2, WIDTH-50, 15, "Player 2", $player2);
    }
	
	
	$x = 25;
	$y = 75;
	
	foreach($measures as $index => &$meas) {
	   
	   if ($x + PXPERBEAT * $meas["numerator"] > WIDTH - 25) {
	       $x = 25;
	       $y += 110 + 5*DRAWPLAYERLINES;
	   }
	   
	   drawMeasure($im, $x, $y, $meas, $notetrack);

	   if ($x + PXPERBEAT * $meas["numerator"] > WIDTH - 50) {
	       $x = 25;
	       $y += 110 + 5*DRAWPLAYERLINES;
	   }
	   else {
	       $x += PXPERBEAT * $meas["numerator"];
	   }
	   
	}
	
	
	header("Content-type: image/png");
	imagepng($im);
	imagedestroy($im);
	
	
	exit;






?>