# Tutorial

<!-- screenshots should be 714 pixels wide -->

This very simple demo will take you through the process of setting up both a custom slash command (https://api.slack.com/slash-commands) and an incoming webhook (https://api.slack.com/incoming-webhooks).

Wikipedia is good to use for this because you don't need an API key to access their search API. All you need to do is identify your script with a user agent string (which we'll cover in a bit). In fact, because this is the default for all MediaWiki installations, you could repurpose this script to search _any_ site built on MediaWiki.

## What we'll be using

* PHP (http://php.net)
* JSON (JavaScript Object Notation - http://json.org/)
* cURL (http://curl.haxxe.se)

Don't worry too much if you've never used one or more of those. Our use of them will be thoroughly explained in the tutorial.

## What you'll need:

* A plain text editor. If you want a free one, I recommend TextWrangler for Mac (http://barebones.com/products/textwrangler/) or Notepad++ for Windows (http://notepad-plus-plus.org/)
* A hosting account running PHP 5 and cURL where you can put the script we're going to write. Pretty much any shared hosting account in the world should work for this.
* A Slack account (a free one is fine)
* A custom slash command on Slack
* An internal webhook on Slack

### Slash commands

Slack's custom slash commands perform a very simple task: they take whatever text you enter after the command itself (along with some other predefined values), send it to a URL, then accept whatever the script returns and posts it as a Slackbot message to the person who issued the command. What you do with that text at the URL is what makes slash commands so useful. 

For example, you have a script that translates English to French, so you create a slash command called `/translate`, and expect that the user will enter an English word that they'd like translated into French. When the user types `/translate dog` into the Slack message field, Slack bundles up the text string `dog` with those other server variables and sends the whole thing to your script, which performs its task of finding the correct French word, `chien`, and sends it back to Slack along with whatever message you added with your script has, and Slack posts it back to the user as `The French word for "dog" is "chien"`. No one else on the team will see message, since it's from Slackbot to the user. That can be useful, but sometimes you also want to share your output, or you need  the output to be formatted in a specific way. That's where the incoming webhook comes in.

### Incoming webhooks

Incoming webhooks are common tools for services with APIs. They provide a structured way to send information into the service. Slack's incoming webhooks accept data in a format called JSON, which is a common format for this kind of data. We'll go over a little bit of it when we're setting up the webhook, but what you need to know for now is that an incoming webhook lives at a specific URL, and when send this formatted JSON to that URL, Slack can take it and post a message to any channel, group, or direct message that you have access to.

What that means for our project is that when someone uses our Wikipedia search slash command, we have a way to post the results publicly for others to see.

### The script

Our script is going to

* Take the values that the slash command sends and turn them into variables 
* Use cURL to send the search string entered by your user to Wikipedia's Search API
* Accept the results returned by the Wikipedia search and figure out what to do with them
* Format the results into a proper JSON payload for the incoming webhook
* Use cURL to send the formatted JSON to the incoming webhook's URL

## Set up your slash command

Go to your integrations page at Slack (http://my.slack.com/services/new) and scroll down to the bottom section, "DIY Integrations & Customizations". Click on the "Add" button for "Slash Commands".

![Add a slash command integration](add-slash-command.png)

Create the text command itself. This is the text that the user will type after the slash. I use `wikip`, because it's just enough to indicate that this isn't just any wiki that you're searching. But you could use the entire word `Wikipedia` or `searchwiki`. Whatever makes the most sense for your command and your users.

![Create the command](create-command.png)

For now you can leave everything else empty. We'll come back and finish setting this up in a bit. Just scroll down to the bottom and click the "Save Integration" button.

## Set up your webhook

Go to your integrations page at Slack (http://my.slack.com/services/new) and scroll down to the bottom section, "DIY Integrations & Customizations". Click on the "Add" button for "Incoming Webhooks".

<!-- Add webhook screenshot -->

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

cURL (http://curl.haxx.se) is an open source tool that lets you transfer data with URL syntax, which is what web browsers use, and as a result, much of the web uses. Being able to transfer data with URL syntax is what makes webhooks work. The thing about cURL that's useful for us is that not only can you use it from the command line (which makes it easy to use for testing things), but you can interact with it from most modern scripting language. 

PHP has had support for cURL for years, and we're going to take advantage of that so that our script can receive data from Slack and then send it back in. We'll be using a few very basic commands that are common for this type of task. All of the cURL that we use in this script will be transferrable to any other webhook script that you want to write. 

* Set up config

### Set up your config

There are a few things we're going to need for the script, so let's get those set up at the very top.

First, your incoming webhook URL. This tells the script where to send the reply it gets back from Wikipedia. Get this URL from your incoming webhook configuration page.

    $slack_webhook_url = ""; // put your webhook url between those quotes https://hooks.slack.com/services/TXXXXXXXX/BXXXXXXXX/xxxxxxxxxxxxxxxxxxxxxxxx
    
Next, the icon for your integration. You probably remember that we already set a custom icon for the webhook on the configuration page, but you can also specify within the webhook's payload. This is useful if you want to reuse the webhook itself for a few slash commands, or just as a fallback for the one on the configuration page.

    $icon_url = ""; // the URL for where you upload the image, eg http://domain.com/slackipedia/wikipedia-logo-cc-by-sa_0.png
    
And now some defaults for Wikipedia itself.

You can change the language you're searching in with this. Set it to `en` for English. You can find other language options here: <!-- languages url -->

    $wiki_lang = "en";
    
By default, the WikiMedia API will return 10 search results. That will make for a very long message in Slack, so I like to default it to 4. You can play around with this and see what makes the most sense for your team.

    $search_limit = "4"; 

Finally, the WikiMedia API requests that the client is identified by a User-Agent string (http://www.mediawiki.org/wiki/API:Etiquette#User-Agent_header). Feel free to leave this set to this, or you can update it with any info you want.

    $user_agent = "Slackipedia/1.0 (https://github.com/mccreath/slackipedia; mccreath@gmail.org)";

### Now for the action

The first thing you need to do when the script is called by your slash command is grab all the values from the command and make variables out of them. We'll use most of them at various points in the script, and the variable names will be easy to remember.

First, the command string itself. In our case, `wikip`

    $command = $_POST['command'];
    
Next, the text that was entered with the command. For this webhook, this will be the search string we send to Wikipedia.
     
    $text = $_POST['text'];
    
The token is an additional identifier that's sent with the slash command that you could use to verify that what's calling your script is actually your slash command.
<!-- think about actually adding this. not a bad example to set. just put it in a die statement. -->
    
    $token = $_POST['token'];
    
The team ID is useful if you want to use this same script for multiple teams and send back different formatting.
    
    $team_id = $_POST['team_id'];
    
The channel ID is the channel where the slash command was issued. We'll use this later in the script to tell the webhook to return the results to that same channel. 
    
    $channel_id = $_POST['channel_id'];
    
The channel name could be used the same way.
    
    $channel_name = $_POST['channel_name'];
    
The user ID is handy if you want to keep track of who's using the webhook, or to return something besides the user name with the results.
    
    $user_id = $_POST['user_id'];
    
We're going to display the user name with the webhook message, just so it's clear who caused the message to appear in the channel.
    
    $user_name = $_POST['user_name'];





* Step by step through the rest

<!-- Slash command config -->

To include your new slash command in the autocomplete list, check the box, then add a short description and a usage hint. This is especially useful if you need to create a longer command for your users. The description and usage hint will display in the autocomplete list.

Finally, enter the descriptive label. This is what will show on the list of slash commands in your integrations list page, so make it something relevant.

![Add hint, usage, and label](hint-usage-label.png)



