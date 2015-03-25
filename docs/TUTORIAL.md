# Tutorial

// screenshots should be 714 pixels wide

This very simple demo will take you through the process of setting up both a custom slash command (https://api.slack.com/slash-commands) and an incoming webhook (https://api.slack.com/incoming-webhooks).

Wikipedia is good to use for this because you don't need an API key to access their search API. All you need to do is identify your script with a user agent string (which we'll cover in a bit). In fact, because this is the default for all MediaWiki installations, you could repurpose this script to search _any_ site built on MediaWiki.

## What we'll be using

* PHP (http://php.net)
* JSON (JavaScript Object Notation - http://json.org/)
* cURL (http://curl.haxxe.se)

Don't worry too much if you've never used one or more of those. Our use of them will be thoroughly explained in the tutorial.

## What you'll need:

* A hosting account running PHP 5 and cURL where you can put the script we're going to write
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

// Add webhook screenshot

All incoming webhooks require a default channel to post to. We're going to see how to override that default later, but for now, either pick one of your existing channels or use the "create new channel" option to make new channel.

When you've done that click the "Add Incoming Webhook Integration" button.

// Select channel and add integration screenshot

Put "Slackipedia" in the Descriptive Label field. This will help you distinguish this webhook from any others you set up in your list of configured integrations.

// Descriptive field screenshot

Also put "Slackipedia" in the Customize Name field. This is what the webhook will use as a "username" when it posts to your channels.

// Customize name screenshot

PRO TIP: Save your settings now before trying to add the custom icon. There's a little bug with the webhook page right now where you'll have to re-enter some of your settings if you don't save them first.

Now you can upload the Wikipedia logo icon that's in the code package you downloaded. (Or, you know, use something inferior.)

// Custom icon screenshot

Save your settings again, and you're done with the webhook for the moment.

## The PHP script

* Get a text editor (TextWrangler and ... )
* Talk about cURL and how it works in PHP
* Set up config
* Step by step through the rest

/* Slash command config */

To include your new slash command in the autocomplete list, check the box, then add a short description and a usage hint. This is especially useful if you need to create a longer command for your users. The description and usage hint will display in the autocomplete list.

Finally, enter the descriptive label. This is what will show on the list of slash commands in your integrations list page, so make it something relevant.

![Add hint, usage, and label](hing-usage-label.png)



