PageSync

<img alt="PageSync" width="300" src="assets/images/pagesync.png">

Export and import wiki pages

Please visit https://www.mediawiki.org/wiki/Extension:PageSync for default information.
Detailed documentation can be found here : https://www.open-csp.org/DevOps:Doc/PageSync

#### Development

* 2.1.3 Maintenance script color difference between success and skipped
* 2.1.2 Add before pagedisplay hook back in
* 2.1.1 fixed bug when synced page was altered
* 2.1.0 Add Installing Shared file from PageSync Repo
* 2.0.10 Install share file bug
* 2.0.9 Extra Share file checks added and non valid URL warning catched
* 2.0.8 PHP Warning removed
* 2.0.7 seperated config from main
* 2.0.6 removed PHP notice and newline ( Gitlab #13 )
* 2.0.5 Added maintenance option **rebuild-files** to rebuild all files from the index.
* 2.0.4 fixed bug in Shared file import
* 2.0.3 Added onArticleDelete hook ( this is deprecated, but we only support LTS versions of MW )
* 2.0.2 Fixed title look-up in conversion to version 2
* 2.0.0 New file management system to fix a problem with multi-language wikis
* 1.5.2 Added overview installing shared file through Maintenance script
* 1.5.1 Made maintenance option silent more silent. Added special option.
* 1.5.0 Sharing Files added. Rename to PageSync.
* 1.2.0 Rename WSPageSync to PageSync and move from gitlab to github.
* 1.1.0 Rewrote deprecated 1.35.1+ code. Unchanged pages will no longer updates sync files
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
