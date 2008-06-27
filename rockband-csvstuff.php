<?php

	define("MIDIPATH", "mids/");
	define("OUTDIR", "charts/rb/");

	require_once "parselib.php";
	require_once "notevalues.php";
	require_once "songnames.php";

    $DIFFICULTIES = array("easy", "medium", "hard", "expert");

    if (isset($argv[1]) && $argv[1] == "--help") do_help();
    if (isset($argv[1]) && $argv[1] == "--version") do_version();


    $files = array();
    
    $dir = opendir(MIDIPATH . "rb/");
    while (false !== ($file = readdir($dir))) {
        if ($file == "." || $file == "..") continue;
        if (substr($file, -11) == ".parsecache") continue;
        if (substr($file, 0, 1) == "_") continue;
        $files[] = $file;
    }
    
    $dir = opendir(MIDIPATH . "rb/");
    if ($dir === false) die("Unable to open directory " . MIDIPATH . "rb/ for reading.\n");
    
    umask(0);
    
    if (!file_exists(OUTDIR . "csv-scores/")) {
        if (!mkdir(OUTDIR . "csv-scores/", 0777, true)) die("Unable to create output directory " . OUTDIR . "csv-scores/\n");
    }
    
    
    $idx = array();
    $ind["fullband"] = null;
    if (false === ($idx["fullband"] = fopen(OUTDIR . "csv-scores/index.html", "w"))) {
        die("Unable to open file " . OUTDIR . "index.html for writing.\n");
    }

    $idx["streak"] = null;
    if (false === ($idx["streak"] = fopen(OUTDIR . "fc_note_streaks.csv", "w"))) {
        die("Unable to open file " . OUTDIR . "fc_note_streaks.csv for writing.\n");
    }
    
    $idx["bonuses"] = null;
    if (false === ($idx["bonuses"] = fopen(OUTDIR . "bonuses.csv", "w"))) {
        die("Unable to open file " . OUTDIR . "bonuses.csv for writing.\n");
    }

    
    index_header($idx["fullband"], "Full Band .csv scores");
    
    // open the tables
    fwrite($idx["fullband"], "<table border=\"1\">");
    fwrite($idx["streak"], "short_name,guitar_easy,guitar_medium,guitar_hard,guitar_expert,bass_easy,bass_medium,bass_hard,bass_expert\n");
    fwrite($idx["bonuses"], "short_name,easy_solos,medium_solos,hard_solos,expert_solos,big_rock_ending\n");


    echo "Making .csv files for " . count($files) . " files...\n";
    
    foreach ($files as $i => $file) {
        $shortname = substr($file, 0, strlen($file) - 4);
        echo "File " . ($i + 1) . " of " . count($files) . " ($shortname) [parsing]";
        
    	list ($songname, $events, $timetrack, $measures, $notetracks, $vocals, $beat) = parseFile(MIDIPATH . "rb/" . $file, "rb");
    	if ($CACHED) echo " [cached]";
    	    	
    	$realname = (isset($NAMES[$songname]) ? $NAMES[$songname] : $songname);
    	echo " ($realname)";


        // csv full band scores
        echo " [csvscores]";
        fwrite($idx["fullband"], "<tr><td>" . $realname . "</td>");
        foreach ($DIFFICULTIES as $diff) {
            echo " ($diff)";

            $csv = null;
            if (false === ($csv = fopen(OUTDIR . "csv-scores/" . $shortname . "_fullband_" . $diff . "_scores.csv", "w"))) {
                die("Unable to open file " . OUTDIR . "csv-scores/" . $shortname . "_fullband_" . $diff . "_scores.csv for writing.\n");
            }
            
            fwrite($csv, "meas,vocals,guitar,bass,drums,base,,vocals mult,guitar mult,bass mult,drums mult,mult,16 BEAT,24 BEAT,32 BEAT\n");
            for ($i = 0; $i < count($measures["guitar"]); $i++) {
                fprintf($csv, "%d,0,%d,%d,%d,=SUM(B%d:E%d),,=4*B%d,=4*C%d,=6*D%d,=4*E%d,=SUM(H%d:K%d),,,\n", $i+1, 
                        $measures["guitar"][$i]["mscore"][$diff], $measures["bass"][$i]["mscore"][$diff],
                        $measures["drums"][$i]["mscore"][$diff], $i+2, $i+2, $i+2, $i+2, $i+2, $i+2, $i+2, $i+2);
            }
            
            fclose($csv);
            
            fwrite($idx["fullband"], "<td><a href=\"" . $shortname . "_fullband_" . $diff . "_scores.csv\">" . $diff. "</a></td>");
        } // csv scores diffs
        fwrite($idx["fullband"], "</tr>\n");
        
        
        // fc note streaks scores
        fwrite($idx["streak"], $songname);

        // guitar
        echo " [fcstreaks guitar]";
        foreach ($DIFFICULTIES as $diff) {
            echo " ($diff)";
            $streak = $measures["guitar"][count($measures["guitar"])-1]["streak"][$diff];
            fwrite($idx["streak"], "," . $streak);
        } // guitar diffs

        // bass
        echo " [fcstreaks bass]";
        foreach ($DIFFICULTIES as $diff) {
            echo " ($diff)";
            $streak = $measures["bass"][count($measures["bass"])-1]["streak"][$diff];
            fwrite($idx["streak"], "," . $streak);
        } // bass diffs

/*
        // vocals
        echo " [vocals]";
        $last = -1;
        $streak = 0;
        foreach ($events["vocals"] as $e) {
            if (!($e["type"] == "p1" || $e["type"] == "p2")) continue;
            if (($e["type"] == "p1" || $e["type"] == "p2") && $e["start"] > $last) {
                $last = $e["start"];
                $streak++;
            }
        } // vocal events
        fwrite($idx, "," . $streak);
*/
        fwrite($idx["streak"], "\n");
        // / fc note streaks scores
        
        
        // bonuses
        fwrite($idx["bonuses"], $songname);
        
        // guitar solos
        echo " [solo bonuses]";
        foreach ($DIFFICULTIES as $diff) {
            echo " ($diff)";
            $solonotes = 0;
            foreach ($events["guitar"] as $e) {
                if ($e["type"] == "solo" && $e["difficulty"] == $diff) {
                    $solonotes += $e["notes"];
                }
            }
            fwrite($idx["bonuses"], "," . $solonotes);
        } // solos diffs

        // big rock ending
        echo " [big rock ending]";
        $brescore = 0;
        foreach ($events["guitar"] as $e) {
            if ($e["type"] != "bre") continue;
            $brescore = $e["brescore"];
            break;
        }
        fwrite($idx["bonuses"], "," . $brescore . "\n");
        
        echo "\n";
    } // foreach file


    // close the files
    fwrite($idx["fullband"], "</table>\n</body>\n</html>");
    fclose($idx["fullband"]);
    fclose($idx["streaks"]);
    fclose($idx["bonuses"]);

    exit;


    function index_header($fhand, $title) {
        fwrite($fhand, "<html>\n<head>\n<title>Blank Charts for Rock Band $title</title>\n</head>\n");
        fwrite($fhand, <<<EOT
<body>
<p>These files are meant to assist full-band pathing using the spreadsheet method. They contain the per-measure scores for <strike>vocals</strike> (not yet, working on it!), guitar, bass, and drums. Unfortunately, I did not have my code set up to easily be able to determine the multiplier score per measure without redoing the score calculation code. I'll try to address this at some point, but for now you'll have to go through and re-work the first few measures per instrument to get the right multiplier score.</p>
<p>These are .csv (comma-seperated value) files, which every spreadsheet program should be able to open. You will have to re-save it in your program's native format to add colors to cells. Depending on your system configuration, clicking the links may open the file directly in your spreadsheet program; you probably have to right-click and Save As... to save to your hard drive.</p>
<p>They have not been verified against the game and may be faulty. If you see something horribly wrong please <a href="http://rockband.scorehero.com/forum/privmsg.php?mode=post&u=52545">send me a message</a> on ScoreHero.</p>
<p>They are in alphabetical order by .mid file name (this normally doesn't mean anything, but "the" is often left out). Probably easier to find a song this way anyway.</p>
EOT
);
    }


    function do_help() {
        // TODO
        exit;
    }

    
    function do_version() {
        // TODO
        exit;
    }

?>