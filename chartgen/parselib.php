<?php

    define("DEBUG", 0);
    define("VERBOSE", 0);
    define("OMGVERBOSE", 0);
	define("PARSELIBVERSION", "0.3.2");

    header("Content-type:text/plain");

    require_once 'notevalues.php';
    require_once '../classes/midi.class.php';
    require_once 'songnames.php';


    function parseFile($file, $difficulty, $game, $instrument) {
        global $songname, $timebase, $CONFIG, $NOTES;
        $songname = "";

        $mid = new Midi;
        $mid->importMid($file);
        $timebase = $mid->getTimebase();
    
        $tracknum = 0;
        $trackname = "";
        switch ($game) {
            case "RB":
                switch ($instrument) {
                    case "GUITAR":
                        $trackname = "PART GUITAR";
                        break;
                    case "BASS":
                        $trackname = "PART BASS";
                        break;
                    case "DRUMS":
                        $trackname = "PART DRUMS";
                        break;
                    case "VOX":
                        $trackname = "PART VOCALS";
                }
                break;
            case "GH1":
                $trackname = "T1 GEMS";     // GH1 only has one instrument
                break;
            default:
                switch ($instrument) {
                    case "GUITAR":
                        $trackname = "PART GUITAR";
                        break;
                    case "BASS":
                        $trackname = "PART BASS";      // check this
                        break;
                    case "COOP":
                        $trackname = "PART GUITAR COOP";      // check this too
                        break;
                }
        }
        
        
        $eventsTrack = 0;
        for ($i = 1; $i < $mid->getTrackCount(); $i++) {
            $temp = $mid->getMsg($i, 0);
            //echo substr($temp, 16); 
            if (substr($temp, 16) == $trackname . "\"") {
                $tracknum = $i;
            }
            if (substr($temp, 16) == "EVENTS\"") {
                $eventsTrack = $i;
            }
        }
        
        $notetrack = filterDifficulty($mid->getTrackTxt($tracknum), $NOTES[$game][$difficulty]);
        $timetrack = parseTimeTrack($mid->getTrackTxt(0));
        $measures = makeMeasureTable($timetrack, $notetrack);
        list($measures, $notetrack) = putNotesInMeasures($measures, $notetrack);
    
        $measures = calcBaseScores($measures, $notetrack, $CONFIG[$game], ($instrument=="DRUMS"), ($instrument=="BASS" && $game=="RB"));
        $measures = getSectionNames($measures, $mid->getTrackTxt($eventsTrack));


        return array($measures, $notetrack, $songname);
    }
    
    
    
function getSectionNames($measures, $eventstrk) {
    
    $events = explode("\n", $eventstrk);
    $mIndex = 0;
    
    foreach ($events as $event) {
        $e = explode(" ", $event);
        if ($e[1] == "Meta" && $e[2] == "Text" && $e[3] == "\"[section") {
            $section = substr($e[4], 0, strlen($e[4]) - 2);
            while ($measures[$mIndex]["time"] < $e[0]) $mIndex++;
            $measures[$mIndex]["section"] = $section;
        }
        
    }
    
    return $measures;
}
    
    

