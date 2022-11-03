<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use PageSync\Core\PSConfig;
use PageSync\Core\PSConverter;
use PageSync\Core\PSCore;
use PageSync\Core\PSNameSpaceUtils;
use PageSync\Core\PSSlots;
use PageSync\Helpers\PSShare;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance class to import PageSync pages
 */
class importPagesIntoWiki extends Maintenance {

	/**
	 * @var string
	 */
	public $filePath = '';

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Import pages into Wiki that have been set to sync by the PageSync extension.\n";
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
			'rebuild-files',
			'Will take the index file and re-create all files from the database'
		);
		$this->addOption(
			'rebuild-index',
			'Will recreate the index file from existing file structure'
		);

		$this->addOption(
			'convert-2-version-2',
			'Will rewrite all file and rebuild index from files.'
		);
		$this->addOption(
			'force-rebuild-index',
			'Used with rebuild-index. This forces rebuild-index without prompting for user interaction'
		);
		$this->addOption(
			'force-rebuild-files',
			'Used with rebuild-files. This forces rebuild-files without prompting for user interaction'
		);

		$this->addOption(
			'install-shared-file',
			'url or path to a PageSync share file. This will only import the pages into the wiki. Nothing else.',
			false,
			true
		);

		$this->addOption(
			'install-shared-file-from-temp',
			'Name of an already stored PageSync share file. This will only import the pages into the wiki. Nothing else.',
			false,
			true
		);

		$this->addOption(
			'silent',
			'No verbose information. Will end with number of successes and skipped pages.'
		);

		$this->addOption(
			'special',
			'Used for the Special page. Same as silent option, but result is in the following format. success : "ok|description", error: "error|error message".'
		);

		$this->addOption(
			'skip-if-page-is-changed-in-wiki',
			'For Shared Files only : Tell PageSync to not overwrite a page, when the maintenance user differs from the last user who edited the page in the wiki.'
		);
	}

	/**
	 * @param string $filePath
	 * @param string $filename
	 * @param mixed $user
	 * @param string $content
	 * @param string $summary
	 * @param mixed $timestamp
	 * @param bool $checkSameUser
	 *
	 * @return bool|string
	 */
	public function uploadFileToWiki(
		string $filePath,
		string $filename,
		$user,
		string $content,
		string $summary,
		$timestamp,
		bool $checkSameUser = false
	) {
		global $wgUser;
		if ( ! file_exists( $filePath ) ) {
			return 'Cannot find file : ' . $filePath;
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
		if ( !is_object( $title ) ) {
			return "{$base} could not be imported; a valid title cannot be produced";
		}

		if ( $checkSameUser ) {
			if ( $title->exists() ) {
				$oldRevId  = $title->getLatestRevID();
				$revLookup = MediaWikiServices::getInstance()->getRevisionLookup();
				$oldRev    = $oldRevId ? $revLookup->getRevisionById( $oldRevId ) : null;
				if ( $oldRev !== null ) {
					$revUser = $oldRev->getUser();
					if ( $revUser->getId() !== $wgUser->getId() ) {
						return "different user";
					}
				}
			}
		}

		$fileRepo       = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
		$image          = $fileRepo->newFile( $title );
		$mwProps        = new MWFileProps( MediaWiki\MediaWikiServices::getInstance()->getMimeAnalyzer() );
		$props          = $mwProps->getPropsFromPath(
			$filePath,
			true
		);
		$flags          = 0;
		$publishOptions = [];
		$handler        = MediaHandler::getHandler( $props['mime'] );
		if ( $handler ) {
			$metadata = \Wikimedia\AtEase\AtEase::quietCall(
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

		$image->recordUpload3(
			$archive->value,
			$summary,
			$content,
			$user,
			$props,
			$timestamp
		);

		return true;
	}

	/**
	 * @param string $message
	 * @param string $status
	 * @param array $collectedMessage
	 * @param bool $special
	 *
	 * @return void
	 */
	private function returnOutput(
		string $message,
		string $status = 'error',
		array $collectedMessage = [],
		bool $special = false
	) {
		if ( $special && !empty( $collectedMessage ) ) {
			$message = '<p><ul><li>' . $message . '</li>';
			foreach ( $collectedMessage as $msg ) {
				$message .= '<li>' . $msg . '</li>';
			}
			$message .= '</ul></p>';
			echo $status . '|' . $message;
		} else {
			echo $message;
		}
	}

	/**
	 * @throws MWContentSerializationException
	 * @throws MWException
	 */
	public function execute() {

		$collectedMessages = [];

		if ( wfReadOnly() ) {
			$this->fatalError( "Wiki is in read-only mode; you'll need to disable it for import to work." );
		}

		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}
		$versionCurrent = PSConfig::$config['version'];

		$IP = getenv( 'MW_INSTALL_PATH' );

		$silent = false;
		if ( $this->hasOption( 'silent' ) ) {
			$silent = true;
		}

		$special = false;
		if ( $this->hasOption( 'special' ) ) {
			$special = true;
			$silent = true;
		}

		$skipDifferentUser = false;
		if ( $this->hasOption( 'skip-if-page-is-changed-in-wiki' ) ) {
			$skipDifferentUser = true;
		}

		if ( $IP === false ) {
			$IP = __DIR__ . '/../..';
		}
		if ( !$silent ) {
			echo "\n\n\n";
			echo "********************************************************************\n";
			echo str_pad( "** PageSync version \e[36m$versionCurrent\e[0m", 75 ) . "**\n";
			echo "** /WSps/maintenance/WSps.maintenance.php                         **\n";
			echo "********************************************************************\n";
			echo "** Import pages that have been synced by the PageSync extension **\n";
			echo "********************************************************************\n";
		}
		if ( $this->hasOption( 'autodelete' ) && strtolower( $this->getOption( 'autodelete' ) ) === 'true' ) {
			$autoDelete = true;
			if ( !$silent ) {
				echo "\n[Auto delete files turned on]\n";
			}
		}


		if ( PSConverter::checkFileConsistency() === false ) {
			if ( !$silent ) {
				$this->fatalError( "\n\e[41mConsistency check failed. Please read instructions on converting old file formats to new." . "\e[0m\n" );
			} else {
				$this->returnOutput( 'Consistency check failed. Please read instructions on converting old file formats to new.' );
			}
			return;
		}

		if ( $this->hasOption( 'convert-2-version-2' ) ) {
			echo "\n[Converting to version 2 and rebuilding index file from file structure --RUN--]\n";
			$result = PSConverter::convertToVersion2();
			$cnt = $result['total'];
			$skipped = $result['skipped'];
			echo "\nWorked with $cnt file(s), skipped $skipped files and index Rebuild.\nDone!\n";
			die();
		}

		if ( PSConverter::checkFileConsistency2() === false ) {
			if ( !$silent ) {
				$this->fatalError( "\n\e[41mConsistency check failed. Please read instructions on converting old file formats to new." . "\e[0m\n" );
			} else {
				$this->returnOutput( 'Consistency check failed. Please read instructions on converting old file formats to new.' );
			}
			return;
		}

		if ( $this->hasOption( 'rebuild-files' ) ) {
			// We need to rebuild the index file here.
			if ( $this->hasOption( 'force-rebuild-files' ) === false ) {
				echo "\n[Rebuilding files from index]\n";
				$answer = strtolower( readline( "Are you sure (y/n)" ) );
				if ( $answer !== "y" ) {
					die( "no action\n\n" );
				}
			}
			echo "\n[Rebuilding files from index --RUN--]\n";
			if ( PSConfig::$config === false ) {
				PSCore::setConfig();
			}
			$indexFile = PSCore::getFileIndex();
			$cnt           = 0;
			$index = [];

			if ( $this->hasOption( 'user' ) ) {
				$userName = $this->getOption( 'user' );
			} else {
				if ( !$silent ) {
					$this->fatalError( "User argument is mandatory." );
				} else {
					$this->returnOutput( 'User argument is mandatory.' );
				}

				return;
			}
			$user = User::newFromName( $userName );
			if ( !$user ) {
				if ( !$silent ) {
					$this->fatalError( "Invalid username\n" );
				} else {
					$this->returnOutput( 'Invalid username' );
				}
			}
			if ( $user->isAnon() ) {
				$user->addToDatabase();
			}
			foreach ( $indexFile as $indexFileEntry ) {
				//echo "\nWorking on $indexFileEntry";
				$ns = PSNameSpaceUtils::getNSFromTitleString( $indexFileEntry );
				$pageTitle = PSNameSpaceUtils::titleForDisplay( $ns, $indexFileEntry );
				//echo "\nTitle: $pageTitle";
				$pageId = PSCore::getPageIdFromTitle( $pageTitle );
				//echo "\nPage ID : $pageId\n";

				$result = PSCore::addFileForExport(
					$pageId,
					$userName
				);
				if ( $result['status'] === false ) {
					die( "ERROR: " . $result['info'] );
				}

				echo "Working on page id $pageId with user $userName on title $pageTitle\n";
				$cnt++;
			}
			echo "\n$cnt files Rebuild from Index.\nDone!\n";
			die();
		}

		if ( $this->hasOption( 'rebuild-index' ) ) {
			// We need to rebuild the index file here.
			if ( $this->hasOption( 'force-rebuild-index' ) === false ) {
				echo "\n[Rebuilding index file from file structure]\n";
				$answer = strtolower( readline( "Are you sure (y/n)" ) );
				if ( $answer !== "y" ) {
					die( "no action\n\n" );
				}
			}
			echo "\n[Rebuilding index file from file structure --RUN--]\n";
			if ( PSConfig::$config === false ) {
				PSCore::setConfig();
			}
			$path          = PSConfig::$config['exportPath'];
			$infoFilesList = glob( $path . "*.info" );
			$cnt           = 0;
			$index = [];
			foreach ( $infoFilesList as $infoFile ) {
				$content = json_decode( file_get_contents( $infoFile ), true );
				$fName = $content['filename'];
				$fTitle = $content['pagetitle'];
				$index[$fName] = $fTitle;
				$cnt++;
			}
			PSCore::saveFileIndex( $index );
			echo "\nIndex Rebuild with $cnt file(s).\nDone!\n";
			die();
		}
		$zipFromTemp = false;
		if ( $this->hasOption( 'install-shared-file' ) ) {
			$zipFile = $this->getOption( 'install-shared-file' );
			$zipFromTemp = false;
			if ( !$silent ) {
				echo "\nZip File set : $zipFile\n";
			}
		} else {
			$zipFile = false;
		}

		if ( $this->hasOption( 'install-shared-file-from-temp' ) ) {
			$zipFromTemp = true;
			$zipFile = $this->getOption( 'install-shared-file-from-temp' );
			if ( !$silent ) {
				echo "\nZip File set : $zipFile\n";
			}
		} else {
			$zipFromTemp = false;
		}

		$summary = $this->getOption(
			'summary',
			'Imported by PageSync'
		);

		if ( $this->hasOption( 'user' ) ) {
			$user = $this->getOption( 'user' );
		} else {
			if ( !$silent ) {
				$this->fatalError( "User argument is mandatory." );
			} else {
				$this->returnOutput( 'User argument is mandatory.' );
			}

			return;
		}

		$user = User::newFromName( $user );

		if ( ! $user ) {
			if ( !$silent ) {
				$this->fatalError( "Invalid username\n" );
			} else {
				$this->returnOutput( 'Invalid username' );
			}
		}
		if ( $user->isAnon() ) {
			$user->addToDatabase();
		}
		$exit = 0;

		$successCount = 0;
		$failCount    = 0;
		$skipCount    = 0;

		PSCore::setConfig();
		if ( PSConfig::$config === false ) {
			if ( !$silent ) {
				$this->fatalError( wfMessage( 'wsps-api-error-no-config-body' )->text() . "\n" );
			} else {
				$this->returnOutput( wfMessage( 'wsps-api-error-no-config-body' )->text() );
			}
			return;
		}
		$share = new PSShare();
		if ( $zipFile === false ) {
			$this->filePath = PSConfig::$config['exportPath'];
			$data           = PSCore::getAllPageInfo();
		} else {
			if ( $zipFromTemp === false ) {
				$store = $share->getExternalZipAndStoreIntemp( $zipFile );
				if ( $store !== true ) {
					if ( !$silent ) {
						$this->fatalError( $store );
					} else {
						$this->returnOutput( $store );
					}

					return;
				}
			}
			$tempPath = PSConfig::$config['tempFilePath'];
			if ( !$silent ) {
				$fileInfo         = [];
				$fileInfo['info'] = $share->getShareFileInfo( $tempPath . basename( $zipFile ) );
				if ( $fileInfo['info'] === null ) {
					die( "not a PageSync Share file\n\n" );
				}
				if ( !isset( $fileInfo['info']['project'] ) ) {
					die( "not a PageSync Share file\n\n" );
				}
				$fileInfo['file'] = $tempPath . basename( $zipFile );
				$fileInfo['list'] = $share->getShareFileContent( $tempPath . basename( $zipFile ) );

				echo $share->renderShareFileInformationConsole( $fileInfo );

				$answer = strtolower( readline( "Continue and agree to disclaimer/description (y/n)" ) );
				if ( $answer !== "y" ) {
					die( "no action\n\n" );
				}
			}

			$pathToExtractedZip = $share->extractTempZip( basename( $zipFile ) );
			if ( $pathToExtractedZip === false ) {
				if ( !$silent ) {
					$this->fatalError( 'Could not extract this Share file!' );
				} else {
					$this->returnOutput( 'Could not extract this Share file!' );
				}
				return;
			}
			$this->filePath = $pathToExtractedZip;
			$data           = $share->getFileInfoList( $pathToExtractedZip );
			if ( empty( $data ) ) {
				if ( !$silent ) {
					$this->fatalError( 'No pages in the PageSync Share file to be installed!' );
				} else {
					$this->returnOutput( 'No pages in the PageSync Share file to be installed!' );
				}
				return;
			}
		}

		foreach ( $data as $page ) {
			$content = [];
			$checkSameUser = false;
			if ( isset( $page['isFile'] ) && $page['isFile'] === true ) {
				if ( $zipFile === false ) {
					$checkSameUser = $skipDifferentUser;
					$fpath = PSConfig::$config['exportPath'] . $page['filestoredname'];
				} else {
					$fpath = $this->filePath . $page['filestoredname'];
				}
				$text = PSCore::getFileContent(
					$page['filename'],
					SlotRecord::MAIN,
					$this->filePath

				);
				if ( $text === false ) {
					if ( !$silent ) {
						$this->fatalError( "Cannot read content of file : " . $page['filename'] );
					} else {
						$this->returnOutput( "Cannot read content of file : " . $page['filename'] );
					}
					return;
				}
				$resultFileUpload = $this->uploadFileToWiki(
					$fpath,
					$page['fileoriginalname'],
					$user,
					$text,
					$summary,
					wfTimestampNow(),
					$checkSameUser
				);
				if ( $checkSameUser && $resultFileUpload === 'different user' ) {
					$skipCount++;
					if ( !$silent ) {
						$this->output(
							"File " . $page['fileoriginalname'] . " skipped. File changed in wiki.[skip-if-page-is-changed-in-wiki]\n"
						);
					} else {
						$collectedMessages[] = "File " . $page['fileoriginalname'] . " skipped. File changed in wiki.[skip-if-page-is-changed-in-wiki]\n";
					}
				} elseif ( $resultFileUpload !== true ) {
					if ( !$silent ) {
						$this->fatalError( $resultFileUpload );
					} else {
						$this->returnOutput( $resultFileUpload );
					}
					return;
				}
				if ( $resultFileUpload !== 'different user' ) {
					$successCount++;
					if ( !$silent ) {
						$this->output( "Uploaded " . $page['fileoriginalname'] . "\n" );
					} else {
						$collectedMessages[] = "Uploaded " . $page['fileoriginalname'];
					}
				}
				continue;
			}
			$pageName = $page['pagetitle'];
			$pageSlots = explode(
				',',
				$page['slots']
			);

			$ns = $page['ns'];
			$nTitle2 = PSNameSpaceUtils::titleForDisplay( $ns, $pageName );
			$title = Title::newFromText( $nTitle2 );
			if ( !$silent ) {
				echo "\n\e[36mWorking with page $nTitle2 / $pageName / $ns \e[0m";
			}
			if ( !$title || $title->hasFragment() ) {
				if ( !$silent ) {
					$this->error( "Invalid title $nTitle2. Skipping.\n" );
				} else {
					$collectedMessages[] = "Invalid title $nTitle2. Skipping.";
				}
				$failCount++;
				$exit = true;
				continue;
			}
			try {
				$wikiPageObject = WikiPage::factory( $title );
			} catch ( MWException $e ) {
				if ( !$silent ) {
					echo "Could not create a WikiPage Object from title " . $title->getText(
						) . '. Message ' . $e->getMessage();
				} else {
					$collectedMessages[] = "Could not create a WikiPage Object from title " . $title->getText(
						) . '. Message ' . $e->getMessage();
				}
				$failCount++;
				$exit = true;
				continue;
			}
			if ( $wikiPageObject === null ) {
				if ( !$silent ) {
					echo "Could not create a WikiPage Object from Article Id. Title: " . $title->getText();
				} else {
					$collectedMessages[] = "Could not create a WikiPage Object from Article Id. Title: " . $title->getText();
				}
				$failCount++;
				$exit = true;
				continue;
			}

			if ( $zipFile !== false && $skipDifferentUser ) {
				if ( $title->exists() ) {
					$oldRevId  = $title->getLatestRevID();
					$revLookup = MediaWikiServices::getInstance()->getRevisionLookup();
					$oldRev    = $oldRevId ? $revLookup->getRevisionById( $oldRevId ) : null;
					if ( $oldRev !== null ) {
						$revUser = $oldRev->getUser();
						if ( $revUser->getId() !== $user->getId() ) {
							if ( !$silent ) {
								$this->output(
									"\nPage " . $pageName . " skipped. Page changed in wiki.[skip-if-page-is-changed-in-wiki]\n"
								);
							} else {
								$collectedMessages[] = "Page " . $pageName . " skipped. File changed in wiki.[skip-if-page-is-changed-in-wiki]\n";
							}
							$skipCount++;
							continue;
						}
					}
				}
			}

			foreach ( $pageSlots as $slot ) {
				if ( !$silent ) {
					echo "\nGetting content for slot $slot.";
				}
				$content[ $slot ] = PSCore::getFileContent(
					$page['filename'],
					$slot,
					$this->filePath
				);
				if ( false === $content[ $slot ] ) {
					$failCount++;
					if ( !$silent ) {
						$this->output(
							"\n\e[41mFailed " . $page['pagetitle'] . " with slot: " . $slot . ". Could not find file:" . $page['filename'] . "\e[0m\n"
						);
					} else {
						$collectedMessages[] = "Failed " . $page['pagetitle'] . " with slot: " . $slot . ". Could not find file:" . $page['filename'];
					}
					unset( $content[$slot] );
				}
			}
			$result = PSSlots::editSlots(
				$user,
				$wikiPageObject,
				$content,
				$summary
			);
			if ( false === $result['result'] ) {
				list( $result, $errors ) = $result;
				$failCount++;
				foreach ( $errors as $error ) {
					if ( !$silent ) {
						$this->output(
							"\n\e[41mFailed " . $page['pagetitle'] . " with. Message:" . $error . "\e[0m\n"
						);
					} else {
						$collectedMessages[] = "Failed " . $page['pagetitle'] . " with. Message:" . $error;
					}
				}
			} else {
				if ( $result['changed'] === false ) {
					$successCount++;
					if ( !$silent ) {
						$this->output(
							"\n\e[42mSuccessfully changed " . $page['pagetitle'] . " and slots " . $page['slots'] . "\e[0m\n"
						);
					} else {
						$collectedMessages[] = "Successfully changed " . $page['pagetitle'] . " and slots " . $page['slots'];
					}
					if ( $zipFile === false ) {
						$infoPath = PSCore::getInfoFileFromPageID( $wikiPageObject->getId() );

						if ( $infoPath['status'] !== false && $zipFile === false ) {
							$pageInfo = json_decode(
								file_get_contents( $infoPath['info'] ),
								true
							);
							$pageInfo['pageid'] = $wikiPageObject->getId();
							file_put_contents(
								$infoPath['info'],
								json_encode( $pageInfo )
							);
						}
					}
				} else {
					$skipCount++;
					if ( !$silent ) {
						$this->output(
							"\n\e[43mSkipped no change for " . $page['pagetitle'] . " and slots " . $page['slots'] . "\e[0m\n"
						);
					} else {
						$collectedMessages[] = "Skipped no change for " . $page['pagetitle'] . " and slots " . $page['slots'];
					}
				}
			}

		}


		if ( !$silent ) {
			$this->output( "Done! $successCount succeeded, $skipCount skipped.\n" );
		} else {
			$this->returnOutput( "Done! $successCount succeeded, $skipCount skipped.\n", "ok", $collectedMessages, $special );
		}
		if ( $exit ) {
			if ( !$silent ) {
				$this->fatalError(
					"Import failed with $failCount failed pages.\n",
					$exit
				);
			} else {
				$this->returnOutput( "Import failed with $failCount failed pages." );
			}
		}
	}
}

$maintClass = importPagesIntoWiki::class;
require_once RUN_MAINTENANCE_IF_MAIN;