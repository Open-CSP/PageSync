# WSPageSync
Export and import wiki pages

## Installation
Grab an instance from the Wikibase Task repository. Create a "WSPageSync" folder in your Wiki extensions folder and extract the files there.

## Setup
WSPS needs a full path to a directory to store the file that can be synced. e.g. $IP/wspsFiles
This can be set in the localsettings as  : 
```php
$wgWSPageSync['filePath'] =  $IP . '/wspsFiles';
```
Make sure the map has the correct right for WSPageSync to store files
It is also a good practice to store these files outside of your html folder.

Files from the File namespace will also be synced.


## Special page usage
By default the special page shows a list of all Wiki pages set for syncing.

Further actions can be found in the menu on the Special Page.

## Maintenance script
Options:

- 'summary': Additional text that will be added to the files imported History.
- 'user': Your username. Will be added to the import log. [mandatory]
- 'use-timestamp': Use the modification date of the page as the timestamp for the edit.
- 'overwrite': Overwrite existing pages. If --use-timestamp is passed, this will only overwrite pages if the file has been modified since the page was last modified.
- 'rc': Place revisions in RecentChanges.

Example:
```bash
SERVER_NAME=<myservername> php extensions/WSPageSync/maintenance/WSps.maintenance.php --user 'Maintenance script' --rc --summary 'Fill database' --overwrite

```

#### Development

* 0.9.9.7 Sub specials pages visual fix for most MW skins
* 0.9.9.6 Not using user received from API anymore. Added check to only show sync button if a page can actually be synced. ( introduced by SkinTemplateNavigation::Universal )
* 0.9.9.5 Fix for skins other than Chameleon
* 0.9.9.4 Delete un-used translations
* 0.9.9.3 Clean-up Special page
* 0.9.9.2 More messy code clean-up
* 0.9.9.1 Clean-up
* 0.9.9 Initial Community release