<?php

/*
	Config.
*/


// Slack webhook URL, from the webhook config page
if($token == 'rfszle7MC9JVHZGZ3iib1TZj'){
	$slack_webhook_url = "https://hooks.slack.com/services/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";  // test
} else {
	$slack_webhook_url = "https://hooks.slack.com/services/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";  //dahveedtest
}

// prepended to search URL to determine which language you're searching in
$wiki_lang = "en";
// WikiMedia API defaults to 10. This defaults to 4, so you get 1 result and 3 other options. Do what thou wilt.
$search_limit = "4"; 

/*
	The WikiMedia API requests that the client is identified by a User-Agent string
	http://www.mediawiki.org/wiki/API:Etiquette#User-Agent_header
*/

$user_agent = "Slackipedia/1.0 (https://github.com/mccreath/slackipedia; mccreath@gmail.org)";

/* 
	Grab values from the slash command, create vars for post back to webhook
*/

$command = $_POST['command'];
$text = $_POST['text'];
$token = $_POST['token'];
$team_id = $_POST['team_id'];
$channel_id = $_POST['channel_id'];
$channel_name = $_POST['channel_name'];
$user_id = $_POST['user_id'];
$user_name = $_POST['user_name'];

/* 
	Encode $text for search string 
*/

$encoded_text = urlencode($text);

/*
	Call Wikipedia API with cURL (must be a GET)
*/

$wch_url = "http://".$wiki_lang.".wikipedia.org/w/api.php?action=opensearch&search=".$encoded_text."&format=json&limit=".$search_limit;

$wch = curl_init();
curl_setopt_array($wch, array(
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => $wch_url,
    CURLOPT_USERAGENT => $user_agent,
));
$wch_resp = curl_exec($wch);
curl_close($wch);


/*	Handle the returned data from Wikipedia */

$wch_arr = json_decode($wch_resp);
	
/*
	
	The array that comes back from Wikipedia has 4 nodes.
	
	The [0] node in the array is the search string (maybe an array for multi-word strings?)
	
	Each subsequent node is an array
	
	[1] is page titles (in 'apollo' example, there are 5, 0-4)
	[2] is page summaries/snippets (again, 0-4)
	[3] is page URLs (guess what, 0-4)
	
	So, for now, we'll do as an attachment:
	
	msg text: You searched for [0]. 
	
	attachment title	[1][0]
	attachment text 	[2][0] \n 
						[3][0] \n
						*other possibilities*
						[3][1] \n
						[3][2] \n
						[3][3] \n
						*search again on Wikipedia*
						<search url>
	
	
	From http://stackoverflow.com/questions/8926805/how-to-use-json-decode-to-extract-an-inner-information

	Pass TRUE to json_decode as second parameter

	$output = json_decode($input,TRUE);
	Then traverse the arrays. It should be something like $output['g'][0]['g9']['text3']['g91']
*/



	$other_options = $wch_arr[3];

	$first_item = array_shift($other_options);

	$other_options_count = count($other_options);
	
	$wch_text = "<@".$user_id."|".$user_name."> searched for *".$text."*.";

	$disamb_check = $text." may refer to:";
	//$disamb_text = "There are lots of possible results for *".$text."*.\n";

	$wch_att_title	= 	$wch_arr[1][0];
	$wch_att_desc = $wch_arr[2][0];
	$wch_att_link = $wch_arr[3][0];

	if(count($wch_arr[1]) == 0){
		$wch_att_text = "Sorry! I couldn't find anything like that.";
	} else {
		$wch_att_text = "";
		$wch_att_other = "";

		if(strtolower($wch_arr[2][0]) == $disamb_check){
			// $wch_att_text .= "There are lots of possible results for *<".$wch_att_link."|".$text.">*.\n\n";
			$wch_text .= "\nThere are lots of possible results for *<".$wch_att_link."|".$text.">*.\n\n";
			$wch_att_other_title = "Here are a few options:";
		} else {
			// $wch_att_text		.= 	$wch_att_desc."\n";
			// $wch_att_text		.= 	$wch_att_link;
			$wch_text	.= 	"\n*".$wch_att_title."*\n".$wch_att_desc."\n".$wch_att_link;
			$wch_att_other_title 	= 	"Here are some other options:";
		}
		foreach ($other_options as $value) {
			$wch_att_other .= $value."\n";
		}
		
	}
	
// Send it back through the webhook
$data = array(
	"username" => "Slackipedia",
	"channel" => $channel_id,
	"text" => $wch_text,
 	"mrkdwn" => true,
 	"icon_url" => "http://dmccreath.org/slackipedia/wikipedia-logo_small.png",
 	"attachments" => array(
 		 array(
			"color" => "#b0c4de",
 		//	"title" => $wch_att_title,
 			"fallback" => $wch_att_text,
 			"text" => $wch_att_text,
 			"mrkdwn_in" => array(
 				"fallback",
 				"text"
 			),
 			"fields" => array(
 				array(
 					"title" => $wch_att_other_title,
 					"value" => $wch_att_other
 				)
 			)
 		)
 	)
);
$json_string = json_encode($data);        

$ch = curl_init($slack_webhook_url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_string);
curl_setopt($ch, CURLOPT_CRLF, true);                                                               
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    "Content-Type: application/json",                                                                                
    "Content-Length: " . strlen($json_string))                                                                       
);                                                                                                                   
$result = curl_exec($ch);
curl_close($ch);





