WSPageSync

<img alt="WSPageSync" width="300" src="https://gitlab.wikibase.nl/community/mw-wspagesync/-/raw/master/assets/images/wspagesync.png">

Export and import wiki pages

## Installation
Grab an instance from the Wikibase Gitlab repository. Create a "WSPageSync" folder in your Wiki extensions folder and extract the files there.

## Setup
WSPS needs a full path to a directory to store the file that can be synced. e.g. $IP/wspsFiles
This can be set in the LocalSettings as  : 
```php
$wgWSPageSync['filePath'] =  $IP . '/wspsFiles';
```
Make sure the map has the correct right for WSPageSync to store files
It is also a good practice to store these files outside of your html folder.

Files from the File namespace will also be synced.

You can define what slots you want to sync. Default value is all.
If you change this value, make sure to add "main" for the main content-slot.
```php
$wgWSPageSync['contentSlotsToBeSynced'] = "all";
```
or
```php
$wgWSPageSync['contentSlotsToBeSynced'] = ['main', 'my-content-slot'];
```

## Special page usage
By default the special page shows a list of all Wiki pages set for syncing.

From the Special page you can also create, restore and delete ZIP backups.
To be able to use **ZIP Backups**, make sure ZIPArchive is installed on your PHP setup.

The special page also allows for you to do a **Semantic MediaWiki Query** to quickly add
certain pages to WSPageSync. This feature only works if you have SemanticMediaWiki extension installed.

###Update to version 0.9.9.9+

Since the structure of the files have changed as of version 0.9.9.9 to support Content Slots, some extra effort is needed if you are performing an upgrade.

We have tried to make this very effortlessly.

Once you have installed the 0.9.9.9+ update, visit the Wiki. You will notice the sync button in the admin menu has an exclamation mark. Click this and it will bring you to a Special page.

Make sure you do not sync any pages to avoid possible failures.

First thing you should do is use the new feature to create a backup. This will bring you to the backup tab and you can find your new backup file there.

Click on the WSPageSync logo to go back to the convert page. Now click convert files preview. This will give you an overview of the files affected. Click on convert files to convert all the synced files to version 0.9.9.9.

## Maintenance script
Options:

- 'rebuild-index' : Rebuild the index file from existing files in export folder
- 'force-rebuild-index' : Used with 'rebuild-index' to suppress confirmation
- 'summary': Additional text that will be added to the files imported History.
- 'user': Your username. Will be added to the import log. [mandatory]

Example:
```bash
SERVER_NAME=<myservername> php extensions/WSPageSync/maintenance/WSps.maintenance.php --user 'Maintenance script' --summary 'Fill database'
```

#### Development

* 1.0.1 Added support for different content types
* 1.0.0 release
* 1alpha5 Created handlers classes and clean Special Page. Added requirements.
* 1alpha4 Added consistency in maintenance script
* 1alpha3 fixed on page save
* 1.0ß2 Fixed weird username JavaScript error on one specific wiki
* 1.0ß Added support message when files are not in index. Removed debug information.
* 0.9.9.9 Removed Maintenance script option --rc, --overwrite and --timestamp. Added slot support. Added rebuild index option. Added backup and restore. 
* 0.9.9.8 MW codestyle
* 0.9.9.7 Sub specials pages visual fix for most MW skins
* 0.9.9.6 Not using user received from API anymore. Added check to only show sync button if a page can actually be synced. ( introduced by SkinTemplateNavigation::Universal )
* 0.9.9.5 Fix for skins other than Chameleon
* 0.9.9.4 Delete un-used translations
* 0.9.9.3 Clean-up Special page
* 0.9.9.2 More messy code clean-up
* 0.9.9.1 Clean-up
* 0.9.9 Initial Community release