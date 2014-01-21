#!/usr/bin/php
<?php
// Configure me hard!
// Please configure Fritz!Box Login Data in /lib/fritzbox_user.conf.php

$tmpFile="/tmp/fritzbw.db";
$tmpFileMaxAge="60";

// NO NEED TO EDIT BELOW HERE!
require_once(__DIR__."/lib/fritzbox_api.class.php");
require_once(__DIR__."/lib/lua_parser.class.php");

function lowhigh($l,$h) 
{ 
    $res=$l+($h*pow(65536,2)); 
    $res=round($res/1000,0); 
    return($res); 
} 

// Check if we have to do a new DOCSIS query
if(!file_exists($tmpFile) || (filemtime($tmpFile) < time() - $tmpFileMaxAge)) {
	// Get us a new fritzbox handler
	$fritz = new fritzbox_api();
	
	// Get the "Onlien-Monitor" details page
	$params = array('getpage'         => '/internet/inetstat_counter.lua');
	$output = $fritz->doGetRequest($params);
	// Disconnect from the Webinterface
	$fritz = null;
	// DISCLAIMER: Now comes the funny part!
	// 1. AVM enclosed a LUA table in <code> tags. First we get the LUA table out!
	$pattern='/.*\[".*"\].=.".*",/';
	$test=preg_match_all($pattern,$output,$out);
	$luaTable="QUERIES ={\n";
	foreach($out[0] as $luaLine) {
		$luaTable.=$luaLine."\n";
	}
	$luaTable.="}";
	// 2. Then we parse the LUA table to an array using a parser for World of Warcraft (it's everywhere, mh?)
	$lp=new WLP_Parser($luaTable);
	$dat=$lp->toArray();
	
	// Heute empfangen 
	$high=$dat["QUERIES"]["inetstat:status/Today/BytesReceivedHigh"];
	$low=$dat["QUERIES"]["inetstat:status/Today/BytesReceivedLow"];
	$bwstats['received']=lowhigh($low,$high);
	$high=$dat["QUERIES"]["inetstat:status/Today/BytesSentHigh"];
	$low=$dat["QUERIES"]["inetstat:status/Today/BytesSentLow"];	
	$bwstats['sent']=lowhigh($low,$high);

	// Alte Daten lesen
	$bwstats['receivedDelta'] = 0;
	$bwstats['sentDelta'] = 0;
	if(file_exists($tmpFile)) {
		$bwstatsOld = unserialize(file_get_contents($tmpFile));


		$tmpDelta = ($bwstats['received'] - $bwstatsOld['received']);
		if($tmpDelta > 0) {
			$bwstats['receivedDelta'] = $tmpDelta;
		}
		$tmpDelta = ($bwstats['sent'] - $bwstatsOld['sent']);
		if($tmpDelta > 0) {
			$bwstats['sentDelta'] = $tmpDelta;
		}
	}

	// Serialize what we have
	file_put_contents($tmpFile,serialize($bwstats));
} else {
	// Unserialize from file
	$bwstats = unserialize(file_get_contents($tmpFile));
}

// Munin plugin part
// Check if the user wants a config suggestions
if(isset($argv[1])) {
	if($argv[1]=="suggest") {
		echo "ln -s ".$argv[0]." /etc/munin/plugins/fritzbw_sent\n";
		echo "ln -s ".$argv[0]." /etc/munin/plugins/fritzbw_received\n";
		echo "ln -s ".$argv[0]." /etc/munin/plugins/fritzbw_both\n";
		exit(0);
	}
}

// Detect our function derived from our name:
preg_match("/.*_(sent|received|both)/",$argv[0],$selector);
// up or down?
$mode=$selector[1];

/*
* MUNIN fritzbw_received
* Shows Received Data
*/
if($mode=="received" && !empty($argv[1])){
	if($argv[1] == "config") {
	echo "host_name stuttgart.sbuehl.com\n";
	echo "graph_title FritzBox Bandwidth - Received\n";
	echo "graph_vlabel Bandwidth"."\n";
	echo "graph_scale no"."\n";
	echo "graph_category fritzbox-bandwidth"."\n";
	echo "bw.label data received [kB]"."\n";
	exit(0);
	}
}
if($mode=="received") {
	echo "bw.value ".($bwstats["receivedDelta"])."\n";
}
/*
* MUNIN fritzbw_sent
* Shows Sent Data
*/
if($mode=="sent" && !empty($argv[1])){
        if($argv[1] == "config") {
	echo "host_name stuttgart.sbuehl.com\n";
	echo "graph_title FritzBox Bandwidth - Sent\n";
	echo "graph_vlabel Bandwidth"."\n";
	echo "graph_scale no"."\n";
	echo "graph_category fritzbox-bandwidth"."\n";
	echo "bw.label data sent [kB]"."\n";
        exit(0);
        }
}
if($mode=="sent") {
        echo "bw.value ".($bwstats["sentDelta"])."\n";
}
/*
* MUNIN fritzbw combined
*/
if($mode=="both" && !empty($argv[1])){
        if($argv[1] == "config") {
	echo "host_name stuttgart.sbuehl.com\n";
	echo "graph_title FritzBox Bandwidth\n";
	echo "graph_vlabel Bandwidth"."\n";
	echo "graph_scale yes"."\n";
	echo "graph_category fritzbox-bandwidth"."\n";
	echo "bwrecv.label data received [kB]"."\n";
	echo "bwsent.label data sent [kB]"."\n";
        exit(0);
        }
}
if($mode=="both") {
        echo "bwrecv.value ".$bwstats["receivedDelta"]."\n";
        echo "bwsent.value ".$bwstats["sentDelta"]."\n";
}
?>
