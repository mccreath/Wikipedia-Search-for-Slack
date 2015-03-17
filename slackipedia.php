<?php

/*	Configuration */

//  Slack webhook URL - from the webhook config page
$slack_webhook_url = "https://hooks.slack.com/services/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";

//  Icon URL - Where ever you put the from the zip archive
$icon_url = "http://dmccreath.org/slackipedia/wikipedia-logo-cc-by-sa_0.png";

//  prepended to search URL to determine which language you're searching in
$wiki_lang = "en";
//  WikiMedia API defaults to 10. This defaults to 4, so you get 1 result and 3 other options. Do what thou wilt.
$search_limit = "4"; 

//	The WikiMedia API requests that the client is identified by a User-Agent string
//	http://www.mediawiki.org/wiki/API:Etiquette#User-Agent_header
$user_agent = "Slackipedia/1.0 (https://github.com/mccreath/slackipedia; mccreath@gmail.org)";


/*  Now for some action! */

//  Grab the POST values from the slash command, create vars for post back to webhook
$command = $_POST['command'];
$text = $_POST['text'];
$token = $_POST['token'];
$team_id = $_POST['team_id'];
$channel_id = $_POST['channel_id'];
$channel_name = $_POST['channel_name'];
$user_id = $_POST['user_id'];
$user_name = $_POST['user_name'];

//  Encode the $text for the Wikipedia search string 
$encoded_text = urlencode($text);

//  Create URL for Wikipedia API, which requires using GET
$wiki_url = "http://".$wiki_lang.".wikipedia.org/w/api.php?action=opensearch&search=".$encoded_text."&format=json&limit=".$search_limit;

//  Call the URL
$wiki = curl_init($wiki_url);
curl_setopt($wiki, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($wiki, CURLOPT_USERAGENT, $user_agent);
$wiki_resp = curl_exec($wiki);
if($wiki_resp === FALSE ){
	$wiki_text = "There was a problem reaching Wikipedia. This might be helpful: The cURL error is " . curl_error($wiki);
} else {
	$wiki_text = "";
}
curl_close($wiki);


//  Handle the returned data from Wikipedia
if($wiki_resp !== FALSE){

	$wiki_arr = json_decode($wiki_resp);
	$other_options = $wiki_arr[3];
	$first_item = array_shift($other_options);
	$other_options_count = count($other_options);
	
	$wiki_text = "<@".$user_id."|".$user_name."> searched for *".$text."*.\n";

	$disamb_check = "may refer to:";

	$wiki_att_title	= 	$wiki_arr[1][0];
	$wiki_att_desc		=		$wiki_arr[2][0];
	$wiki_att_link		=		$wiki_arr[3][0];

	if(count($wiki_arr[1]) == 0){
		$wiki_att_text = "Sorry! I couldn't find anything like that.";
	} else {
		$wiki_att_text = "";
		$wiki_att_other = "";
		if (strpos($wiki_arr[2][0],$disamb_check) !== false) { // see if it's a disambiguation page
			$wiki_text	.= "There are several possible results for ";
			$wiki_text	.= "*<".$wiki_att_link."|".$text.">*.\n";
			$wiki_text	.= $wiki_att_link;
			$wiki_att_other_title = "Here are some of the possibilities:";
		} else {
			$wiki_text	.= 	"*<".$wiki_att_link."|".$wiki_att_title.">*\n";
			$wiki_text	.= 	$wiki_att_desc."\n";
			$wiki_text	.= 	$wiki_att_link;
			$wiki_att_other_title 	= 	"Here are a few other options:";
		}
		foreach ($other_options as $value) {
			$wiki_att_other .= $value."\n";
		}
	}

}
	
// Send it back through the webhook
$data = array(
	"username" => "Slackipedia",
	"channel" => $channel_id,
	"text" => $wiki_text,
 	"mrkdwn" => true,
 	"icon_url" => $icon_url,
 	"attachments" => array(
 		 array(
			"color" => "#b0c4de",
 		//	"title" => $wiki_att_title,
 			"fallback" => $wiki_att_text,
 			"text" => $wiki_att_text,
 			"mrkdwn_in" => array(
 				"fallback",
 				"text"
 			),
 			"fields" => array(
 				array(
 					"title" => $wiki_att_other_title,
 					"value" => $wiki_att_other
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





