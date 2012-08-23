#!/usr/bin/php
<?php
// Configure me hard!
$password="tclttk95";
$fritzbox_ip="fritz.box";

// NO NEED TO EDIT BELOW HERE!
require_once(__DIR__."/lib/fritzbox_api.class.php");
require_once(__DIR__."/lib/lua_parser.class.php");
// Get us a new fritzbox handler
$fritz = new fritzbox_api($password, $fritzbox_ip);
// Refresh the Page
$params = array(
   'getpage'             => '../html/de/menus/menu2.html',
   'var:menu'                => 'internet',
   'var:pagename'        => 'docsis',
   'var:errorpagename'   => 'docsis',
   'var:type'            => '0',
);
$fritz->doPostForm($params);
// Get the "Kabel Informationen" details page
$params = array('getpage'         => '/internet/docsis_info.lua');
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
// 3. Now we decode (read: de-fuckup) the LUA table to proper SNMP keys for DOWNSTREAM and UPSTREAM
$downstream=array();
$upstream=array();
for($i=0; $i<100; $i++) {
	// find Downstream channel IDs
	if(array_key_exists("docsis:status/snmpOidCom/int/1/3/6/1/2/1/10/127/1/1/1/1/1/".$i,$dat["QUERIES"])) {
		// Generate a more DOCSIS-OID like data structure (see http://www.oidview.com/mibs/0/DOCS-IF-MIB.html)
		$channel=array(
			"docsIfDownChannelId" => $i,
			"docsIfDownChannelFrequency" => $dat["QUERIES"]["docsis:status/snmpOidCom/int/1/3/6/1/2/1/10/127/1/1/1/1/2/".$i],
			"docsIfDownChannelPower" => $dat["QUERIES"]["docsis:status/snmpOidCom/int/1/3/6/1/2/1/10/127/1/1/1/1/6/".$i],
			"docsIfSigQCorrecteds" => $dat["QUERIES"]["docsis:status/snmpOidCom/int/1/3/6/1/2/1/10/127/1/1/4/1/3/".$i],
			"docsIfSigQUncorrectables" => $dat["QUERIES"]["docsis:status/snmpOidCom/int/1/3/6/1/2/1/10/127/1/1/4/1/4/".$i],
			// And now AVM switched to another MIB for no reason (see http://www.oidview.com/mibs/4491/DOCS-IF3-MIB.html)
			"docsIf3SignalQualityExtRxMER" => $dat["QUERIES"]["docsis:status/snmpOidCom/int/1/3/6/1/4/1/4491/2/1/20/1/24/1/1/".$i],
			// And back to the former
			"docsIfDownChannelInterleave" => $dat["QUERIES"]["docsis:status/snmpOidCom/int_2_ds_interleaving/1/3/6/1/2/1/10/127/1/1/1/1/5/".$i],
			"docsIfDownChannelModulation" => $dat["QUERIES"]["docsis:status/snmpOidCom/int_2_ds_mod_type/1/3/6/1/2/1/10/127/1/1/1/1/4/".$i],
		);
		array_push($downstream,$channel);
	}
	if(array_key_exists("docsis:status/snmpOidCom/int/1/3/6/1/2/1/10/127/1/1/2/1/1/".$i,$dat["QUERIES"])) {
		// Generate the same for upstream
		$channel=array(
			"docsIfUpChannelId" => $i,
			"docsIfUpChannelType" => $dat["QUERIES"]["docsis:status/snmpOidCom/int_2_us_ch_type/1/3/6/1/2/1/10/127/1/1/2/1/15/".$i],
		);
		// Because AVM didn't stick to standard SNMP we need to fumble upstream power and modulation out of a real mess!
		// DISCLAIMER: I'm not resposible for breaking SNMP naming here! *grumble
		$upChannels=explode(",",$dat["QUERIES"]["docsis:status/ChdbEntry/us_usid_a"]);
		$upPower=explode(",",$dat["QUERIES"]["docsis:status/UpstreamDbEntry/txPower_a"]);
		// Modulation is even more inconsistent, not useable ATM
		//$upModulation=explide(",",$dat["QUERIES"]["docsis:status/UcdChannels/usModType_a"]);
		for($h=0;$h < count($upChannels); $h++) {
			// Pretty ugly: Channel Number 0 seems to mean "not used" to AVM
			if($upChannels[$h]!="0") {
				$channel["docsIfUpChannelPower"]=$upPower[$h];
			}
		}
		array_push($upstream,$channel);
	}
}
// Enable to see magic happening
/*
print_r($downstream);
print_r($upstream);
exit;
*/
// Munin plugin part
// Check if the user wants a config suggestions
if(isset($argv[1])) {
	if($argv[1]=="suggest") {
		foreach(array_keys($downstream) as $downId) {
			echo "ln -s ".$argv[0]." /etc/munin/plugins/fritzdocsis_down_".$downId."\n";
                        echo "ln -s ".$argv[0]." /etc/munin/plugins/fritzdocsis_downerr_".$downId."\n";
		}
		foreach(array_keys($upstream) as $upId) {
			echo "ln -s ".$argv[0]." /etc/munin/plugins/fritzdocsis_up_".$upId."\n";
		}
		exit(0);
	}
}

