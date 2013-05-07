Auto-Upgrader
#############

An Ushahidi plugin to automagically upgrade an Ushahidi install

== Installation ==
1. Copy the entire /autoupgrader/ directory into your /plugins/ directory.
2. Activate the plugin.
3. Make sure your webserver can write to the folder your Ushahidi deployment is installed in. On Ubuntu you can do sudo chmod -R www-data: [your_ushahidi_folder]. If the plugin ask for your FTP details, make sure you provide the correct `host` `username` and `password`. Also your FTP user must be able to manipulate your installed Ushahidi files.
4. Refresh your deployment admin dashboard. 
5. Click on the *Click here to upgrade now* link at the top to upgrade your old install. See ![Click here to upgrade now](http://dl.dropbox.com/u/504300/upgrade_header_prompt.png)