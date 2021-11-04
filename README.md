# WSPageSync
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

Further actions can be found in the menu on the Special Page.

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

* 1.alpha3 fixed on page save
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