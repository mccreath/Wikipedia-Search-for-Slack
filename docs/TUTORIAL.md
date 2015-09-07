# Tutorial

<!-- screenshots should be 714 pixels wide -->

This very simple demo will take you through the process of setting up both a custom slash command (https://api.slack.com/slash-commands) and an incoming webhook (https://api.slack.com/incoming-webhooks). If you haven't worked with Slack's custom slash commands, you might find it helpful to read through our [tutorial for them]().

Wikipedia is good to use for this because you don't need an API key to access their search API. All you need to do is identify your script with a user agent string (which we'll cover in a bit). In fact, because this is the default for all MediaWiki installations, you could repurpose this script to search _any_ site built on MediaWiki.

## Overview

**Our script is going to:**

* Take the values from a slash command on Slack and turn them into variables 
* Use cURL to send the search string entered by your user to Wikipedia's Search API
* Accept the results returned by the Wikipedia search and figure out what to do with them
* Format the results into a proper JSON payload for the incoming webhook
* Use cURL to send the formatted JSON to the incoming webhook's URL
* Post the results to the Slack channel where the slash command was used

**This tutorial makes use of the following technologies:**

* PHP [http://php.net](http://php.net)
* JSON (JavaScript Object Notation - [http://json.org/](http://json.org/))
* cURL [http://curl.haxxe.se](http://curl.haxxe.se)

Don't worry too much if you've never used one or more of those. Our use of them will be thoroughly explained in the tutorial.

**You'll need the following tools:**

* A plain text editor. If you want a free one, [TextWrangler for Mac](http://barebones.com/products/textwrangler/) or [Notepad++ for Windows](http://notepad-plus-plus.org/) are both great.
* A hosting account running PHP 5 and cURL where you can put the script we're going to write. Pretty much any shared hosting account in the world should work for this.
* A Slack account with privileges to create integrations. (A free team is fine.)



Slack's custom slash commands perform a very simple task: they take whatever text you enter after the command itself (along with some other predefined values), send it to a URL, then accept whatever the script returns and posts it as a Slackbot message to the person who issued the command. What you do with that text at the URL is what makes slash commands so useful. 

If you're not familiar with slash commands, I recommend reading through my [slash command tutorial](https://github.com/mccreath/isitup-for-slack/blob/master/docs/TUTORIAL.md) before you tackle this one.

Webhooks are common tools for services with APIs. They provide a structured way to send information into or out of the service. Slack's incoming webhooks accept data in a format called JSON, which is a widely-used format for this kind of data. We'll go over a little bit of it when we're setting up the webhook, but what you need to know for now is that an incoming webhook lives at a specific URL, and when send this formatted JSON to that URL, Slack can take it and post a message to any channel, group, or direct message that you have access to.

What that means for our project is that when someone uses our Wikipedia search slash command, we have a way to post the results publicly for others to see.

## Set up Slack 

### Slash Command

Go to your integrations page at Slack (http://my.slack.com/services/new) and scroll down to the bottom section, "DIY Integrations & Customizations". Click on the "Add" button for "Slash Commands".

![Add a slash command integration](add-slash-command.png)

Create the text command itself. This is the text that the user will type after the slash. I use `wikip`, because it's just enough to indicate that this isn't just any wiki that you're searching. But you could use the entire word `Wikipedia` or `searchwiki`. Whatever makes the most sense for your command and your users.

![Create the command](create-command.png)

For now you can leave everything else empty. We'll come back and finish setting this up in a bit. Just scroll down to the bottom and click the "Save Integration" button.

### Incoming Webhook

Go to your integrations page at Slack (http://my.slack.com/services/new) and scroll down to the bottom section, "DIY Integrations & Customizations". Click on the "Add" button for "Incoming Webhooks".

![Add incoming webhook](add-incoming-webhook.png)

All incoming webhooks require a default channel to post to. We're going to see how to override that default later, but for now, either pick one of your existing channels or use the "create new channel" option to make new channel.

When you've done that click the "Add Incoming Webhook Integration" button.

<!-- Select channel and add integration screenshot -->

Put "Slackipedia" in the Descriptive Label field. This will help you distinguish this webhook from any others you set up in your list of configured integrations.

<!-- Descriptive field screenshot -->

Also put "Slackipedia" in the Customize Name field. This is what the webhook will use as a "username" when it posts to your channels.

<!-- Customize name screenshot -->

PRO TIP: Save your settings now before trying to add the custom icon. There's a little bug with the webhook page right now where you'll have to re-enter some of your settings if you don't save them first.

Now you can upload the Wikipedia logo icon that's in the code package you downloaded. (Or, you know, use something inferior.)

<!-- Custom icon screenshot -->

Save your settings again, and you're done with the webhook for the moment.

## The PHP script

Now we're going to go step by step through the PHP script. If PHP isn't your jam, this should still be pretty simple to apply to the language of your choice. 

### cURL, briefly

If you're familiar with cURL, feel free to jump over this section.

cURL is an open source command line tool that lets you transfer data with URL syntax, which is what web browsers use, and as a result, much of the web uses. Being able to transfer data with URL syntax is what makes webhooks work. The thing about cURL that's useful for us is that not only can you use it from the command line (which makes it easy to use for testing things), but you can interact with it from most modern scripting language. 

PHP has had support for cURL for years, and we're going to take advantage of that so that our script can receive data from Slack and then send it back in. We'll be using a few very basic commands that are common for this type of task. All of the cURL that we use in this script will be transferrable to any other webhook script that you want to write. 

### Set up your config

There are a few things we're going to need for the script, so let's set those up at the very top.

First, your incoming webhook URL. This tells the script where to send the reply it gets back from Wikipedia. Get this URL from your incoming webhook configuration page.

```php
$slack_webhook_url = "https://hooks.slack.com/services/T0XXXXXXX/B0XXXXXXX/xxxxxxxxxxxxxxxxxxxxxxxx"; // replace that URL with your webhook URL 
```
    
Next, the icon for your integration. You probably remember that we already set a custom icon for the webhook on the configuration page, but you can also specify within the webhook's payload. This is useful if you want to reuse the webhook itself for a few slash commands, or just as a fallback for the one on the configuration page.

```php
$icon_url = ""; // the URL for where you upload the image, eg http://domain.com/slackipedia/wikipedia-logo-cc-by-sa_0.png
```
    
And now some defaults for Wikipedia itself.

You can change the language you're searching in with this. Set it to `en` for English. You can find other language options here: <!-- languages url -->

```php
$wiki_lang = "en";
```
   
By default, the WikiMedia API will return 10 search results. That will make for a very long message in Slack, so I like to default it to 4. You can play around with this and see what makes the most sense for your team.

```php
$search_limit = "4"; 
```

Finally, the WikiMedia API requests that the client is identified by a User-Agent string (http://www.mediawiki.org/wiki/API:Etiquette#User-Agent_header). This allows the Wikipedia folks to look through their logs and see how often our script is being used to search their site. Feel free to leave this set to this, or you can update it with any info you want.

```php
$user_agent = "Slackipedia/1.0 (https://github.com/mccreath/slackipedia; mccreath@gmail.org)";
```

### Create some variables from the slash command

The first thing you need to do when the script is called by your slash command is grab all the values from the command and make variables out of them. We'll use most of them at various points in the script, and the variable names will be easy to remember.

First, the command string itself. In our case, we know it's `wikip`. But it's good to set it as a variable if you decide you want to test against it.

```php
$command = $_POST['command'];
```
    
Next, the text that was entered with the command. For this webhook, this will be the search string we send to Wikipedia.
     
```php
$text = $_POST['text'];
```
    
The token is an additional identifier that's sent with the slash command that you could use to verify that what's calling your script is actually your slash command.

```php
$token = $_POST['token'];
```
    
The channel ID is the channel where the slash command was issued. We'll use this later in the script to tell the webhook to return the results to that same channel. 
    
```php
$channel_id = $_POST['channel_id'];
```
    
We're going to display the user name with the webhook message, just so it's clear who caused the message to appear in the channel.
    
```php
$user_name = $_POST['user_name'];
```

Finally, we want to encode the search string properly. All API searches go to Wikipedia as `GET` requests, which means the search query gets appended to the URL of API request. That means we need to properly encode the text for use in a URL. Php has a function just for that. 

```php
#
# Encode the $text for the Wikipedia search string 
#

$encoded_text = urlencode($text);
```

### Build the URL to send to Wikipedia

Before we can actually send the URL to Wikipedia, we need to create it. We'll make one more new variable called `$wiki_url`, which uses PHP's string concatenation to add some of the variables we've been creating to the standard Wikipedia API search URL.

The MediaWiki API responds to GET requests, which means that all the search values are passed to the server as part of the URL. For our search we're going to pass four values.

* `action`, which tells the Wikipedia server what function of the API we're going to use. In our case it's going to be `opensearch`. 
* `search`, which is the string will be looking for. In our case it will be our `$encoded_text` variable.
* `format`, which tells the Wikipedia server how we want to receive the data it sends back. We want `json`.
* `limit`, which tells the Wikipedia server how many results to return to us. We're going to limit it to `4` for this tutorial. You can do any number, but bear in mind the context of a Slack channel and whether it would make sense in that context to have 10, or even 20, results. It probably wouldn't.

The completed URL will look like this:

```php
http://en.wikipedia.org/w/api.php?action=opensearch&search=searchtext&format=json&limit=4
```

Taking that and replacing all the appropriate parts with our variables results in this:

```php
#
# Create URL for Wikipedia API, which requires using GET
# 

$wiki_url = "http://".$wiki_lang.".wikipedia.org/w/api.php?action=opensearch&search=".$encoded_text."&format=json&limit=".$search_limit; 
```
### Send the URL to Wikipedia

Now we're ready to actually send the search to Wikipedia. This is where the first use of cURL happens.

First we have to initialize cURL, and we'll create a variable with that, which we'll use in the next few lines to set some options and execute the call. (It's very common to use the variable name `$ch` for this. It stands for `curl handle` and it can be used over and over for all your cURL handles. However, I like to give my cURL handles more descriptive names.)
 
```php
$wiki_call = curl_init($wiki_url);
```

Next we set a couple of options. The first one, `CURLOPT_RETURNTRANSFER`, tells cURL that we expect to get something back from the URL that we're calling. In this case it's the search results. The second one, `CURLOPT_USERAGENT` is where we pass that User-Agent string that we created to identify our script.

```php
curl_setopt($wiki_call, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($wiki_call, CURLOPT_USERAGENT, $user_agent);
```
    
Now we execute our cURL. This is basically like your script opening up a web browser, typing a URL into the address field, and hitting "Return". Just like with the initialization command, we're going to set a variable for this, which will allow us to do a test immediatelly.
    
```php
$wiki_respsonse = curl_exec($wiki_call);
```
    
Now we check to see if that execution returned anything we can use. If not, the contents of that variable will be `FALSE`. In that case, we create a variable called `$message_text` and put in an error message. Later on, we're going to send the value of `$message_text` back to Slack. If there are no results, we want to let user know that. However, if there are results, then we'll leave `$message_text` blank, and fill it in with some of the results (after we do some formatting).

Note the use of the triple equal signs! There's a very good PHP reason for doing your comparison with triple equals instead of just double. Here's a link that explains it. <!-- FIND A LINK -->

```php
if($wiki_response === FALSE ){
	$message_text = "There was a problem reaching Wikipedia. This might be helpful: The cURL error is " . curl_error($wiki_call);
} else {
	$message_text = "";
}
```
 
And now we close the cURL connection, so there's not something on your server just taking up memory.
    
```php
curl_close($wiki_call);
```

### Understanding the returned data, or:<br> A short discursion into JSON and arrays.
    
Assuming we got data back from Wikipedia, we need to get it properly organized for sending back through our webhook. Let's take a look at what Wikipedia sends back in their search results. It's important to know that all sites structure their JSON in the way that makes sense for their data, which means you need to understand what that structure is and how to transform it into the structure that Slack's webhooks use. 

We'll use the search term `airship` as our example. If you paste this URL into a browser's address bar (or run it with cURL in a terminal):

```
http://en.wikipedia.org/w/api.php?action=opensearch&search=airship&format=json&limit=4
```

You'll get back a JSON object that looks like this:

```json
[
    "airship",
    [
        "Airship",
        "Airship Italia",
        "Airship Industries",
        "Airship (Final Fantasy)"
    ],
    [
        "An airship or dirigible is a type of aerostat or lighter-than-air aircraft which can navigate through the air under its own power.",
        "Airship Italia was a semi-rigid airship used by Italian engineer Umberto Nobile in his second series of flights around the North Pole.",
        "Airship Industries was a British manufacturers of modern non-rigid airships (blimps) active under that name from 1970 to the present day and controlled for part of that time by Alan Bond.",
        "This is a redirect from a page that was merged into the target page. This page was kept as a redirect to the corresponding main page on the topic it names, in order to preserve this page's edit history after its content was merged into the target page's content."
    ],
    [
        "http://en.wikipedia.org/wiki/Airship",
        "http://en.wikipedia.org/wiki/Airship_Italia",
        "http://en.wikipedia.org/wiki/Airship_Industries",
        "http://en.wikipedia.org/wiki/Airship_(Final_Fantasy)"
    ]
]
```

It's a very simple object, with no more information in it than is absolutely necessary. The first item in the array is the search term. That's followed by three more arrays, each of which has four quoted strings in it, separated by commas. Knowing what we know about Wikipedia, we can make some guesses as to what each of those objects.

* First is a page title
* Second is a page summary 
* Third is a page URL

Fortunately, that's all we need to build a nicely formatted and informative message to return to Slack. 

However, it will be much easier to work with the data in PHP if we turn it into an array first. So we'll use PHP's `json_decode()` function, which does just that.

```php
$wiki_array = json_decode($wiki_response);
```

That will give us a standard indexed PHP array which would look like this. Notice that all of the items we got back from Wikipedia now have numbers associated with them. The number for each item is called its `index`, and an array that uses numbers to identify its contents like this is called an "indexed array".

```php
$wiki_array = array(
    [0] => airship
    [1] => Array(
        [0] => Airship
        [1] => Airship Italia
        [2] => Airship Industries
        [3] => Airship (Final Fantasy)
    )
    [2] => Array(
        [0] => An airship or dirigible is a type of aerostat or lighter-than-air aircraft which can navigate through the air under its own power.
        [1] => Airship Italia was a semi-rigid airship used by Italian engineer Umberto Nobile in his second series of flights around the North Pole.
        [2] => Airship Industries was a British manufacturers of modern non-rigid airships (blimps) active under that name from 1970 to the present day and controlled for part of that time by Alan Bond.
        [3] => This is a redirect from a page that was merged into the target page. This page was kept as a redirect to the corresponding main page on the topic it names, in order to preserve this page's edit history after its content was merged into the target page's content.
    )
    [3] => Array(
        [0] => http://en.wikipedia.org/wiki/Airship
        [1] => http://en.wikipedia.org/wiki/Airship_Italia
        [2] => http://en.wikipedia.org/wiki/Airship_Industries
        [3] => http://en.wikipedia.org/wiki/Airship_(Final_Fantasy)
    )
)
```

In an indexed array, we reference each item in the array by its index. Using the array above, if we wanted to make a variable with the title for the Wikipedia page "Airship", we would use `$wiki_array[1][0]`. That basically tells PHP "Look in the array called `$wiki_array`, go to index `[1]`, and within that, find index `[0]`.

Building on that, if we want to make variables from the title, summary, and URL for the page "Airship".

```php
$title   = $wiki_array[1][0]
$summary = $wiki_array[2][0]
$url     = $wiki_array[3][0]
```

To do the same thing for the page "Airship Italia", it would look like this.

```php
$title   = $wiki_array[1][1]
$summary = $wiki_array[2][1]
$url     = $wiki_array[3][1]
```

See the pattern? All the titles are in index `[1]`, all the summaries are in index `[2]`, all the URLs are in index `[3]`. Now that we understand how the results are coming back, we can start building the message that we're going to send into the webhook.

## Designing your message

The information you send into Slack and how it's formatted will directly effect how successful your integration is. No matter what the message is, you want to provide enough data for the user to take any action that might need, but not so much that it overwhelms the channel. 

In the case of our Wikipedia search, the message is something that will be triggered by a human user so there shouldn't be too much concern about spamming a channel, at least from a technical standpoint. 

Other good practices for bot messages in Slack:

* **Identify the bot that posted the message**. We'll do that with a custom username and a custom icon. These are both helpful for team members to see in the context of a Slack channel to help distinguish human messages from bot and integration messages.
* **Identify the team member** that caused the message to be posted, if possible. This good etiquette for both the message poster and their fellow team members.

We've already set the username and icon webhook configuration page. Setting the name on the configuration page can be very useful for finding Slackipedia in your list of integrations if you have a lot of incoming webhooks.

For our message, we have two cases that we'll need to handle:

* In most cases, Wikipedia will return one page as its best result, along with as many other options as it can, up to your `$search_limit` number. In those cases, we want to **display the title and summary of the page**, as well as a link to and then list the other options, in case the primary result wasn't what the user was looking for.
* If Wikipedia doesn't have one page that it considers the best for that search, it will return a link to a disambiguation page, along with a list of possible options. In that case, we need to make sure that's clear to the user.

### Options for displaying data in a webhook message

There are two basic places in a webhook message to display text.  

* The `text` parameter, which is the main body of the message. This is required _unless_ you send in `attachments`. 
* Attachments in incoming webhooks are not attachments in the sense of email attachments; they're not files. They're additional chunks of data that can help with formatting and clarifying the message that your webhook posts. You can have up to ten attachments per message. 

We'll put the linked name of the person who performed the search in the main message `text` field, along with the text they searched for.

The search results will go in an attachment. Because Wikipedia will always return more than one result, we'll put the primary result in the attachment's text field, then the other options in attachment fields, which will help differentiate them from the primary result. To keep the in-channel message from getting too long, we'll just display the link for each of those items.



<!-- 
* Message
	* text - The basic text of the message. We'll use that to send in some information about the search that was run.
	* channel - Every incoming webhook has a default channel, set in the configuration page, that it posts to. This parameter lets you override that. We'll be using that.
	* username - Every incoming webhook has a default name, set in the configuration page, that it posts with. This parameter lets you override that. We won't need to use this; we'll use the setting from the configuration page.
	
Each attachment is part of the message itself, but I'm going to list the parameters available to attachments separately.
	
* Attachment(s)
	* title - Each attachment can have its own title. This will be bold text.
	* text - The text of the attachment itself. Conforms to standard message formatting rules.
	* fallback - Used in the event of missing data. A good place for an error message.
	* fields - Fields default to the full width of the message, but can be made half-width and displayed side by side, for smaller bits of data. Each field has `title` and `value` parameters. To make a field half-width, send in a `short` parameter set to `true`.
 -->
	
## Creating the webhook payload


We'll use an `if` statement to see if `$wiki_response` contains anything, and if it does, we'll decode the JSON and put it into a PHP array, which is much faster and easier to work with than the JSON text string.

```php
if($wiki_response !== FALSE){
	$wiki_array = json_decode($wiki_response);
```

While we're working with the array, we'll put our non-primary results into their own array called `$other_options` so they'll be easier to work with. We're only going to display the link for each of these items, which are all in the third sub-array of `$wiki_array`. 

```php
$other_options = $wiki_array[3];
```
Since we only need the alternate options, we can use `array_shift` to remove the first item from our new array. Finally, we'll set the number of alternate options as a variable that we can display later.

```php
$first_item = array_shift($other_options);
$other_options_count = count($other_options);
```

To identify who performed the Wikipedia search, we'll insert the linked username of the user, along with the text that was searched for. Both the user_id and user_name are part of the `$_POST` variables that came over with the slash command. This will also be the start of the string that we'll send to Slack as the `text` parameter of the main message.

```php
$message_text = "<@".$user_id."|".$user_name."> searched for *".$text."*.\n";
```
Next we want to verify whether the primary search result is a disambiguation page or not. If the first item in `$wiki_array[2]` contains the string "may refer to:", then we know it's a disambiguation page. (**Note:** This is not the greatest way to test something like this, because Wikipedia could conceivably change that text, but it's the only bit of info in our JSON object that identifies these pages.)

In PHP we can use the `strpos()` function to check whether a specific string exists within any other string. That's useful in this case because we don't know what the value of `$wiki_array[2][0]` is at this point. 

```php
if (strpos($wiki_array[2][0],"may refer to:") !== false) {
	$disambiguation_check = TRUE;
}
```

	$message_primary_title		=	$wiki_array[1][0];
	$message_primary_summary	=	$wiki_array[2][0];
	$message_primary_link		=	$wiki_array[3][0];

	if(count($wiki_array[1]) == 0){
		$message_text = "Sorry! I couldn't find anything like that.";
	} else {
		if ($disambiguation_check == TRUE) { // see if it's a disambiguation page
			$message_text	.= "There are several possible results for ";
			$message_text	.= "*<".$wiki_att_link."|".$text.">*.\n";
			$message_text	.= $wiki_att_link;
			$message_other_title = "Here are some of the possibilities:";
		} else {
			$message_text	.= 	"*<".$wiki_att_link."|".$wiki_att_title.">*\n";
			$message_text	.= 	$wiki_att_desc."\n";
			$message_text	.= 	$wiki_att_link;
			$message_other_title 	= 	"Here are a few other options:";
		}
		foreach ($other_options as $value) {
			$message_other_options .= $value."\n";
		}
	}
}else{
	$message_text = "I didn't get a response from Wikipedia.";
}
```


<!-- Slash command config -->

To include your new slash command in the autocomplete list, check the box, then add a short description and a usage hint. This is especially useful if you need to create a longer command for your users. The description and usage hint will display in the autocomplete list.

Finally, enter the descriptive label. This is what will show on the list of slash commands in your integrations list page, so make it something relevant.

![Add hint, usage, and label](hint-usage-label.png)



