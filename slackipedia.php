<?php

/*

## REQUIREMENTS

* A custom slash command on a Slack team
* A web server running PHP5 with cURL enabled

## USAGE

* Place the `slackipedia.php` script on a server running PHP5 with cURL.
* Set up a new custom slash command on your Slack team: http://my.slack.com/services/new/slash-commands
* Under "Choose a command", enter whatever you want for the command. /isitup is easy to remember.
* Under "URL", enter the URL for the script on your server.
* Leave "Method" set to "Post".
* Decide whether you want this command to show in the autocomplete list for slash commands.
* If you do, enter a short description and usage hint.

*/

/*	Configuration */

//  Slack webhook URL - from the webhook config page
$slack_webhook_url = "https://hooks.slack.com/services/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";

//  Icon URL - Where ever you put the image from the zip archive
$icon_url = "http://dmccreath.org/slackipedia/wikipedia-logo-cc-by-sa_0.png";

/* Wikipedia defaults */

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
$wiki_url = "https://".$wiki_lang.".wikipedia.org/w/api.php?action=opensearch&search=".$encoded_text."&format=json&limit=".$search_limit;

//  Call the URL
$wiki_call = curl_init($wiki_url);
curl_setopt($wiki_call, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($wiki_call, CURLOPT_USERAGENT, $user_agent);
$wiki_response = curl_exec($wiki_call);
if($wiki_response === FALSE ){
	$message_text = "There was a problem reaching Wikipedia. This might be helpful: The cURL error is " . curl_error($wiki);
} else {
	$message_text = "";
}
curl_close($wiki_call);


//  Handle the returned data from Wikipedia
if($wiki_response !== FALSE){

	// Turn the response into an array
	$wiki_array = json_decode($wiki_response);
	
	// Put all the links into their own array, then remove the first item
	// This will become our "Other Options" list
	$other_options = $wiki_array[3];
	array_shift($other_options);
	
	// Identify the user, link name, and reflect the search term
	$message_text = "<@".$user_id."|".$user_name."> searched for *".$text."*.\n";

	// Determine if our first result is a disambiguation page
	// NOTE: This is not the most reliable method, and will need to be changed depending 
	// on the languge of the wiki being searched, but it's the only reliable way to check
	if (strpos($wiki_array[2][0],"may refer to:") !== false) {
    $disambiguation_check = TRUE;
	}

	// Set variables for the first result
	$message_primary_title		= $wiki_array[1][0];
	$message_primary_summary	=	$wiki_array[2][0];
	$message_primary_link			=	$wiki_array[3][0];

	if(count($wiki_array[1]) == 0){
		$message_attachment_text = "Sorry! I couldn't find anything like *".$text."*.";
	} else {
		$message_attachment_text = "";
		$message_other_options = "";
		if ($disambiguation_check == TRUE){
			$message_text	.= "There are several possible results for ";
			$message_text	.= "*<".$message_primary_link."|".$text.">*.\n";
			$message_text	.= $message_primary_link;
			$$message_other_options_title = "Here are some of the possibilities:";
		} else {
			$message_text	.= 	"*<".$message_primary_link."|".$message_primary_title.">*\n";
			$message_text	.= 	$message_primary_summary."\n";
			$message_text	.= 	$message_primary_link;
			$message_other_options_title 	= 	"Here are a few other options:";
		}
		foreach ($other_options as $value) {
			$message_other_options .= $value."\n";
		}
	}
}
	
// Send it back through the webhook
$data = array(
	"username" => "Slackipedia",
	"channel" => $channel_id,
	"text" => $message_text,
 	"mrkdwn" => true,
 	"icon_url" => $icon_url,
 	"attachments" => array(
 		 array(
			"color" => "#b0c4de",
 		//	"title" => $message_primary_title,
 			"fallback" => $message_attachment_text,
 			"text" => $message_attachment_text,
 			"mrkdwn_in" => array(
 				"fallback",
 				"text"
 			),
 			"fields" => array(
 				array(
 					"title" => $message_other_options_title,
 					"value" => $message_other_options
 				)
 			)
 		)
 	)
);
$json_string = json_encode($data);        

$slack_call = curl_init($slack_webhook_url);
curl_setopt($slack_call, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
curl_setopt($slack_call, CURLOPT_POSTFIELDS, $json_string);
curl_setopt($slack_call, CURLOPT_CRLF, true);                                                               
curl_setopt($slack_call, CURLOPT_RETURNTRANSFER, true);                                                                      
curl_setopt($slack_call, CURLOPT_HTTPHEADER, array(                                                                          
    "Content-Type: application/json",                                                                                
    "Content-Length: " . strlen($json_string))                                                                       
);                                                                                                                   
$result = curl_exec($slack_call);
curl_close($slack_call);



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





