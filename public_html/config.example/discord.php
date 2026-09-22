<?php
/*
	This is your Discord bot's configuration
	
	$discordEnabled - true to enable Discord bot connection, false to disable
	$secret - Your bot's secret code
	$bottoken - Your bot's token
	
	If you want DM notifications, this is required
*/
$discordEnabled = false;
$secret = "";
$bottoken = "";
/*
	This is Discord webhooks configuration
	
	$webhooksEnabled - true to enable rate webhooks, false to disable
	$webhooksToEnable - What webhooks you want to enable
	Current available webhooks:
		"rate" - rate/unrate webhooks
		"suggest" - suggested levels
		"ban" - bans/unbans
		"daily" - dailies/weeklys
		"register" - new registered accounts
		"levels" - levels upload/change/deletion
		"account" - accounts change
		"lists" - lists upload/change/deletion
		"mods" - mods promotion/change/demotion
		"gauntlets" - Gauntlets creation/change
		"mappacks" - Map Packs creation/change
		"warnings" - warnings about something happening on GDPS (automod)
	
	Rates:
		$rateWebhook - Webhook link to channel you want to send rates to (PLAYER)
		$suggestWebhook - Webhook link to channel you want to send moderators suggest requests to (MOD)
		$dailyWebhook - Webhook link to channel you want to send new dailies and weeklys (PLAYER)
	Bans:
		$banWebhook - Webhook link to channel you want to send ban/unban messages (MOD)
	Logs:
		$logsRegisterWebhook - Webhook link to channel you want to send new registered accounts (MOD)
		$logsLevelChangeWebhook - Webhook link to channel you want to send level uploads/changes/deletes (MOD)
		$logsAccountChangeWebhook - Webhook link to channel you want to send account changes (MOD)
		$logsListChangeWebhook - Webhook link to channel you want to send lists uploads/changes/deletes (MOD)
		$logsModChangeWebhook - Webhook link to channel you want to send mod promotions/changes/demotions (MOD)
		$logsGauntletChangeWebhook - Webhook link to channel you want to send gauntlet creations/changes (MOD)
		$logsMapPackChangeWebhook - Webhook link to channel you want to send map pack creations/changes (MOD)
		$warningsWebhook - Webhook link to channel you want to send warnings (MOD)
		
	$dmNotifications - true to enable rates and demonlist notifications to player DMs (if he connected his Discord account with in-game account), false to disable
	
	$webhookLanguage - Language of webhooks
	Current available languages:
		EN - English (English)
		RU - Russian (Русский)
		TR - Turkish (Türkçe)
		UA - Ukrainian (Українська)
		FR - French (Français)
		ES - Spanish (Español)
		PT - Portuguese (Português)
  		CZ - Czech (Čeština)
  		PL - Polish (Polski)
  		IT - Italian (Italiano)
		VI - Vietnamese (Tiếng Việt)
		ID - Indonesian (Bahasa Indonesia)
	
	Emojis:
		$likeEmoji - Custom like emoji (👍)
		$dislikeEmoji - Custom dislike emoji (👎)
		$downloadEmoji - Custom download emoji (⤵️)
		$tadaEmoji - Custom tada emoji (🎉)
		$sobEmoji - Custom sob emoji (😭)
	
	$difficultiesURL - URL from where difficulties should be retrieved for rate/send webhooks; Don't forget about slash at the end!
	
	Embed config:
		$authorURL - URL to open when author text is clicked
		$authorIconURL - Author icon URL
		$rateTitleURL - URL to open when rate/unrate title text is clicked
		$linkTitleURL - URL to open when account linking title text is clicked
		$logsRegisterTitleURL - URL to open when new registered account text is clicked
		$logsLevelChangeTitleURL - URL to open when changed level text is clicked
		$logsAccountChangeTitleURL - URL to open when changed account text is clicked
		$logsListChangeTitleURL - URL to open when changed list text is clicked
		$logsModChangeTitleURL - URL to open when changed moderator text is clicked
		$logsGauntletChangeTitleURL - URL to open when changed Gauntlet text is clicked
		$logsMapPackChangeTitleURL - URL to open when changed Map Pack text is clicked
		$warningsTitleURL - URL to open when warning text is clicked
		
		$successColor - Color for succeeded actions (rate, unban, upload, etc)
			Optional, if you leave it blank you will enable automatic colors for the webhook based on the difficulty of the level
		$failColor - Color for failed actions
		$pendingColor - Color for pending actions
		$dailyColor - Color for daily webhooks
		$weeklyColor - Color for weekly webhooks
		$eventColor - Color for event webhooks
		$logsRegisterColor - Color for new registered accounts webhooks
		
		$footerIconURL - Footer icon URL
		$linkThumbnailURL - Image to show for account linking
		$unlinkThumbnailURL - Image to show for account unlinking
		$acceptThumbnailURL - Image to show for accepting account linking
		$banThumbnailURL - Image to show for banning players
		$unbanThumbnailURL - Image to show for unbanning players
		$logsRegisterThumbnailURL - Image to show for registered accounts
		$logsAccountChangeThumbnailURL - Image to show for changing accounts
		$logsModChangeThumbnailURL - Image to show for changing moderators
		$logsGauntletChangeThumbnailURL - Image to show for changing Gauntlets
*/
$webhooksEnabled = false;
$webhooksToEnable = ["rate", "suggest", "ban", "daily", "register", "levels", "account", "lists", "mods", "gauntlets", "mappacks", "warnings"];
$rateWebhook = "";
$suggestWebhook = "";
$banWebhook = "";
$dailyWebhook = "";
$logsRegisterWebhook = "";
$logsLevelChangeWebhook = "";
$logsAccountChangeWebhook = "";
$logsListChangeWebhook = "";
$logsModChangeWebhook = "";
$logsGauntletChangeWebhook = "";
$logsMapPackChangeWebhook = "";
$warningsWebhook = "";
$dmNotifications = false;

