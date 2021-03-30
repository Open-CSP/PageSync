<?php


//error_reporting( -1 );
//ini_set( 'display_errors', 1 );

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";



class importPagesIntoWiki extends Maintenance {

	var $filePath = '';

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Import pages into Wiki that have been set to sync by the WSPageSync extension.\n";
		$this->addOption( 'summary', 'Additional text that will be added to the files imported History. [optional]', false, true, "s" );
		$this->addOption( 'user', 'Your username. Will be added to the import log. [mandatory]', true, true, "u" );
		$this->addOption( 'use-timestamp', 'Use the modification date of the page as the timestamp for the edit, instead of time of import' );
		$this->addOption( 'overwrite', 'Overwrite existing pages. If --use-timestamp is passed, this ' .
		                               'will only overwrite pages if the date in the .info file is modified since the page was last modified.' );
		$this->addOption( 'rc', 'Place revisions in RecentChanges.' );
	}

	/**
	 * @param string $filePath
	 * @param string $filename
	 * @param $user
	 * @param string $content
	 * @param string $summary
	 * @param $timestamp
	 *
	 * @return mixed
	 */
	public function uploadFileToWiki( $filePath, $filename, $user, $content, $summary, $timestamp ) {
		$ret = array();
		global $wgUser;
		if( !file_exists( $filePath ) ) {
			return 'Cannot find file';
		}

		if( $user === false ){
			return 'Cannot find user';
		}
		$wgUser = $user;
		$base = UtfNormal\Validator::cleanUp( wfBaseName( $filename ) );
		# Validate a title
		$title = Title::makeTitleSafe( NS_FILE, $base );
		if ( !is_object( $title ) ) {
			return "{$base} could not be imported; a valid title cannot be produced";
		}

		$image = wfLocalFile( $title );
		$mwProps = new MWFileProps( MediaWiki\MediaWikiServices::getInstance()->getMimeAnalyzer() );
		$props = $mwProps->getPropsFromPath( $filePath, true );
		$flags = 0;
		$publishOptions = [];
		$handler = MediaHandler::getHandler( $props['mime'] );
		if ( $handler ) {
			$metadata = Wikimedia\quietCall( 'unserialize', $props['metadata'] );

			$publishOptions['headers'] = $handler->getContentHeaders( $metadata );
		} else {
			$publishOptions['headers'] = [];
		}
		$archive = $image->publish( $filePath, $flags, $publishOptions );

		if ( !$archive->isGood() ) {
			return $archive->getWikiText( false, false, 'en' );
		}
		$image->recordUpload2(
			$archive->value,
			$summary,
			$content,
			$props,
			$timestamp
		);
		return true;

	}

	public function execute() {

		if ( wfReadOnly() ) {
			$this->fatalError( "Wiki is in read-only mode; you'll need to disable it for import to work." );
		}
		$autoDelete = false;
		$bot = false;

		$IP = getenv( 'MW_INSTALL_PATH' );

		if ( $IP === false ) {
			$IP = __DIR__ . '/../..';
		}
		echo "\n\n\n";
		echo "********************************************************************\n";
		echo "** /WSps/maintenance/WSps.maintenance.php                         **\n";
		echo "********************************************************************\n";
		echo "** Import pages that have been synced by the WSPageSync extension **\n";
		echo "********************************************************************\n";

		if( $this->hasOption( 'autodelete' ) && strtolower( $this->getOption( 'autodelete' ) ) === 'true' ) {
			$autoDelete = true;
			echo "\n[Auto delete files turned on]\n";
		}

		$summary = $this->getOption( 'summary', 'Imported by WSPageSync' );

		if( $this->hasOption( 'user' ) ) {
			$user = $this->getOption( 'user' );
		} else {
			$this->fatalError( "User argument is mandatory." );
			return;
		}
		$overwrite = $this->hasOption( 'overwrite' );
		$useTimestamp = $this->hasOption( 'use-timestamp' );
		$rc = $this->hasOption( 'rc' );

		$user = User::newFromName( $user );

		if ( !$user ) {
			$this->fatalError( "Invalid username\n" );
		}
		if ( $user->isAnon() ) {
			$user->addToDatabase();
		}
		$exit = 0;

		$successCount = 0;
		$failCount = 0;
		$skipCount = 0;


		WSpsHooks::setConfig();
		if ( WSpsHooks::$config === false ) {
			$this->fatalError( wfMessage( 'wsps-api-error-no-config-body' )->text() . "\n" );
		}
		$this->filePath = WSpsHooks::$config['exportPath'];
		$data = WSpsHooks::getAllPageInfo();

		foreach ( $data as $page ) {
			if( isset( $page['isFile'] ) && $page['isFile'] === true ) {
				$fpath = WSpsHooks::$config['exportPath'] . $page['fileoriginalname'];
				$text = WSpsHooks::getFileContent( $page['filename'] );
				if( $text === false ) {
					$this->fatalError( "Cannot read file : " . $page['filename'] );
				}
				$resultFileUpload = $this->uploadFileToWiki(
					$fpath,
					$page['fileoriginalname'],
					$user,
					$text,
					$summary,
					$timestamp
				);
				if( $resultFileUpload !== true ) {
					$this->fatalError( $resultFileUpload );
				}
				$successCount++;
				$this->output( "Uploaded " . $page['fileoriginalname'] . "\n" );
				continue;
			}
			$pageName = $page['pagetitle'];
			$tme = strtotime( $page['changed'] );
			$newTime = date('YmdHis', $tme);
			$timestamp = $useTimestamp ? $newTime : wfTimestampNow();

			$title = Title::newFromText( $pageName );
			if ( !$title || $title->hasFragment() ) {
				$this->error( "Invalid title $pageName. Skipping.\n" );
				$skipCount++;
				continue;
			}
			$exists = $title->exists();



			$oldRevID = $title->getLatestRevID();
			if ( version_compare( $GLOBALS['wgVersion'], "1.35" ) < 0 ) {
				$oldRev = $oldRevID ? Revision::newFromId( $oldRevID ) : null;
			} else {
				$revLookup = MediaWikiServices::getInstance()->getRevisionLookup();
				$oldRev = $oldRevID ? $revLookup->getRevisionById( $oldRevID ) : null;
			}
			$oldRev = $oldRevID ? Revision::newFromId( $oldRevID ) : null;
			$actualTitle = $title->getPrefixedText();

			if ( $exists ) {
				$touched = wfTimestamp( TS_UNIX, $title->getTouched() );
				if ( !$overwrite ) {
					$this->output( "Title $actualTitle already exists. Skipping.\n" );
					$skipCount++;
					continue;
				} elseif ( $useTimestamp && intval( $touched ) >= intval( $timestamp ) ) {
					$this->output( "\e[41mFile for title $actualTitle has not been modified since the " .
					               "destination page was touched. Skipping.\e[0m\n" );
					$skipCount++;
					continue;
				}
			}

			$text = WSpsHooks::getFileContent( $page['filename'] );
			if( $text === false ) {
				$this->fatalError( "Cannot read file : " . $page['filename'] );
			}

			$rev = new WikiRevision( MediaWikiServices::getInstance()->getMainConfig() );


			if ( version_compare( $GLOBALS['wgVersion'], "1.35" ) < 0 ) {
				$rev->setText( rtrim( $text ) );
				$rev->setTitle( $title );
				$rev->setUserObj( $user );
				$rev->setComment( $summary );
				$rev->setTimestamp( $timestamp );
			} else {
				$content = ContentHandler::makeContent( rtrim( $text ), $title );
				$rev->setContent( SlotRecord::MAIN, $content );
				$rev->setTitle( $title );
				$rev->setUserObj( $user );
				$rev->setComment( $summary );
				$rev->setTimestamp( $timestamp );
			}


			if( !is_null( $oldRev ) ) {
				if ( version_compare( $GLOBALS['wgVersion'], "1.35" ) < 0 ) {
					if ( $exists && $overwrite && $rev->getContent()->equals( $oldRev->getContent() ) ) {
						$this->output( "File for title $actualTitle contains no changes from the current " .
						               "revision. Skipping.\n" );
						$skipCount ++;
						continue;
					}
				} else {
					if ( $exists && $rev->getContent()->equals( $oldRev->getContent( SlotRecord::MAIN ) ) ) {
						$this->output( "File for title $actualTitle contains no changes from the current " .
						               "revision. Skipping.\n" );
						$skipCount ++;
						continue;
					}
				}
			} elseif( !$overwrite ){
				$this->output( "File for title $actualTitle seems to exist in the wiki, but no revision info " .
				               "overwrite is turned off so.. Skipping.\n" );
				$skipCount++;
				continue;
			}

			$status = $rev->importOldRevision();
			$newId = $title->getLatestRevID();

			if ( $status ) {
				$action = $exists ? 'updated' : 'created';
				$this->output( "\e[42mSuccessfully $action $actualTitle\e[0m\n" );
				$successCount++;
			} else {
				$action = $exists ? 'update' : 'create';
				$this->output( "\e[41mFailed to $action $actualTitle\e[0m\n" );
				$failCount++;
				$exit = 1;
			}

			// Create the RecentChanges entry if necessary
			if ( $rc && $status ) {
				if ( $exists ) {
					if ( is_object( $oldRev ) ) {
						if ( version_compare( $GLOBALS['wgVersion'], "1.35" ) < 0 ) {
							$oldContent = $oldRev->getContent();
						} else {
							$oldContent = $oldRev->getContent( SlotRecord::MAIN );
						}
						RecentChange::notifyEdit(
							$timestamp,
							$title,
							$rev->getMinor(),
							$user,
							$summary,
							$oldRevID,
							$oldRev->getTimestamp(),
							$bot,
							'',
							$oldContent ? $oldContent->getSize() : 0,
							$rev->getContent()->getSize(),
							$newId,
							1 /* the pages don't need to be patrolled */
						);
					}
				} else {
					RecentChange::notifyNew(
						$timestamp,
						$title,
						$rev->getMinor(),
						$user,
						$summary,
						$bot,
						'',
						$rev->getContent()->getSize(),
						$newId,
						1
					);
				}
			}


		}
		$this->output( "Done! $successCount succeeded, $skipCount skipped.\n" );
		if ( $exit ) {
			$this->fatalError( "Import failed with $failCount failed pages.\n", $exit );
		}
	}
}

$maintClass = importPagesIntoWiki::class;
require_once RUN_MAINTENANCE_IF_MAIN;