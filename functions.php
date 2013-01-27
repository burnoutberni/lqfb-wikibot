<?
function new_issues($since, $until, $programm, $go, $satzung, $sonstig, $lqfb, $unitid) {
	//Get issues
	$apiUrl = "https://lfapi.piratenpartei.at/";
	$today = date('Y-m-d', time());
	if(!isset($since)) {$since = $today;}
	if(!isset($unitid)) {$unitid = 1;}
	if($programm == 1) {$policy[] = 1;}
	if($go == 1) {$policy[] = 5;}
	if($lqfb == 1) {$policy[] = 10;}
	if($sonstig == 1) {$policy[] = 38;}
	if($satzung == 1) {$policy[] = 39;}
	if(count($policy) == 0) {$policy[] = "1";$policy[] = "5";$policy[] = "10";$policy[] = "38";$policy[] = "39";}
	$policy_id = "&policy_id=".implode(",",$policy);
	$url_issue = $apiUrl . "issue?issue_closed_after=".$since."&issue_closed_before=".$until."&issue_state=finished_with_winner,finished_without_winner&unit_id=".$unitid.$policy_id;
	$json_issue = json_decode(file_get_contents($url_issue),true);
	if (count($json_issue['result']) == 0) {
		return "no_new_issues";
	}
	for ($i = 0; $i < count($json_issue['result']); $i++) {
		$array_issue_id[$i] = $json_issue['result'][$i]['id'];
		$time = strtotime($json_issue['result'][$i]['closed']);
		$array_issue_closed[$i] = date('Y-m-d H:i:s', strtotime('+1 hour',$time));
		$array_issue_state[$i] = $json_issue['result'][$i]['state'];
		$array_issue_area[$i] = $json_issue['result'][$i]['area_id'];
		$array_issue_count[$i] = $json_issue['result'][$i]['voter_count'];
		$array_issue_policy_id[$i] = $json_issue['result'][$i]['policy_id'];
	}

	asort($array_issue_closed);
	$array_keys = array_keys($array_issue_closed);

	//Get area names
	$url_area = $apiUrl . "area";
	$json_area = json_decode(file_get_contents($url_area),true);
	foreach ($json_area['result'] as $area) {
		for ($i = 0; $i < count($array_issue_id); $i++) {
			if($area['id'] == $array_issue_area[$i]) {
				$array_issue_area[$i] = $area['name'];
			}
		}
	}

	//Get policy names
	$url_policy = $apiUrl . "policy";
	$json_policy = json_decode(file_get_contents($url_policy),true);
	foreach ($json_policy['result'] as $policy) {
		for ($i = 0; $i < count($array_issue_id); $i++) {
			if($policy['id'] == $array_issue_policy_id[$i]) {
				$array_issue_policy[$i] = $policy['name'];
			}
		}
	}

	//Get quora
	$url_quorum = "https://lqfb.piratenpartei.at/static/hourly.php";
	$get_quorum = file_get_contents($url_quorum);
	$array_quorum = explode('<h2>Stimmberechtigte Nutzer in Liquid Feedback Organisation Piratenpartei Österreichs</h2>', $get_quorum);
	$array_quorum = str_replace('<b>','',$array_quorum);
	$array_quorum = str_replace('</b>','',$array_quorum);
	$array_quorum = explode('<br /></div></div></div>', $array_quorum[1]);
	$array_quorum = explode('<br />', $array_quorum[0]);
	$count = count($array_quorum) - 1;
	unset($array_quorum[$count]);
	for ($i = 0; $i < count($array_quorum); $i++) {
		$array_quorum[$i] = str_replace(' Stimmen','',$array_quorum[$i]);
		$array_quorum[$i] = str_replace(': x^0.6=','',$array_quorum[$i]);
		$array_quorum[$i] = explode(': ', $array_quorum[$i]);
		$buffer = explode('aufgerundet', $array_quorum[$i][1]);
		$buffer[1] = str_replace(')','',$buffer[1]);
		$array_quorum[$i][1] = $buffer[1];
		$buffer1 = explode(', ', $array_quorum[$i][0]);
		$buffer1[1] = str_replace(' ','',$buffer1[1]);
		$buffer2 = explode(' ', $buffer1[2]);
		if ($buffer2[1] == 'CEST') {$utc = "-2 hours";}
		if ($buffer2[1] == 'CET') {$utc = "-1 hours";}
		$array_quorum[$i][0] = $buffer1[1].' '.$buffer2[0];
		$array_quorum[$i][0] = strtotime($utc, strtotime($array_quorum[$i][0]));
		$array_quorum[$i][0] = date('Y-m-d H:i:s', $array_quorum[$i][0]);
	}

	for ($i = 0; $i < count($array_issue_id); $i++) {
		//Get best initiative name
		$url_initiative = $apiUrl . "initiative?issue_id=".$array_issue_id[$i];
		$json_initiative = json_decode(file_get_contents($url_initiative),true);
		foreach ($json_initiative['result'] as $initiative) {
			if($initiative['rank'] == 1) {
				$array_issue_initiative[$i] = $initiative['id'];
				$array_issue_initiative_name[$i] = $initiative['name'];
				$array_issue_initiative_pos[$i] = $initiative['positive_votes'];
				$array_issue_initiative_neg[$i] = $initiative['negative_votes'];
			}
		}

		$enthaltungen = $array_issue_count[$i] - $array_issue_initiative_pos[$i] - $array_issue_initiative_neg[$i];
		$abstimmung = $array_issue_initiative_pos[$i].':'.$enthaltungen.':'.$array_issue_initiative_neg[$i];

		for ($e = 0; $e < count($array_quorum); $e++) {
			if (strtotime($array_issue_closed[$i]) >= strtotime($array_quorum[$e][0]))
			{
				$issue_quorum = $array_quorum[$e][1];
				goto next;
			}
		}

		next:

		if ($issue_quorum > $array_issue_count[$i]) {
			$issue_quorum_text = "nein";
		} else {
			$issue_quorum_text = "ja";
		}

		$output[$i] = "";
		if($array_issue_state[$i] == "finished_with_winner") {
			$output[$i] = '|-style="background: PaleGreen"<br />';
			$accepted = "ja";
		} else {
			$output[$i] = '|-style="background: LightSalmon"<br />';
			$percentage = intval($array_issue_initiative_pos[$i])/(intval($array_issue_initiative_pos[$i])+intval($array_issue_initiative_neg[$i]));
			if ($array_issue_policy_id[$i] == "1") {
				if ($percentage < 0.6) {
					$accepted = "nein";
				}
			} elseif ($array_issue_policy_id[$i] == "5" || $array_issue_policy_id[$i] == "39") {
				if ($percentage < 0.7) {
					$accepted = "nein";
				}
			}
		}
		$output[$i] .= '|'.$array_issue_closed[$i].'<br />';
		$output[$i] .= '|'.$array_issue_policy[$i].'<br />';
		$output[$i] .= '|'.$array_issue_area[$i].'<br />';
		$output[$i] .= '|[https://lqfb.piratenpartei.at/issue/show/'.$array_issue_id[$i].'.html ×]<br />';
		$output[$i] .= '|[https://lqfb.piratenpartei.at/initiative/show/'.$array_issue_initiative[$i].'.html '.$array_issue_initiative_name[$i].']<br />';
		$output[$i] .= '|'.$accepted.' ('.$abstimmung.')<br />';
		$output[$i] .= '|'.$issue_quorum_text.' (Beteiligung: '.$array_issue_count[$i].'/'.$issue_quorum.')<br />';
		$output[$i] .= '|[https://wiki.piratenpartei.at ×]<br />';
	}
	
	$output1 = $array_keys;
	$output1[] = $output;
	return $output1;
}
?>