$webhookLanguage = 'EN';
$likeEmoji = ":+1:";
$dislikeEmoji = ":-1:";
$downloadEmoji = ":arrow_heading_down:";
$tadaEmoji = ":tada:";
$sobEmoji = ":sob:";

$difficultiesURL = "https://gcs.skin/WTFIcons/difficulties/";

$authorURL = "";
$authorIconURL = "";
$rateTitleURL = "";
$linkTitleURL = "";
$logsRegisterTitleURL = "";
$logsLevelChangeTitleURL = "";
$logsAccountChangeTitleURL = "";
$logsListChangeTitleURL = "";
$logsModChangeTitleURL = "";
$logsGauntletChangeTitleURL = "";
$logsMapPackChangeTitleURL = "";
$warningsTitleURL = "";

$successColor = "BBFFBB";
$failColor = "FFBBBB";
$pendingColor = "FFCCBB";
$dailyColor = "FF9999";
$weeklyColor = "CACACA";
$eventColor = "EEB3E5";
$logsRegisterColor = "BBFFBB";

$footerIconURL = "";
$linkThumbnailURL = "";
$unlinkThumbnailURL = "";
$acceptThumbnailURL = "";
$banThumbnailURL = "";
$unbanThumbnailURL = "";
$logsRegisterThumbnailURL = "";
$logsAccountChangeThumbnailURL = "";
$logsModChangeThumbnailURL = "";
$logsGauntletChangeThumbnailURL = "";

/* 
	This is the text which will be sent with notifications but outside of embed.
	You can mention roles: <@&role_id>
 	And people: <@user_id>
  	And channels: <#channel_id>
	
	$rateNotificationText - Text to show when rating a level
	$unrateNotificationText - Text to show when unrating a level
	$suggestNotificationText - Text to show when suggesting a level
	$banNotificationText - Text to show when banning/unbanning people
	$dailyNotificationText - Text to show when new daily level appears
	$weeklyNotificationText - Text to show when new weekly level appears
	$eventNotificationText - Text to show when new event level appears
	$logsRegisterNotificationText - Text to show when someone registeres new account
	$logsLevelChangedNotificationText - Text to show when someone changes some level
	$logsAccountChangedNotificationText - Text to show when someone changes some account
	$logsListChangedNotificationText - Text to show when someone changes some list
	$logsModChangedNotificationText - Text to show when someone changes some moderator
	$logsGauntletChangedNotificationText - Text to show when someone changes some Gauntlet
	$logsMapPackChangedNotificationText - Text to show when someone changes some Map Pack
	$warningsNotificationText - Text to show when warning is sent
*/

$rateNotificationText = "";
$unrateNotificationText = "";
$suggestNotificationText = "";
$banNotificationText = "";
$dailyNotificationText = "";
$weeklyNotificationText = "";
$eventNotificationText = "";
$logsRegisterNotificationText = "";
$logsLevelChangedNotificationText = "";
$logsAccountChangedNotificationText = "";
$logsListChangedNotificationText = "";
$logsModChangedNotificationText = "";
$logsGauntletChangedNotificationText = "";
$logsMapPackChangedNotificationText = "";
$warningsNotificationText = "";
?>