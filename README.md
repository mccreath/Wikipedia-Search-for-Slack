# slackipedia
PHP script for searching Wikipedia from Slack.

Requirements 

* custom slash command on Slack
* internal webhook on Slack
* a web server running PHP 5

Comments in the script will walk you through the setup if you're familiar with those three things. There's also a tutorial in this README.


## Tutorial

This very simple demo will walk you through the process of setting up both a custom slash command (https://api.slack.com/slash-commands) and an incoming webhook (https://api.slack.com/incoming-webhooks).

Wikipedia is good to use for this because you don't need an API key to access their search API. All you need to do is identify your script with a user agent string (which we'll cover in a bit). In fact, because this is the default for all MediaWiki installations, you could repurpose this script to search _any_ site built on MediaWiki.

## What you'll need:

* A hosting account running PHP 5 where you can put the script we're going to write
* A Slack account (a free one is fine)


## The Script


## Set up your slash command

Go to your integrations page at Slack (http://my.slack.com/services/new) and scroll down to the bottom section, "DIY Integrations & Customizations". Click on the "Add" button for "Slash Commands"