function calcBaseScores($measures, $notetrack, $config, $drums = false, $goesTo6 = false) {
    global $timebase;
    $mult = 1;
    $oldmult = 1;
    $total = 0;
    $streak = 0;
    $over = 0;
    $overChord = 0;
    $overScore = 0;
    
    $totalOverScore = 0;
    
    foreach ($measures as $mindex => &$meas) {
        $meas["number"] = $mindex + 1;
        $mScore = 0;
        
        
        if ($drums) {
            $total = 0;
            // fuck all that other code XD

            // base score with multiplier doesn't really mean anything with drums
            // and there aren't sustains either for rounding issues...
            /*
            for ($i = 0; $i < count($meas["notes"]); $i++) {
                for ($j = 0; $j < count($notetrack[$meas["notes"][$i]]["note"]); $j++) {
                    $streak++;;
                    $oldmult = $mult;
                    if ($streak == $config["multi"][0] || $streak == $config["multi"][1] || $streak == $config["multi"][2]) {
                       // multiplier change
                       $mult++;
                   }
                   if ($goesTo6 && ($streak == $config["multi"][3] || $streak == $config["multi"][4])) {
                       $mult++;
                   }
                   
                   $mScore += $config["gem_score"];
                   $total += $oldmult * $config["gem_score"];
                }
            }
            */
            
            // so just add gem_score * gem count to both scores :)
            for ($i = 0; $i < count($meas["notes"]); $i++) {
                $mScore += $config["gem_score"] * count($notetrack[$meas["notes"][$i]]["note"]);
                if (!$notetrack[$meas["notes"][$i]]["fill"]) {
                    $total += $config["gem_score"] * count($notetrack[$meas["notes"][$i]]["note"]);
                }
            }
            

               
            
        }
        else {
            // take care of leftovers from last measure first
            if ($over > 0) {
                
                $newover = 0;
                $newOverScore = 0;
                $newTotalOverScore = 0;
                if ($over > $meas["numerator"]) {
                    // this sustain goes through the entire measure into the next
                    $newover = $over - $meas["numerator"];
                    $newOverScore = $overScore - ($config["ticks_per_beat"] * $meas["numerator"] * $overChord);
                    $newTotalOverScore = $totalOverScore - ($config["ticks_per_beat"] * $meas["numerator"] * $overChord);
                    $overScore = $config["ticks_per_beat"] * $meas["numerator"] * $overChord;
                    $totalOverScore = $overScore;
                    $over = $meas["numerator"];
                }
                
                $mScore += $overScore;
                $total += $mult * $totalOverScore;
                
                $over = $newover;
                $overScore = $newOverScore;
                $totalOverScore = $newTotalOverScore;
            }
            
        
            for ($i = 0; $i < count($meas["notes"]); $i++) {
                $streak++;
                $note = &$notetrack[$meas["notes"][$i]];
                $oldmult = $mult;
                
                if ($streak == $config["multi"][0] || $streak == $config["multi"][1] || $streak == $config["multi"][2]) {
                    // multiplier change
                    $mult++;
                }
                if ($goesTo6 && ($streak == $config["multi"][3] || $streak == $config["multi"][4])) {
                    $mult++;
                }
                
                $over = 0;
                if (($note["time"] + $note["duration"]) > ($meas["time"] + $timebase*$meas["numerator"])) {
                    $over = (($note["time"] + $note["duration"]) - ($meas["time"] + $timebase*$meas["numerator"]) ) / $timebase;
                }
                
                // measure score
                
                $gems = $config["gem_score"] * count($note["note"]);
                $ticks = floor($config["ticks_per_beat"] * ($note["duration"] / $timebase) + EPS);
                if ($over > 0) {
                    $mTicks = floor($ticks * ($meas["time"] + $timebase*$meas["numerator"] - $note["time"])
                                / $note["duration"]);
                    $overScore = $ticks - $mTicks;
                    $overScore *= ($config["chord_sustain_bonus"] ? count($note["note"]) : 1);
                    $ticks = $mTicks;
                }
                $ticks *= ($config["chord_sustain_bonus"] ? count($note["note"]) : 1);
                $mScore += $gems + $ticks;
                
                            
                // $sustain ? $chordsize * int ( 25 * ($eb-$sb) + $EPS ) : 0;
                
                
                // total score
                
                $totalTicks = floor($config["ticks_per_beat"] * ($note["duration"] / $timebase) + 0.5 + EPS);
                if ($over > 0) {
                    $totalMTicks = floor($totalTicks * ($meas["time"] + $timebase*$meas["numerator"] - $note["time"])
                                        / $note["duration"]);
                    $totalOverScore = $totalTicks - $totalMTicks;
                    $totalOverScore *= ($config["chord_sustain_bonus"] ? count($note["note"]) : 1);
                    $totalTicks = $totalMTicks;
                }
                $totalTicks *= ($config["chord_sustain_bonus"] ? count($note["note"]) : 1);
                $total += ($oldmult * $gems) + ($oldmult * $totalTicks);
                            
                //$mult * ($sustain ? $chordsize * int ( 25 * ($eb-$sb) + 0.5 + $EPS ) : 0);
                
                $overChord = $config["chord_sustain_bonus"] ? count($note["note"]) : 1;
                
            }
        }
        
        $meas["mscore"] = (int)$mScore;
        $meas["cscore"] = (int)$total;
        
    }
    
    
    return $measures;
}



