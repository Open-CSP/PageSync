<?php
/**
 * Created by  : Wikibase Solutions
 * Project     : PageSync
 * Filename    : PSCore.php
 * Description :
 * Date        : 4-10-2022
 * Time        : 14:06
 */

namespace PageSync\Core;

use DateTime;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use MWException;
use Title;
use WikiPage;

class PSCore {

	/**
	 * Read config and set appropriately
	 */
	public static function setConfig() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wsConfig = new PSConfig();
		$wsConfig->setVersionNr();
		if ( $config->has( "PageSync" ) ) {
			$WSPageSync = $config->get( "PageSync" );
			$wsConfig->checkConfigFromMW( $WSPageSync );
		} else {
			$wsConfig->setAllDefaults();
		}
	}

	/**
	 * @param mixed &$arr
	 * @param mixed $col
	 * @param mixed $dir
	 *
	 * @return void
	 */
	public static function arraySortByColumn( &$arr, $col, $dir = SORT_ASC ) {
		$sort_col = [];
		foreach ( $arr as $key => $row ) {
			$sort_col[$key] = $row[$col];
		}
		array_multisort( $sort_col, $dir, $arr );
	}

	/**
	 * @param string $fname
	 *
	 * @return string cleaned filename, replacing not valid character with an _
	 */
	public static function cleanFileName( string $fname ) : string {
		return preg_replace(
			'/[^a-z0-9]+/',
			'_',
			strtolower( $fname )
		);
	}

	/**
	 * Read the list of files that need to be synced
	 *
	 *
	 * @return array|false|mixed
	 */
	public static function getFileIndex() {
		if ( PSConfig::$config === false ) {
			self::setConfig();
		}
		if ( PSConfig::$config === false ) {
			return false;
		}

		$indexFile = PSConfig::$config['filePath'] . 'export.index';
		if ( !file_exists( $indexFile ) ) {
			file_put_contents( $indexFile,
							   [] );

			return [];
		}
		$content = file_get_contents( $indexFile );
		if ( empty( $content ) ) {
			return [];
		}
		return json_decode(
			file_get_contents( $indexFile ),
			true
		);
	}

	/**
	 * @param int $id
	 *
	 * @return false|string Either Title as string or false
	 */
	public static function getPageTitle( int $id ) {
		$article = WikiPage::newFromId( $id );
		if ( $article instanceof WikiPage ) {
			return $article->getTitle()->getText();
		} else {
			return false;
		}
	}

	/**
	 * @param int $id
	 *
	 * @return false|string Either Title as string or false
	 */
	public static function getPageTitleForFileName( int $id ) {
		$article = WikiPage::newFromId( $id );
		if ( $article instanceof WikiPage ) {
			$title = $article->getTitle()->getText();
			$ns = $article->getTitle()->getNamespace();
			return $ns . '_' . $title;
		} else {
			return false;
		}
	}

	/**
	 * @param string $fname
	 * @param string $title
	 * @param int $ns
	 * @param string $uname
	 * @param int $id
	 * @param array $slots
	 * @param bool|array $isFile
	 * @param false|string $changed
	 * @param string $description
	 * @param string $tags
	 *
	 * @return array
	 */
	public static function setInfoContent(
		string $fname,
		string $title,
		int $ns,
		string $uname,
		int $id,
		array $slots,
		$isFile,
		$changed = false,
		string $description = "",
		string $tags = ""
	) : array {
		if ( $changed === false ) {
			$datetime = new DateTime();
			$date     = $datetime->format( 'd-m-Y H:i:s' );
		} else {
			$date = $changed;
		}
		$infoContent              = [];
		$infoContent['filename']  = $fname;
		$infoContent['pagetitle'] = $title;
		$infoContent['ns'] = $ns;
		$infoContent['username']  = $uname;
		$infoContent['changed']   = $date;
		$infoContent['pageid']    = $id;
		$infoContent['slots']     = implode(
			',',
			$slots
		);

		if ( $isFile !== false ) {
			$infoContent['isFile']           = true;
			$infoContent['fileurl']          = $isFile['url'];
			$infoContent['fileoriginalname'] = $isFile['name'];
			$infoContent['fileowner']        = $isFile['owner'];
			$infoContent['filestoredname']   = $isFile['storedfile'];
		} else {
			$infoContent['isFile'] = false;
		}
		$infoContent['description'] = $description;
		$infoContent['tags']    	= $tags;

		return $infoContent;
	}

	/**
	 * Create name for file saving
	 *
	 * @param string $fname
	 *
	 * @return string
	 */
	private static function makeDataStoreName( string $fname ) : string {
		return $fname . '.data';
	}

	/**
	 * @param mixed $id
	 * @param string $uname
	 * @param mixed $tags
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function addFileForExport( $id, string $uname, $tags = false ) : array {
		$isFile = false;
		if ( $id === null || $id === 0 ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_no_page_id' )->text()
			);
		}
		$title = self::getPageTitleForFileName( $id );
		$fileTitle = $title;
		if ( $title === false || $title === null || $fileTitle === false || $fileTitle === null ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}
		$fname = self::cleanFileName( $fileTitle );
		$ns = PSNameSpaceUtils::getNSFromId( $id );
		if ( self::isFile( $id ) !== false ) {
			// we are dealing with a file or image
			$repoGroup    = MediaWikiServices::getInstance()->getRepoGroup();
			$f            = $repoGroup->findFile( self::isFile( $id ) );
			$canonicalURL = $f->getLocalRefPath();
			if ( $canonicalURL === false ) {
				$canonicalURL = $f->getCanonicalUrl();
			}
			$baseName  = $f->getName();
			$fileOwner = $f->getUser();
			$isFile    = [
				'url'        => $canonicalURL,
				'name'       => $baseName,
				'owner'      => $fileOwner,
				'storedfile' => self::makeDataStoreName( $fname )
			];
		}
		$slotContent = PSSlots::getSlotsContentForPage( $id );
		if ( $slotContent === false || empty( $slotContent ) ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_retrievable' )->text()
			);
		}

		$result = self::putFileIndex(
			$fname,
			$title,
			$slotContent,
			$uname,
			$id,
			$isFile,
			$tags
		);
		if ( $result['status'] !== true ) {
			return PSMessageMaker::makeMessage(
				false,
				$result['info']
			);
		}
		$ret          = [];
		$ret['fname'] = $fname;
		$ret['title'] = $title;

		return PSMessageMaker::makeMessage(
			true,
			$ret
		);
	}

	/**
	 * @param string $title
	 *
	 * @return false|mixed
	 */
	public static function isTitleInIndex( string $title ) {
		$tObject = Title::newFromText( $title );
		$title = $tObject->getText();
		$id = $tObject->getArticleID();
		$ns = $tObject->getNamespace();

		$index = self::getFileIndex();
		if ( in_array(
			self::getPageTitleForFileName( $id ),
			$index
		) ) {
			$fname    = self::cleanFileName( self::getPageTitleForFileName( $id ) );
			$infoFile = self::setInfoName( $fname );
			if ( file_exists( $infoFile ) ) {
				$info = json_decode(
					file_get_contents( $infoFile ),
					true
				);

				return $info['pageid'];
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param int $id
	 * @param string $uname
	 *
	 * @return array
	 */
	public static function removeFileForExport( int $id, string $uname ) : array {
		$title = self::getPageTitleForFileName( $id );
		if ( $title === false ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}

		$fname = self::cleanFileName( $title );

		$index = self::getFileIndex();
		if ( isset( $index[$fname] ) && $index[$fname] === $title ) {
			unset( $index[$fname] );

			// save index file
			$result = self::removeFileFromIndex(
				$fname,
				$title
			);
			if ( $result === false ) {
				return PSMessageMaker::makeMessage(
					false,
					wfMessage( 'wsps-error_index_file' )->text()
				);
			}
			$slot_result = PSSlots::getSlotNamesForPageAndRevision( $id );
			$slot_names  = $slot_result['slots'];

			foreach ( $slot_names as $slot_name ) {
				$wikiFile = self::setWikiName(
					$fname,
					$slot_name
				);
				if ( file_exists( $wikiFile ) ) {
					unlink( $wikiFile );
				}
			}
			// set info filename
			$infoFile = self::setInfoName( $fname );
			if ( file_exists( $infoFile ) ) {
				$contents = json_decode(
					file_get_contents( $infoFile ),
					true
				);
				if ( isset( $contents['isFile'] ) && $contents['isFile'] === true ) {
					if ( file_exists( PSConfig::$config['exportPath'] . $contents['filestoredname'] ) ) {
						unlink( PSConfig::$config['exportPath'] . $contents['filestoredname'] );
					}
				}
				unlink( $infoFile );
			}
		}

		return PSMessageMaker::makeMessage(
			true,
			''
		);
	}

	/**
	 * @param WikiPage $article
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function pageSaved(
		WikiPage $article,
		UserIdentity $user,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord,
		EditResult $editResult
	) : bool {
		if ( $editResult->isNullEdit() ) {
			return true;
		}
		$id       = $article->getId();
		$title    = self::getPageTitleForFileName( $id );
		$fName    = self::cleanFileName( $title );
		$username = $user->getName();
		$index    = self::getFileIndex();

		if ( isset( $index[$fName] ) && $index[$fName] === $title ) {
			$result = self::addFileForExport(
				$id,
				$username
			);
		}

		return true;
	}

	/**
	 * Get all pages and their detailed info
	 *
	 * @return array|false all pages and their detailed info
	 */
	public static function getAllPageInfo( $customPath = false ) {
		if ( PSConfig::$config === false ) {
			self::setConfig();
		}
		if ( PSConfig::$config === false ) {
			return false;
		}
		$filesPath = PSConfig::$config['exportPath'];
		$fList     = self::getFileIndex();
		$data      = [];
		if ( $fList !== false && !empty( $fList ) ) {
			foreach ( $fList as $k => $v ) {
				$infoFile = $filesPath . $k . '.info';
				if ( file_exists( $infoFile ) ) {
					$data[] = json_decode(
						file_get_contents( $infoFile ),
						true
					);
				}
			}
		}

		array_multisort( array_map( 'strtotime', array_column( $data, 'changed' ) ),
						 SORT_DESC,
						 $data );

		return $data;
	}

	/**
	 * Create file name for slot saving
	 *
	 * @param string $fName
	 * @param string $slotName
	 *
	 * @return string
	 */
	private static function getFileSlotNameWiki( string $fName, string $slotName ) : string {
		return $fName . '_slot_' . $slotName . '.wiki';
	}

	/**
	 * @param string $fname
	 * @param string $slotName
	 *
	 * @return false|string
	 */
	public static function getFileContent( string $fname, string $slotName, $path = false ) {
		if ( !$path ) {
			$fileAndPath = PSConfig::$config['exportPath'] . self::getFileSlotNameWiki(
					$fname,
					$slotName
				);
		} else {
			$fileAndPath = $path . self::getFileSlotNameWiki(
					$fname,
					$slotName
				);
		}
		// echo "\nGetting file : $fileAndPath\n";

		if ( file_exists( $fileAndPath ) ) {
			return file_get_contents( $fileAndPath );
		} else {
			return false;
		}
	}

	/**
	 * Get a Page ID from a given Page Title
	 *
	 * @param string $title
	 *
	 * @return false|int
	 */
	public static function getPageIdFromTitle( string $title ) {
		$t = Title::newFromText( $title );
		if ( $t !== null ) {
			try {
				$wikiObject = WikiPage::factory( $t );
			} catch ( MWException $e ) {
				return false;
			}
			if ( $wikiObject instanceof WikiPage ) {
				return $wikiObject->getId();
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param string $fname
	 * @param string $slotName
	 *
	 * @return string
	 */
	private static function setWikiName( string $fname, string $slotName ) : string {
		return PSConfig::$config['exportPath'] . $fname . '_slot_' . $slotName . '.wiki';
	}

	/**
	 * @param string $fname
	 *
	 * @return string
	 */
	private static function setInfoName( string $fname ) : string {
		return PSConfig::$config['exportPath'] . $fname . '.info';
	}

	/**
	 * @param string $fname
	 * @param string $path
	 *
	 * @return string
	 */
	private static function setZipInfoName( string $fname, $path ) : string {
		return $path . $fname . '.info';
	}

	/**
	 * @param array $index
	 *
	 * @return false|int
	 */
	public static function saveFileIndex( array $index ) {
		$filesPath = PSConfig::$config['filePath'];
		$indexFile = $filesPath . 'export.index';

		// save index file
		return file_put_contents(
			$indexFile,
			json_encode( $index, JSON_PRETTY_PRINT )
		);
	}

	/**
	 * Edit or add a file to the index and save it.
	 *
	 * @param string $fname
	 * @param string $title
	 *
	 * @return false|int
	 */
	private static function addOrUpdateToFileIndex( string $fname, string $title ) {
		// get current indexfile
		$index = self::getFileIndex();

		// add or replace page
		$index[$fname] = $title;

		return self::saveFileIndex( $index );
	}

	/**
	 * Remove an entry from the index and save it
	 *
	 * @param string $fName
	 * @param string $title
	 *
	 * @return false|int|void
	 */
	private static function removeFileFromIndex( string $fName, string $title ) {
		$index = self::getFileIndex();
		if ( isset( $index[$fName] ) && $index[$fName] === $title ) {
			unset( $index[$fName] );

			return self::saveFileIndex( $index );
		}
		return false;
	}

	/**
	 * @param int $id
	 *
	 * @return array|false|string[]
	 */
	public static function getTagsFromPage( int $id ) {
		$file = self::getInfoFileFromPageID( $id );
		$tags = [];
		if ( $file['status'] ) {
			if ( file_exists( $file['info'] ) ) {
				$infoContent = json_decode( file_get_contents( $file['info'] ), true );
				if ( isset( $infoContent['tags'] ) && !empty( $infoContent['tags'] ) ) {
					$tags = explode( ',', $infoContent['tags'] );
				}
			}
		}
		return array_filter( $tags, 'strlen' );
	}

	/**
	 * @return array
	 */
	public static function getAllTags(): array {
		$pages = self::getAllPageInfo();
		$tags = [];
		foreach ( $pages as $page ) {
			if ( isset( $page['tags'] ) ) {
				$temp = explode( ',', $page['tags'] );
				foreach ( $temp as $single ) {
					$tags[] = $single;
				}
			}
		}
		return array_unique( $tags );
	}

	/**
	 * @param int $id
	 *
	 * @return array
	 */
	public static function getZipInfoFileFromPageID( int $id, $path ): array {
		$title = self::getPageTitle( $id );
		if ( $title === false || $title === null ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}
		return PSMessageMaker::makeMessage(
			true,
			self::setZipInfoName( self::cleanFileName( $title ), $path )
		);
	}

	/**
	 * @param int $id
	 *
	 * @return array
	 */
	public static function getInfoFileFromPageID( int $id ): array {
		$title = self::getPageTitleForFileName( $id );
		if ( $title === false || $title === null ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}
		return PSMessageMaker::makeMessage(
			true,
			self::setInfoName( self::cleanFileName( $title ) )
		);
	}

	/**
	 * @param string $fname
	 * @param string $title
	 * @param array $slotsContents
	 * @param string $uname
	 * @param int $id
	 * @param $isFile
	 * @param mixed $nTags
	 *
	 * @return array
	 */
	public static function putFileIndex(
		string $fname,
		string $title,
		array $slotsContents,
		string $uname,
		int $id,
		$isFile,
		$nTags = false
	) : array {
		// get current indexfile
		$index = self::getFileIndex();

		$ns = PSNameSpaceUtils::getNSFromId( $id );

		// add or replace page
		$index[$fname] = $title;

		//wiki export folder
		$exportFolder = PSConfig::$config['exportPath'];

		//set info filename
		$infoFile = self::setInfoName( $fname );

		// save index file
		$result = self::addOrUpdateToFileIndex(
			$fname,
			$title
		);

		if ( $result === false ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_index_file' )->text()
			);
		}
		//Set content for info file

		$slots = [];
		foreach ( $slotsContents as $k => $v ) {
			$slots[] = $k;
		}
		$description = '';
		if ( !$nTags ) {
			$tags = '';
		} else {
			$tags = $nTags;
		}
		$date = false;
		if ( file_exists( $infoFile ) ) {
			$oldFileInfo = json_decode( file_get_contents( $infoFile ), true );
			if ( isset( $oldFileInfo['description'] ) ) {
				$description = $oldFileInfo['description'];
			}
			if ( !$nTags ) {
				if ( isset( $oldFileInfo['tags'] ) ) {
					$tags = $oldFileInfo['tags'];
				}
			}
			if ( isset( $oldFileInfo['changed'] ) ) {
				$date = $oldFileInfo['changed'];
			}
		}

		$infoContent = self::setInfoContent(
			$fname,
			$title,
			$ns,
			$uname,
			$id,
			$slots,
			$isFile,
			$date,
			$description,
			$tags
		);

		if ( $isFile !== false ) {
			self::copyFile(
				$exportFolder,
				$isFile['storedfile'],
				$isFile['url']
			);
		}


		// save the info file
		$result = file_put_contents(
			$infoFile,
			json_encode( $infoContent )
		);
		if ( $result === false ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_info_file' )->text()
			);
		}
		//set wiki filenames
		$storeResults = [];
		foreach ( $slotsContents as $slotName => $slotContent ) {
			$wikiFile = self::setWikiName(
				$fname,
				$slotName
			);
			// save the content file
			$result = file_put_contents(
				$wikiFile,
				$slotContent
			);
			if ( $result === false ) {
				$storeResults[] = $slotName;
			}
		}

		if ( !empty( $storeResults ) ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_content_file' )->text() . ': ' . implode(
					',',
					$storeResults
				)
			);
		}

		return PSMessageMaker::makeMessage(
			true,
			''
		);
	}

	/**
	 * Copy a file
	 *
	 * @param string $exportFolder
	 * @param string $fName
	 * @param string $fUrl
	 */
	private static function copyFile( string $exportFolder, string $fName, string $fUrl ) {
		file_put_contents(
			$exportFolder . $fName,
			file_get_contents( $fUrl )
		);
	}

	/**
	 * @param int $pageId
	 * @param string $tags
	 * @param string $userName
	 *
	 * @return array
	 */
	public static function updateTags( int $pageId, string $tags, string $userName ): array {
		$infoFile = self::getInfoFileFromPageID( $pageId );
		if ( $infoFile['status'] ) {
			if ( file_exists( $infoFile['info'] ) ) {
				$fileInfo = json_decode( file_get_contents( $infoFile['info'] ), true );
				$fileInfo['tags'] = $tags;
				$fileInfo['username'] = $userName;
				$datetime = new DateTime();
				$fileInfo['changes'] = $datetime->format( 'd-m-Y H:i:s' );
				// save the info file
				$result = file_put_contents(
					$infoFile['info'],
					json_encode( $fileInfo )
				);
				if ( $result === false ) {
					return PSMessageMaker::makeMessage(
						false,
						wfMessage( 'wsps-error_info_file' )->text()
					);
				} else {
					return PSMessageMaker::makeMessage(
						true,
						"ok"
					);
				}
			}
		}
		return PSMessageMaker::makeMessage(
			false,
			wfMessage( 'wsps-error_info_file' )->text()
		);
	}

	/**
	 * @param string $infoFile
	 * @param string $description
	 * @param string $tags
	 *
	 * @return array
	 */
	public static function updateInfoFile( string $infoFile, string $description, string $tags ): array {
		if ( file_exists( $infoFile ) ) {
			$oldFileInfo = json_decode( file_get_contents( $infoFile ), true );
			$oldFileInfo['description'] = $description;
			$oldFileInfo['tags'] = $tags;
		} else {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_info_file_read' )->text()
			);
		}
		// save the info file
		$result = file_put_contents(
			$infoFile,
			json_encode( $oldFileInfo )
		);
		if ( $result === false ) {
			return PSMessageMaker::makeMessage(
				false,
				wfMessage( 'wsps-error_info_file' )->text()
			);
		} else {
			return PSMessageMaker::makeMessage(
				true,
				"ok"
			);
		}
	}

	/**
	 * Check to see if we are dealing with a file page
	 *
	 * @param int $id
	 *
	 * @return false|Title Either Title of false
	 */
	public static function isFile( int $id ) {
		$title = Title::newFromId( $id );

		if ( $title !== null ) {
			$ns = $title->getNamespace();
			if ( $ns === 6 || $ns === -2 ) {
				return $title;
			}
		}

		return false;
	}

	/**
	 * @param string $txt
	 *
	 * @return false|string Either Title as string or false
	 */
	public static function getPageTitleForFileNameFromText( string $txt ) {
		$title = Title::newFromText( $txt );
		$id = $title->getArticleID();
		$article = WikiPage::newFromID( $id );

		if ( $article instanceof WikiPage ) {
			$title = $article->getTitle()->getText();
			$ns = $article->getTitle()->getNamespace();
			return $ns . '_' . $title;
		} else {
			return false;
		}
	}

}
