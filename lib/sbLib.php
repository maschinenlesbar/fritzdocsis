<?php

function formatLua($output) {
	$startParse = false;
	$key = "";
	foreach(explode("\n", $output) as $luaLine) {
		if(preg_match('/^([^ ]*) \= \{$/', $luaLine, $out)) {
			$key = $out[1];
			$startParse = true;
			$data[$key] = "";
		//continue;
		}
		if($luaLine == "}" && $startParse) {
			$data[$key].=$luaLine."\n";
			$key = "";
			$startParse = false;
		}
		if($luaLine == "</pre>") {
			break;
		}
		if($startParse) {
			$data[$key].=$luaLine."\n";
		}
	}

	foreach($data as $k => $v) {
		$lp=new WLP_Parser($v);
		$dat[$k]=$lp->toArray();
	}
	return $dat;
}

?>