function putNotesInMeasures($measures, $notetrack) {
    global $timebase;
    
    // $index = 0;
    
    $target = count($notetrack);
    
    $last = -1;
    
    foreach ($notetrack as $notekey => $note) {
        
        $index = 0;


        if ($notekey == "TrkEnd") continue;
        if ($notekey != (int)$notekey) continue;
        
        while (is_array($measures[$index]) && $note["time"] >= $measures[$index]["time"]) {
            //echo "measures[$index+1][time] = " . $measures[$index+1]["time"] . "   note[time] = " . $note["time"] . "\n";
            $index++;
        }
        //if (is_array($measures[$index])) $index--;
        $index--;
        
        // should also put the measure number, at least, into the note
        // probably the tempo too
        $notetrack[$notekey]["measure"] = $index;
        
        for ($i = 0; $i < count($measures[$index]["tempos"]); $i++) {
            // find the tempo region we're in
            if (!(is_array($measures[$index]["tempos"][$i+1]))) {
                // this is the last one so we have to be in it
                //$notetrack[$j1]["tempo"] = $measures[$index]["tempos"][$i]["tempo"];
                $notetrack[$notekey]["bpm"] = $measures[$index]["tempos"][$i]["bpm"];
            }
            else {
                // there is still at least one more after this, do some checking
                if ($note["time"] >= $measures[$index]["tempos"][$i]["time"] &&
                    $note["time"] <  $measures[$index]["tempos"][$i+1]["time"]) {
                        $notetrack[$notekey]["tempo"] = $measures[$index]["tempos"][$i]["tempo"];
                        $notetrack[$notekey]["bpm"] = $measures[$index]["tempos"][$i]["bpm"];
                }
            }
        }
        
        
        $measures[$index]["notes"][] = $notekey; // $note;
    }
    
    if (DEBUG) print_r($measures);
    
    return array($measures, $notetrack);
}


function makeMeasureTable($timetrack, $notetrack) {
    $ret = array();
    global $timebase;
    
    $measure = $curTime = 0;
    $sigIndex = $tempoIndex = -1;
    $lastTempo = $timetrack["tempos"][0];
    
    while ($curTime < $notetrack["TrkEnd"]) {
        $duration = 0;
        if (is_array($timetrack["tempos"][$tempoIndex+1]) && is_array($timetrack["sigs"][$sigIndex+1])) {
            // both of them have entries left
            if ($timetrack["sigs"][$sigIndex+1]["time"] <= $timetrack["tempos"][$tempoIndex+1]["time"]) {
                // time sig change before tempo change
                
                if (is_array($timetrack["sigs"][$sigIndex+2])) {
                    // still more time sig changes, so see if the next one is before the next tempo change
                    $duration = (($timetrack["sigs"][$sigIndex+2]["time"] < $timetrack["tempos"][$tempoIndex+1]["time"])
                                    ? $timetrack["sigs"][$sigIndex+2]["time"] : $timetrack["tempos"][$tempoIndex+1]["time"]) - $curTime;
                }
                else {
                    // this is the last time sig change, so the next tempo change is our end
                    $duration = $timetrack["tempos"][($tempoIndex == -1 ? 0 : $tempoIndex)+1]["time"] - $curTime;
                }
                $sigIndex++;
            }
            else {
                // tempo change before time sig change
                
                if (is_array($timetrack["tempos"][$tempoIndex+2])) {
                    // still more tempo changes, so see if the next one is before the next time sig change
                    $duration = (($timetrack["tempos"][$tempoIndex+2]["time"] < $timetrack["sigs"][$sigIndex+1]["time"])
                                    ? $timetrack["tempos"][$tempoIndex+2]["time"] : $timetrack["sigs"][$sigIndex+1]["time"]) - $curTime;
                }
                else {
                    // this is the last tempo change, so the next time sig change is our end
                    $duration = $timetrack["sigs"][$sigIndex+1]["time"] - $curTime;
                }
                $tempoIndex++;
            }
        }
        else if (is_array($timetrack["sigs"][$sigIndex+2])) {
            $duration = $timetrack["sigs"][$sigIndex+1]["time"] - $curTime;
            $sigIndex++;
        }
        else if (is_array($timetrack["tempos"][$tempoIndex+2])) {
            $duration = $timetrack["tempos"][$tempoIndex+2]["time"] - $curTime;
            $lastTempo = $timetrack["tempos"][$tempoIndex+1];
            $tempoIndex++;
        }
        else {
            $duration = $notetrack["TrkEnd"] - $curTime;
        }
        
        $measDur = $timebase * $timetrack["sigs"][$sigIndex]["numerator"];
        $numMeas = $duration / $measDur;
        
        $oldMeasure = $measure;
        for (; $measure < $oldMeasure + $numMeas; $measure++) {
            $ret[$measure]["time"] = $curTime;
            $ret[$measure]["numerator"] = $timetrack["sigs"][$sigIndex]["numerator"];
            $ret[$measure]["denominator"] = $timetrack["sigs"][$sigIndex]["denominator"];
            $ret[$measure]["notes"] = array();
   
            
            $measEnd = $curTime + $measDur;
            $measTempo = 0;

            if (!($timetrack["tempos"][$tempoIndex+1]["time"] == $curTime)) {
                // add the last tempo to this measure since there isn't a tempo change
                // at the beginning of the measure
                $ret[$measure]["tempos"][] = $lastTempo;
            }

            while (is_array($timetrack["tempos"][$tempoIndex+1]) && $timetrack["tempos"][$tempoIndex+1]["time"] < $measEnd) {
                // add this tempo change to the measure
                $ret[$measure]["tempos"][/*$measTempo++*/] = $timetrack["tempos"][$tempoIndex+1];
                $lastTempo = $timetrack["tempos"][$tempoIndex+1];
                $tempoIndex++;
            }
            
            $curTime += $measDur;
        }
    }
        
    if (DEBUG >= 1 && VERBOSE) {
        print_r($ret);
    }
    
    return $ret;
}