// Detect our function derived from our name:
// fritzbox_up_1 -> output upstream channel 1 information
// fritzbox_down_5 -> output downstream channel 5 information
preg_match("/.*_(up|down|downerr)_([0-9])/",$argv[0],$selector);
// up or down?
$mode=$selector[1];
// channel id?
$chId=$selector[2];

/*
* MUNIN fritzdocsis_up_N
* Shows Upstream Power levels
*/
if($mode=="up" && !empty($argv[1])){
	if($argv[1] == "config") {
	echo "graph_title Fritz!Box DOCSIS Upstream Channel $chId"."\n";
	echo "graph_vlabel Upstream Channel $chId"."\n";
	echo "graph_scale no"."\n";
	echo "graph_category fritzbox"."\n";
	echo "power.label Power Level [dBmV]"."\n";
	echo "power.critical 40:50"."\n";
	exit(0);
	}
}
if($mode=="up") {
	echo "power.value ".($upstream[$chId]["docsIfUpChannelPower"]/10)."\n";
}
/*
* MUNIN fritzdocsis_down_N
* Shows Downstream Power levels and line loss
*/
if($mode=="down" && !empty($argv[1])){
        if($argv[1] == "config") {
        echo "graph_title Fritz!Box DOCSIS Downstream Channel $chId"."\n";
        echo "graph_vlabel Downstream Channel $chId"."\n";
        echo "graph_scale no"."\n";
        echo "graph_category fritzbox"."\n";
        echo "power.label Power Level [dBmV]"."\n";
        echo "power.critical -3:8"."\n";
	echo "snr.label MSE [dB]"."\n";
        exit(0);
        }
}
if($mode=="down") {
        echo "power.value ".($downstream[$chId]["docsIfDownChannelPower"]/10)."\n";
	echo "snr.value ".($downstream[$chId]["docsIf3SignalQualityExtRxMER"]/10)."\n";
}
/*
* MUNIN fritzdocsis_downerr_N
* Shows Correctable and Uncorrectable error
*/
if($mode=="downerr" && !empty($argv[1])){
        if($argv[1] == "config") {
        echo "graph_title Fritz!Box DOCSIS Downstream Channel $chId Errors"."\n";
        echo "graph_vlabel Channel $chId Errors"."\n";
        echo "graph_scale no"."\n";
        echo "graph_category fritzbox"."\n";
        echo "correctable.label Correctable Errors"."\n";
	echo "correctable.type COUNTER"."\n";
        echo "uncorrectable.label Uncorrectable Errors"."\n";
        echo "uncorrectable.type COUNTER"."\n";
        exit(0);
        }
}
if($mode=="downerr") {
        echo "correctable.value ".$downstream[$chId]["docsIfSigQCorrecteds"]."\n";
        echo "uncorrectable.value ".$downstream[$chId]["docsIfSigQUncorrectables"]."\n";
}
?>
