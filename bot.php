#!/usr/bin/php -f
<?
require('globals.php');
require('functions.php');

$today_0 = time();
//$today_0 = strtotime('+1 hour',time());

$today_1 = date('Y-m-d\TH:i:s', strtotime($differenz,$today_0));
$today_2 = date('Y-m-d\TH:i:s', $today_0);

//$today_1 = date('Y-m-d\TH:i:s', strtotime($differenz,time()));
//$today_2 = date('Y-m-d\TH:i:s', time());
//$today_1 = "2013-01-06T22:00:00";

try
{
    $wiki = new Wikimate($api_url);
    if ($wiki->login($username,$password)) {}
		else {
        $error = $wiki->getError();
        echo $error['login'];
    }
}
catch ( Exception $e )
{
    echo "An error occured: ".$e->getMessage();
}

$page = $wiki->getPage('AG:LiquidFeedback/Sandbox'); // create a new page object
$wikiCode_old = $page->getText();

$output1 = new_issues($today_1,$today_2,1,1,1,1,1,1);
if($output1 == "no_new_issues") {
	$wikiCode2 = "<!--No new issues - ".$today_2."-->
<!--%%NEW%%-->";
	$wikiCode3 = str_replace('',$wikiCode2,$wikiCode_old);
	echo "No new issues";
	goto end;
}
$output_count = count($output1) - 1;
$output = $output1[$output_count];
for ($i = 0; $i < count($output); $i++) {
	$e = $output1[$i];
	$print_out[] = $output[$e];
}

$wikiCode = implode('', $print_out);
$wikiCode1 = str_replace('<br />','
',$wikiCode);
$wikiCode2 = $wikiCode1."
<!--%%NEW%%-->";
no_new:
$wikiCode3 = str_replace('<!--%%NEW%%-->',$wikiCode2,$wikiCode_old);
if($page->setText($wikiCode3)) {
	echo "Done :)
";
}
// returns true if the edit worked
//echo $page->setText("==Testing==\n\n This is a whole page"); // returns true if the edit worked
end:
?>