function parseTimeTrack($tracktxt) {
    global $songname;
    
    $ret = array();
    $ret["sigs"] = array();
    $ret["tempos"] = array();
    $loop = 0;
    $tempoIndex = -1;
    $sigIndex = -1;
    
    
    $trk = explode("\n", $tracktxt);
    
    foreach ($trk as $line) {
        $loop++;
        $info = explode(" ", $line);
        
        if ($info[1] == "Meta") {
            if ($info[2] == "TrkName") {
                preg_match('/.*\"(.*)\"$/', $line, $matches);
                $songname = $matches[1];
            }
            continue;
        }
        
        if ($info[1] != "Tempo" && $info[1] != "TimeSig") {
            continue;
        }
        
        
        if ($info[1] == "TimeSig") {
            $sigIndex++;
            $ret["sigs"][$sigIndex]["time"] = $info[0];
            $ret["sigs"][$sigIndex]["numerator"] = $info[2][0];
            $ret["sigs"][$sigIndex]["denominator"] = $info[2][2];
        }
        else {
            $tempoIndex++;
            $ret["tempos"][$tempoIndex]["time"] = $info[0];
            $ret["tempos"][$tempoIndex]["tempo"] = $info[2];
            $ret["tempos"][$tempoIndex]["bpm"] = round(60000000/$info[2]);
        }
        
    }
    
    if (DEBUG >= 1 && VERBOSE == 1) {
        var_dump(array_values($ret));
    }
    
    return $ret;
}


