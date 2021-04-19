# WSPageSync
Export and import wiki pages

## Installation
Grab in instance from the Wikibase Task repository. Create a "WSPageSync" folder in your Wiki extensions folder and extract the files there.

## Setup
WSPS needs a full path to a directory to store the file that can be synced. e.g. $IP/wspsFiles
This can be set in the localsettings as  : 
```php
$wgWSPageSync['filePath'] =  $IP . '/wspsFiles';
```
Make sure the map has the correct right for WSPageSync to store files


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

v 0.9.9.1 Clean-up
v 0.9.9 Initial Community release