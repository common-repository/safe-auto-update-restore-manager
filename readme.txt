=== Autoupdate Plugins & Themes ===
Contributors: codemenschen
Tags: Auto Update, Auto update Notifications, Restore, Backup, Update Manager, Backup Updates, Restore Update, Backup Plugin
Requires at least: 5.2
Tested up to: 5.6
Requires PHP: 5.6
Stable tag: 1.0
License: GPLv2

Safely Upgrade or Rollback any plugin either Automatically or Manually

== Description ==
- The plugin will create \"Advanced Update Manager\" setting tab after activation

- This Plugin will create 3 tables \"autoupdaterestore_data\", \"autoupdaterestore_backup\" and \"autoupdaterestore_updates\" for storage purpose

- If the user allows Auto Update then the plugin will set cron as per selected schedule for plugin update checking

- From \"Default\" tab
	=> User can set settings like \"To\" email address where user can get update emails, 
		\"From\" email address so, user can get update emails from a particular email 
		\"cron schedule\" user has options like \"Daily\",\"Weekly\" or \"Monthly\".

	=> Also user can upload sitemap for backup purpose, 

	=> User can take manual backup

	=> User can change Plugin Auto Update settings

	=> User can Rollback or Upgrade any plugin versions manually

- From \"Backup History\" tab

	=> From here user can view the Backup list of files

- Cron will check the updates as it scheduled

	=> By default if user select \"Auto Update\" yes then cron will set to Daily schedule

	=> First time when plugin detects update then one mail will send to the admin

	=> If user set allows to auto update plugin 

	then on second schedule cron will update plugin automatically

	=> Before updating plugin cron will take Files and Database backup

	=> After update will take backup again of updated files and Database

	=> Then will Compare both files before updates and after updates. If found any errors then \"Rollback\" the update
	and Mail to admin


[Documentation Autoupdate Plugins & Themes](https://www.codemenschen.at/docs/autoupdate-plugins-themes/ "Autoupdate Plugins & Themes")


== Installation ==
Installation and uninstallation are extremely simple. You can use WordPress’ automatic install or follow the manual instructions below.

INSTALLING:

Download the package.
Extract it to the “plugins” folder of your WordPress directory.
In the Admin Panel, go to “Plugins” and activate it.
Go to Settings -> Set up the basic settings.

UNINSTALLING:
In the Admin Panel, go to “Plugins” and deactivate the plugin.
Go to the “plugins” folder of your WordPress directory and delete the files/folder for this plugin.