function filterDifficulty($tracktxt, $difNotes) {
    
    /* Stuff that will eventually need to be addressed:
    
    5) Valid non-sustained notes must have a corresponding note-off event. If a note endpoint is a second note-on event and the duration of the note is less than 161 pulses, the game considers the note to be an invalid note and it is ignored for all purposes (as exhibited by Cheat on the Church) 
    5) [sic] If a player section note-off event occurs more than 15 (30?) pulses prior to the endpoint of a sustained note, the sustained note is ignored by the game for all purposes, even in single player mode (as exhibited in the solo of You Got Another Thing Comin')

    */
    
    
    $notes = array();
    
    $track = explode("\n", $tracktxt);
    $index = 0;
    $lastRealNote = -1;
    $SP = false;
    $SPphrase = 0;
    $p1 = false;
    $p2 = false;
    $solo = false;
    $fill = false;
    
    foreach ($track as $line) {
        if ($line == "MTrk") continue;
        $info = explode(" ", $line);
        
        if ($info[1] == "Meta") {
            if ($info[2] == "TrkEnd") {
                $notes["TrkEnd"] = (int)$info[0];
            }
            continue;
        }
        
        $note = (int)substr($info[3], 2);
        $vel = (int)substr($info[4], 2);
        
        // filter out stuff for the difficulty we're interested in
        // last bit is hack for RB which has it out of order
        if (($note >= $difNotes["G"] && $note <= $difNotes["P2"]) || $note == $difNotes["STAR"] 
                || ($note >= $difNotes["FILL"]["G"] && $note <= $difNotes["FILL"]["O"])) {
            
            // check for star power
            if ($note == $difNotes["STAR"] && ($info[1] == "On" && $vel >= 100)) {
                 $SP = true;
                 $SPphrase++;
                 if (DEBUG == 2 && VERBOSE) echo "SP phrase $SPphrase start at " . $info[0] . "\n";
            }
            else if ($note == $difNotes["STAR"] && ($info[1] == "Off" || ($info[1] == "On" && $vel == 0))) {
                $SP = false;
                if (DEBUG == 2 && VERBOSE) echo "SP phrase $SPphrase end at " . $info[0] . "\n";
            }
            else if (isset($difNotes["SOLO"]) && $note == $difNotes["SOLO"] && ($info[1] == "On" && $vel >= 100)) {
                // solo section (rock band)
                $solo = true;
            }
            else if (isset($difNotes["SOLO"]) && $note == $difNotes["SOLO"] && ($info[1] == "Off" || ($info[1] == "On" && $vel == 0))) {
                $solo = false;
            }
            else if (is_array($difNotes["FILL"]) && ($note >= $difNotes["FILL"]["G"] && $note <= $difNotes["FILL"]["O"])
                        && ($info[1] == "On" && $vel >= 100)) {
                            // fill section (rock band)
                            $fill = true;
            }
            else if (is_array($difNotes["FILL"]) && ($note >= $difNotes["FILL"]["G"] && $note <= $difNotes["FILL"]["O"])
                        && ($info[1] == "Off" || ($info[1] == "On" && $vel == 0))) {
                            $fill = false;
            }
            
            else { //if ($note != $difNotes["STAR"]) {
                
                // check for player1/player2 stuff
                if ($note == $difNotes["P1"] && ($info[1] == "On" && $vel >= 100)) {
                    $p1 = true;
                    if (DEBUG == 2 && VERBOSE) echo "Player 1 on at " . $info[0] . "\n";
                }
                else if ($note == $difNotes["P1"] && ($info[1] == "Off" || ($info[1] == "On" && $vel == 0))) {
                    $p1 = false;
                    if (DEBUG == 2 && VERBOSE) echo "Player 1 off at " . $info[0] . "\n";
                }
                if ($note == $difNotes["P2"] && ($info[1] == "On" && $vel >= 100)) {
                    $p2 = true;
                    if (DEBUG == 2 && VERBOSE) echo "Player 2 on at " . $info[0] . "\n";
                }
                else if ($note == $difNotes["P2"] && ($info[1] == "Off" || ($info[1] == "On" && $vel == 0))) {
                    $p2 = false;
                    if (DEBUG == 2 && VERBOSE) echo "Player 2 off at " . $info[0] . "\n";
                }
                else  if ($note != $difNotes["P1"] && $note != $difNotes["P2"]) {
                    
                    // see if we already have something at this time index, using the chord window for leniency
                    if (arrayTimeExists($notes, $info[0], CHORD) == false && ($info[1] == "On" && $vel >= 100)) {
                        $index++;
                        $chord = 0;
                    }
                    
                    
                    // regular note
                    if ($info[1] == "On" && $vel >= 100) {
                        if (!isset($notes[$index]["time"])) $notes[$index]["time"] = (int)$info[0];
                        $notes[$index]["count"] = $chord;
                        $notes[$index]["note"][$chord++] = $note;
                        
                        // check to see if last note had an end event
                        // and that this is the first note in the chord
                        if ($chord == 1) {
                            if ($lastRealNote != -1 && !(isset($notes[$lastRealNote]["duration"]))) {
                                
                                if (DEBUG && VERBOSE) {
                                    echo "lastRealNote $lastRealNote index $index lastRealNote duration " . $notes[$lastRealNote]["duration"] . "\n";
                                    echo "lastRealNote time " . $notes[$lastRealNote]["time"] . " info[0] " . $info[0] . "\n";
                                }
                                
                                // no end event, make sure it's at least 161 pulses long
                                //if ($notes[$lastRealNote]["time"] + 161 >= $info[0]) {
                                if ($info[0] - $notes[$lastRealNote]["time"] <= 161) {
                                    // that last note should be ignored!
                                    if (DEBUG && VERBOSE) echo "deleting note (not really)\n";
                                    //unset($notes[$lastRealNote]);
                                }
                                else {
                                    // it's long enough to be a real note
                                    // now see if it's a sustain
                                    //if ($notes[$lastRealNote]["time"] + 240 <= $info[0]) {
                                    if ($info[0] - $notes[$lastRealNote]["time"] <= 240) {
                                        // not a sustain
                                        if (DEBUG && VERBOSE) echo "not a sustain (SHOULD NOT HAPPEN)\n";
                                        $notes[$lastRealNote]["duration"] = 0;
                                    }
                                    else {
                                        // it's a sustain until this note
                                        if (DEBUG && VERBOSE) echo "sustain\n";
                                        $notes[$lastRealNote]["duration"] = $info[0] - $notes[$lastRealNote]["time"];
                                    }
                                }
                            }
                            
                            $lastRealNote = $index;
                        }
                        
                        
                    }
                    
                    // sustain check
                    if (($info[1] == "Off" || ($info[1] == "On" && $vel == 0)) &&
                        $info[0] > $notes[$index]["time"] + SUSTAIN
                        && is_array($notes[$index]["note"])
                        ) {
                        //&& !isset($notes[$index]["duration"])) {
                            if (isset($notes[$index]["duration"])) {
                                
                                if ($notes[$index]["duration"] > ($info[0] - $notes[$index]["time"])) {
                                    if (DEBUG && VERBOSE) echo "Changing duration of note $index from " . $notes[$index]["duration"];
                                    if (DEBUG && VERBOSE) echo " to " . ($info[0] - $notes[$index]["time"]) . "\n";
                                    $notes[$index]["duration"] = $info[0] - $notes[$index]["time"];
                                }
                            }
                            else {
                                $notes[$index]["duration"] = $info[0] - $notes[$index]["time"];
                            }
                    }
                    // make sure end events are for real notes
                    else if ($info[1] == "On" && $vel == 0 && is_array($notes[$index]["note"]) && !isset($notes[$index]["duration"])) {
                        $notes[$index]["duration"] = 0;
                    }
                    
                    // star power
                    if (is_array($notes[$index]["note"])) {
                        if ($SP) {
                            $notes[$index]["phrase"] = $SPphrase;
                        }
                        else {
                            $notes[$index]["phrase"] = 0;
                        }
                        
                        $notes[$index]["player1"] = $p1;
                        $notes[$index]["player2"] = $p2;
                        if (isset($difNotes["SOLO"])) {
                            $notes[$index]["solo"] = $solo;
                        }
                        
                        if (is_array($difNotes["FILL"])) {
                            $notes[$index]["fill"] = $fill;
                        }
                    }
                }
            }
        }
    }
    
    if (DEBUG == 2) {
        print_r(array_values($notes));
    }
    
    return $notes;
}


function arrayTimeExists($array, $time, $window) {
    // $window is how much tolerance we have
    if (!is_array($array)) {
        return false;
    }

    foreach ($array as $item) {
        //if (($item["time"] >= ($time - (($item["count"] + 1) * $window))) && ($item["time"] <= ($time + ($item["count"]+1) * $window))) {
        if ($item["time"] >= ($time - ($item["count"] + 1) * $window) && $item["time"] <= $time) {
            return true;
        }
    }

    return false;
}




?>