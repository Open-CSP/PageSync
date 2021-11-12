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
		$this->addOption(
			'summary',
			'Additional text that will be added to the files imported History. [optional]',
			false,
			true,
			"s"
		);
		$this->addOption(
			'user',
			'Your username. Will be added to the import log. [mandatory]',
			true,
			true,
			"u"
		);
		$this->addOption(
			'rebuild-index',
			'Will recreate the index file from existing file structure'
		);
		$this->addOption(
			'force-rebuild-index',
			'Used with rebuild-index. This forces rebuild-index without prompting for user interaction'
		);
	}

	/**
	 * @param string $filePath
	 * @param string $filename
	 * @param mixed $user
	 * @param string $content
	 * @param string $summary
	 * @param mixed $timestamp
	 *
	 * @return mixed
	 */
	public function uploadFileToWiki(
		string $filePath,
		string $filename,
		$user,
		string $content,
		string $summary,
		$timestamp
	) {
		$ret = array();
		global $wgUser;
		if ( ! file_exists( $filePath ) ) {
			return 'Cannot find file';
		}

		if ( $user === false ) {
			return 'Cannot find user';
		}
		$wgUser = $user;
		$base   = UtfNormal\Validator::cleanUp( wfBaseName( $filename ) );
		# Validate a title
		$title = Title::makeTitleSafe(
			NS_FILE,
			$base
		);
		if ( ! is_object( $title ) ) {
			return "{$base} could not be imported; a valid title cannot be produced";
		}

		$image          = wfLocalFile( $title );
		$mwProps        = new MWFileProps( MediaWiki\MediaWikiServices::getInstance()->getMimeAnalyzer() );
		$props          = $mwProps->getPropsFromPath(
			$filePath,
			true
		);
		$flags          = 0;
		$publishOptions = [];
		$handler        = MediaHandler::getHandler( $props['mime'] );
		if ( $handler ) {
			$metadata = Wikimedia\quietCall(
				'unserialize',
				$props['metadata']
			);

			$publishOptions['headers'] = $handler->getContentHeaders( $metadata );
		} else {
			$publishOptions['headers'] = [];
		}
		$archive = $image->publish(
			$filePath,
			$flags,
			$publishOptions
		);

		if ( ! $archive->isGood() ) {
			return $archive->getWikiText(
				false,
				false,
				'en'
			);
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
		$bot        = false;

		if ( WSpsHooks::$config === false ) {
			WSpsHooks::setConfig();
		}
		$versionCurrent = WSpsHooks::$config['version'];

		$IP = getenv( 'MW_INSTALL_PATH' );

		if ( $IP === false ) {
			$IP = __DIR__ . '/../..';
		}
		echo "\n\n\n";
		echo "********************************************************************\n";
		echo str_pad( "** WSPageSync version \e[36m$versionCurrent\e[0m", 75 ) . "**\n";
		echo "** /WSps/maintenance/WSps.maintenance.php                         **\n";
		echo "********************************************************************\n";
		echo "** Import pages that have been synced by the WSPageSync extension **\n";
		echo "********************************************************************\n";

		if ( $this->hasOption( 'autodelete' ) && strtolower( $this->getOption( 'autodelete' ) ) === 'true' ) {
			$autoDelete = true;
			echo "\n[Auto delete files turned on]\n";
		}


		if( WSpsHooks::checkFileConsistency() === false ) {
			$this->fatalError( "\n\e[41mConsistency check failed. Please read instructions on converting old file formats to new."  . "\e[0m\n" );
			return;
		}

		if ( $this->hasOption( 'rebuild-index' ) ) {
			// We need to rebuild the index file here.
			if( $this->hasOption('force-rebuild-index') === false ) {
				echo "\n[Rebuilding index file from file structure]\n";
				$answer = strtolower( readline( "Are you sure (y/n)" ) );
				if ( $answer !== "y" ) {
					die( "no action\n\n" );
				}
			}
			echo "\n[Rebuilding index file from file structure --RUN--]\n";
			if ( WSpsHooks::$config === false ) {
				WSpsHooks::setConfig();
			}
			$path          = WSpsHooks::$config['exportPath'];
			$infoFilesList = glob( $path . "*.info" );
			$cnt           = 0;
			$index = array();
			foreach( $infoFilesList as $infoFile ){
				$content = json_decode( file_get_contents( $infoFile ), true );
				$fName = $content['filename'];
				$fTitle = $content['pagetitle'];
				$index[$fName] = $fTitle;
				$cnt++;
			}
			WSpsHooks::saveFileIndex( $index );
			echo "\nIndex Rebuild with $cnt file(s).\nDone!\n";
			die();
		}

		$summary = $this->getOption(
			'summary',
			'Imported by WSPageSync'
		);

		if ( $this->hasOption( 'user' ) ) {
			$user = $this->getOption( 'user' );
		} else {
			$this->fatalError( "User argument is mandatory." );

			return;
		}

		$user = User::newFromName( $user );

		if ( ! $user ) {
			$this->fatalError( "Invalid username\n" );
		}
		if ( $user->isAnon() ) {
			$user->addToDatabase();
		}
		$exit = 0;

		$successCount = 0;
		$failCount    = 0;
		$skipCount    = 0;

		WSpsHooks::setConfig();
		if ( WSpsHooks::$config === false ) {
			$this->fatalError( wfMessage( 'wsps-api-error-no-config-body' )->text() . "\n" );
		}
		$this->filePath = WSpsHooks::$config['exportPath'];
		$data           = WSpsHooks::getAllPageInfo();


		foreach ( $data as $page ) {
			$content = array();
			if ( isset( $page['isFile'] ) && $page['isFile'] === true ) {
				$fpath = WSpsHooks::$config['exportPath'] . $page['filestoredname'];
				$text  = WSpsHooks::getFileContent(
					$page['filename'],
					SlotRecord::MAIN
				);
				if ( $text === false ) {
					$this->fatalError( "Cannot read content of file : " . $page['filename'] );
				}
				$resultFileUpload = $this->uploadFileToWiki(
					$fpath,
					$page['fileoriginalname'],
					$user,
					$text,
					$summary,
					wfTimestampNow()
				);
				if ( $resultFileUpload !== true ) {
					$this->fatalError( $resultFileUpload );
				}
				$successCount++;
				$this->output( "Uploaded " . $page['fileoriginalname'] . "\n" );
				continue;
			}
			$pageName = $page['pagetitle'];
			$pageSlots = explode(
				',',
				$page['slots']
			);
			echo "\n\e[36mWorking with page $pageName\e[0m";
			$title = Title::newFromText( $pageName );
			if ( ! $title || $title->hasFragment() ) {
				$this->error( "Invalid title $pageName. Skipping.\n" );
				$failCount++;
				$exit = true;
				continue;
			}
			try {
				$wikiPageObject = WikiPage::factory( $title );
			} catch ( MWException $e ) {
				echo "Could not create a WikiPage Object from title " . $title->getText(
					) . '. Message ' . $e->getMessage();
				$failCount++;
				$exit = true;
				continue;
			}
			if ( is_null( $wikiPageObject ) ) {
				echo "Could not create a WikiPage Object from Article Id. Title: " . $title->getText();
				$failCount++;
				$exit = true;
				continue;
			}

			foreach ( $pageSlots as $slot ) {
				echo "\nGetting content for slot $slot.";
				$content[ $slot ] = WSpsHooks::getFileContent(
					$page['filename'],
					$slot
				);
				if ( false === $content[ $slot ] ) {
					$failCount ++;
					$this->output(
						"\n\e[41mFailed " . $page['pagetitle'] . " with slot: " . $slot . ". Could not find file:" . $page['filename'] . "\e[0m\n"
					);
					unset( $content[$slot] );
				}
			}
			$result  = WSpsHooks::editSlots(
				$user,
				$wikiPageObject,
				$content,
				$summary
			);
			if ( false === $result['result'] ) {
				list( $result, $errors ) = $result;
				$failCount++;
				foreach( $errors as $error ) {
					$this->output(
						"\n\e[41mFailed " . $page['pagetitle'] . " with. Message:" . $error . "\e[0m\n"
					);
				}
				//echo "\n$message\n";
			} else {
				if ( $result['changed'] === false ) {
					$successCount++;
					$this->output(
						"\n\e[42mSuccessfully changed " . $page['pagetitle'] . " and slots " . $page['slots'] . "\e[0m\n"
					);
				} else {
					$skipCount++;
					$this->output(
						"\n\e[42mSkipped no change for " . $page['pagetitle'] . " and slots " . $page['slots'] . "\e[0m\n"
					);
				}
			}

		}



		$this->output( "Done! $successCount succeeded, $skipCount skipped.\n" );
		if ( $exit ) {
			$this->fatalError(
				"Import failed with $failCount failed pages.\n",
				$exit
			);
		}
	}
}

$maintClass = importPagesIntoWiki::class;
require_once RUN_MAINTENANCE_IF_MAIN;