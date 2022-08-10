<?php

/**
 * Hooks for WSps extension
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;

class WSpsHooks {

	public static $config = false;

	/**
	 * @param Parser $parser
	 *
	 * @throws MWException
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		global $wgOut;
		$wgOut->getOutput()->addModules( 'ext.WSPageSync.scripts' );
		self::setConfig();
	}

	/**
	 * Read config and set appropriately
	 */
	public static function setConfig() {
		global $IP;
		$config                  = MediaWikiServices::getInstance()->getMainConfig();
		$json                    = json_decode(
			file_get_contents( $IP . '/extensions/PageSync/extension.json' ),
			true
		);
		self::$config['version'] = $json['version'];
		if ( $config->has( "PageSync" ) ) {
			$wgWSPageSync = $config->get( "PageSync" );

			if ( !isset( $wgWSPageSync['allowedGroups'] ) || !is_array( $wgWSPageSync['allowedGroups'] ) ) {
				self::$config[ 'allowedGroups' ] = [ 'sysop' ];
			}
			else {
				self::$config[ 'allowedGroups' ] = $wgWSPageSync['allowedGroups'];
			}
			if ( !isset( $wgWSPageSync['fileNameSpaces'] ) && !is_array( $wgWSPageSync['fileNameSpaces'] ) ) {
				self::$config['fileNameSpaces'] = [
					6,
					-2
				];
			} else {
				self::$config['fileNameSpaces'] = $wgWSPageSync['fileNameSpaces'];
			}

			if ( ! isset( $wgWSPageSync['maintenance'] ) ) {
				self::$config['maintenance']['doNotRestoreThesePages'] = [];
				self::$config['maintenance']['restoreFrom']            = '';
			} else {
				self::$config['maintenance'] = $wgWSPageSync['maintenance'];
			}

			if ( ! isset( $wgWSPageSync['contentSlotsToBeSynced'] ) ) {
				self::$config['contentSlotsToBeSynced'] = 'all';
			} else {
				self::$config['contentSlotsToBeSynced'] = $wgWSPageSync['contentSlotsToBeSynced'];
			}

			if ( isset( $wgWSPageSync['filePath'] ) && !empty( $wgWSPageSync['filePath'] ) ) {
				$filePath   = rtrim(
					$wgWSPageSync['filePath'],
					'/'
				);
				$filePath   .= '/';
				$exportPath = $filePath . 'export/';

				if ( !file_exists( $filePath ) ) {
					mkdir(
						$filePath
					);
					chmod(
						$filePath,
						0777
					);
				}

				if ( !file_exists( $exportPath ) ) {
					mkdir(
						$exportPath
					);
					chmod(
						$exportPath,
						0777
					);
				}
				self::$config['filePath']   = $filePath;
				self::$config['exportPath'] = $exportPath;
			}
			if ( isset( $wgWSPageSync['tempFilePath'] ) && !empty( $wgWSPageSync['tempFilePath'] ) ) {
				$filePath   = rtrim(
					$wgWSPageSync['tempFilePath'],
					'/'
				);
				$filePath   .= '/';

				if ( !file_exists( $filePath ) ) {
					mkdir(
						$filePath
					);
					chmod(
						$filePath,
						0777
					);
				}

				self::$config['tempFilePath'] = $filePath;
			} else {
				self::$config['tempFilePath'] = $IP . '/extensions/PageSync/Temp/';
				if ( !file_exists( self::$config['tempFilePath'] ) ) {
					mkdir(
						self::$config['tempFilePath']
					);
					chmod(
						self::$config['tempFilePath'],
						0777
					);
				}
			}
			global $wgScript;
			$url   = str_replace(
				'index.php',
				'',
				$wgScript
			);
			self::$config['uri'] = $url . 'extensions/PageSync/';

			return;
		}
		self::setDefaultConfig();
	}

	/**
	 * old behaviour. Set export inside PageSync folder
	 */
	private static function setDefaultConfig() {
		global $IP;
		self::$config['contentSlotsToBeSynced']                = 'all';
		self::$config['maintenance']['doNotRestoreThesePages'] = [];
		self::$config['maintenance']['restoreFrom']            = '';
		self::$config['filePath']                              = $IP . '/extensions/PageSync/files/';
		self::$config['exportPath']                            = self::$config['filePath'] . 'export/';

		if ( !file_exists( self::$config['filePath'] ) ) {
			mkdir(
				self::$config['filePath']
			);
			chmod(
				self::$config['filePath'],
				0777
			);
		}
		if ( !file_exists( self::$config['exportPath'] ) ) {
			mkdir(
				self::$config['exportPath']
			);
			chmod(
				self::$config['exportPath'],
				0777
			);
		}
	}

	public static function getFileIndexCustom( $path ){
		$infoFilesList = glob( $path . "*.info" );
	}

	/**
	 * Read the list of files that need to be synced
	 *
	 *
	 * @return array|false|mixed
	 */
	public static function getFileIndex() {
		if ( self::$config === false ) {
			self::setConfig();
		}
		if ( self::$config === false ) {
			return false;
		}

		$indexFile = self::$config['filePath'] . 'export.index';
		if ( ! file_exists( $indexFile ) ) {
			file_put_contents( $indexFile,
							   [] );

			return [];
		}

		return json_decode(
			file_get_contents( $indexFile ),
			true
		);
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
	 * @param mixed $arr
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

	public static function titleForDisplay( $ns, $title ) {
		//echo "\nGetting $title for $ns\n";
		$nsName = self::getNameSpaceNameFromID( $ns );
		if ( $ns !== 0 ) {
			return $nsName . ':' . self::removeNSFromTitle( $title );
		} else {
			return self::removeNSFromTitle( $title );
		}
	}

	/**
	 * Get all pages and their detailed info
	 *
	 * @return array|false all pages and their detailed info
	 */
	public static function getAllPageInfo( $customPath = false ) {
		if ( self::$config === false ) {
			self::setConfig();
		}
		if ( self::$config === false ) {
			return false;
		}
		$filesPath = self::$config['exportPath'];
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
			$fileAndPath = self::$config['exportPath'] . self::getFileSlotNameWiki(
					$fname,
					$slotName
				);
		} else {
			$fileAndPath = $path . self::getFileSlotNameWiki(
					$fname,
					$slotName
				);
		}
		//echo "\nGetting file : $fileAndPath\n";

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
		return self::$config['exportPath'] . $fname . '_slot_' . $slotName . '.wiki';
	}

	/**
	 * @param string $fname
	 *
	 * @return string
	 */
	private static function setInfoName( string $fname ) : string {
		return self::$config['exportPath'] . $fname . '.info';
	}

	/**
	 * @param string $fname
	 * @param string $path
	 *
	 * @return string
	 */
	private static function setZipInfoName( string $fname, $path  ) : string {
		return $path . $fname . '.info';
	}

	/**
	 * @param array $index
	 *
	 * @return false|int
	 */
	public static function saveFileIndex( array $index ) {
		$filesPath = self::$config['filePath'];
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
	 * @param User $user
	 * @param WikiPage $wikipage_object
	 * @param array $text
	 * @param string $summary
	 *
	 * @return array
	 * @throws MWContentSerializationException
	 * @throws MWException
	 */
	public static function editSlots(
		User $user,
		WikiPage $wikipage_object,
		array $text,
		string $summary
	) : array {
		$status              = true;
		$errors              = [];
		$title_object        = $wikipage_object->getTitle();
		$page_updater        = $wikipage_object->newPageUpdater( $user );
		$old_revision_record = $wikipage_object->getRevisionRecord();
		$slot_role_registry  = MediaWikiServices::getInstance()->getSlotRoleRegistry();

		foreach ( $text as $slot_name => $content ) {
			//echo "\nWorking with $slot_name";
			// Make sure the slot we are editing exists
			if ( ! $slot_role_registry->isDefinedRole( $slot_name ) ) {
				$status   = false;
				$errors[] = wfMessage(
					"wsslots-apierror-unknownslot",
					$slot_name
				); // TODO: Update message name
				unset( $text[$slot_name] );
				continue;
			}
			if ( $content === "" && $slot_name !== SlotRecord::MAIN ) {
				// Remove the slot if $text is empty and the slot name is not MAIN
				echo "\nSlot $slot_name is empty. Removing..";
				$page_updater->removeSlot( $slot_name );
			} else {
				// Set the content for the slot we want to edit
				if ( $old_revision_record !== null && $old_revision_record->hasSlot( $slot_name ) ) {
					$model_id = $old_revision_record->getSlot( $slot_name )->getContent()->getContentHandler()
													->getModelID();
				} else {
					$model_id = $slot_role_registry->getRoleHandler( $slot_name )->getDefaultModel( $title_object );
				}

				$slot_content = ContentHandler::makeContent(
					$content,
					$title_object,
					$model_id
				);
				$page_updater->setContent(
					$slot_name,
					$slot_content
				);
				if ( $slot_name !== SlotRecord::MAIN ) {
					$page_updater->addTag( 'wsslots-slot-edit' ); // TODO: Update message name
				}
			}
		}

		if ( $old_revision_record === null && ! isset( $text[SlotRecord::MAIN] ) ) {
			// The 'main' content slot MUST be set when creating a new page
			echo "\nWe have no older revision for this page and we do not have a main record.";
			echo " So creating an empty Main.";
			$main_content = ContentHandler::makeContent(
				"",
				$title_object
			);
			$page_updater->setContent(
				SlotRecord::MAIN,
				$main_content
			);
		}

		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$page_updater->saveRevision(
			$comment,
			EDIT_INTERNAL
		);

		if ( $status === true ) {
			return [
				"result"  => true,
				"changed" => $page_updater->isUnchanged()
			];
		} else {
			return [
				'result' => false,
				'errors' => $errors
			];
		}
	}

	/**
	 * @param User $user
	 * @param WikiPage $wikipage_object
	 * @param string $text
	 * @param string $slot_name
	 * @param string $summary
	 *
	 * @return array
	 * @throws MWContentSerializationException
	 * @throws MWException
	 */
	public static function editSlot(
		User $user,
		WikiPage $wikipage_object,
		string $text,
		string $slot_name,
		string $summary
	) : array {
		$title_object        = $wikipage_object->getTitle();
		$page_updater        = $wikipage_object->newPageUpdater( $user );
		$old_revision_record = $wikipage_object->getRevisionRecord();
		$slot_role_registry  = MediaWikiServices::getInstance()->getSlotRoleRegistry();

		// Make sure the slot we are editing exists
		if ( ! $slot_role_registry->isDefinedRole( $slot_name ) ) {
			return [
				wfMessage(
					"wsslots-apierror-unknownslot",
					$slot_name
				),
				"unknownslot"
			]; // TODO: Update message name
		}

		if ( $text === "" && $slot_name !== SlotRecord::MAIN ) {
			// Remove the slot if $text is empty and the slot name is not MAIN
			$page_updater->removeSlot( $slot_name );
		} else {
			// Set the content for the slot we want to edit
			if ( $old_revision_record !== null && $old_revision_record->hasSlot( $slot_name ) ) {
				$model_id = $old_revision_record->getSlot( $slot_name )->getContent()->getContentHandler()->getModelID(
				);
			} else {
				$model_id = $slot_role_registry->getRoleHandler( $slot_name )->getDefaultModel( $title_object );
			}

			$slot_content = ContentHandler::makeContent(
				$text,
				$title_object,
				$model_id
			);
			$page_updater->setContent(
				$slot_name,
				$slot_content
			);
		}

		if ( $old_revision_record === null ) {
			// The 'main' content slot MUST be set when creating a new page
			$main_content = ContentHandler::makeContent(
				"",
				$title_object
			);
			$page_updater->setContent(
				SlotRecord::MAIN,
				$main_content
			);
		}

		if ( $slot_name !== SlotRecord::MAIN ) {
			$page_updater->addTag( 'wsslots-slot-edit' ); // TODO: Update message name
		}

		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$page_updater->saveRevision(
			$comment,
			EDIT_INTERNAL
		);

		return [
			"result"  => true,
			"changed" => $page_updater->isUnchanged()
		];
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
				if ( isset( $infoContent['tags'] ) && ! empty( $infoContent['tags'] ) ) {
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
		foreach( $pages as $page ) {
			if ( isset( $page['tags'] ) ) {
				$temp = explode( ',', $page['tags'] );
				foreach( $temp as $single ) {
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
			return self::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}
		return self::makeMessage(
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
			return self::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}
		return self::makeMessage(
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

		$ns = self::getNSFromId( $id );

		// add or replace page
		$index[$fname] = $title;

		//wiki export folder
		$exportFolder = self::$config['exportPath'];

		//set info filename
		$infoFile = self::setInfoName( $fname );

		// save index file
		$result = self::addOrUpdateToFileIndex(
			$fname,
			$title
		);

		if ( $result === false ) {
			return self::makeMessage(
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
			if( !$nTags ) {
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
			return self::makeMessage(
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

		if ( ! empty( $storeResults ) ) {
			return self::makeMessage(
				false,
				wfMessage( 'wsps-error_content_file' )->text() . ': ' . implode(
					',',
					$storeResults
				)
			);
		}

		return self::makeMessage(
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
				$fileInfo['changes'] = $datetime->format( 'd-m-Y H:i:s' );;
				// save the info file
				$result = file_put_contents(
					$infoFile['info'],
					json_encode( $fileInfo )
				);
				if ( $result === false ) {
					return self::makeMessage(
						false,
						wfMessage( 'wsps-error_info_file' )->text()
					);
				} else {
					return self::makeMessage(
						true,
						"ok"
					);
				}
			}
		}
		return self::makeMessage(
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
			return self::makeMessage(
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
			return self::makeMessage(
				false,
				wfMessage( 'wsps-error_info_file' )->text()
			);
		} else {
			return self::makeMessage(
				true,
				"ok"
			);
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
	private static function setInfoContent(
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
	 * Helper function to create standardized response
	 *
	 * @param bool|string $type
	 * @param mixed $result
	 *
	 * @return array
	 */
	public static function makeMessage( $type, $result ) : array {
		$data           = [];
		$data['status'] = $type;
		$data['info']   = $result;

		return $data;
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
			return self::makeMessage(
				false,
				wfMessage( 'wsps-error_no_page_id' )->text()
			);
		}
		$title = self::getPageTitleForFileName( $id );
		$fileTitle = $title;
		if ( $title === false || $title === null || $fileTitle === false || $fileTitle === null ) {
			return self::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}
		$fname = self::cleanFileName( $fileTitle );
		$ns = self::getNSFromId( $id );
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
		$slotContent = self::getSlotsContentForPage( $id );
		if ( $slotContent === false || empty( $slotContent ) ) {
			return self::makeMessage(
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
			return self::makeMessage(
				false,
				$result['info']
			);
		}
		$ret          = [];
		$ret['fname'] = $fname;
		$ret['title'] = $title;

		return self::makeMessage(
			true,
			$ret
		);
	}

	/**
	 * @param int $ns
	 *
	 * @return string
	 */
	 public static function getNameSpaceNameFromID( int $ns ): string {
		 return MediaWiki\MediaWikiServices::getInstance()->getContentLanguage()->getFormattedNsText( $ns );
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
			return self::makeMessage(
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
				return self::makeMessage(
					false,
					wfMessage( 'wsps-error_index_file' )->text()
				);
			}
			$slot_result = self::getSlotNamesForPageAndRevision( $id );
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
			//set info filename
			$infoFile = self::setInfoName( $fname );
			if ( file_exists( $infoFile ) ) {
				$contents = json_decode(
					file_get_contents( $infoFile ),
					true
				);
				if ( isset( $contents['isFile'] ) && $contents['isFile'] === true ) {
					if ( file_exists( self::$config['exportPath'] . $contents['filestoredname'] ) ) {
						unlink( self::$config['exportPath'] . $contents['filestoredname'] );
					}
				}
				unlink( $infoFile );
			}
		}

		return self::makeMessage(
			true,
			''
		);
	}


	/**
	 * @param WikiPage $article
	 * @param User $user
	 * @param $reason
	 * @param $error
	 *
	 *
	 */
	public static function onArticleDelete( WikiPage &$article, User &$user, &$reason, &$error ) {
		$id       = $article->getId();
		$title    = self::getPageTitleForFileName( $id );
		$fName    = self::cleanFileName( $title );
		$username = $user->getName();
		$index    = self::getFileIndex();
		if ( isset( $index[$fName] ) && $index[$fName] === $title ) {
			$result = self::removeFileForExport(
				$id,
				$username
			);
		}
		return true;
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
		MediaWiki\User\UserIdentity $user,
		string $summary,
		int $flags,
		MediaWiki\Revision\RevisionRecord $revisionRecord,
		MediaWiki\Storage\EditResult $editResult
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
	 * @param int $id
	 *
	 * @return false|int Either Title as string or false
	 */
	public static function getPageNS( int $id ) {
		$article = WikiPage::newFromId( $id );
		if ( $article instanceof WikiPage ) {
			return $article->getTitle()->getNamespace();
		} else {
			return false;
		}
	}

	/**
	 * @param string $title
	 *
	 * @return int
	 */
	public static function getNSFromTitleString( string $title ): int {
		$res = explode( '_', $title );
		return $res[0];
	}

	/**
	 * @param string $title
	 *
	 * @return string
	 */
	public static function removeNSFromTitle( string $title ): string {
		$res = explode( '_', $title );
		unset( $res[0] );
		return implode( '_', $res );
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
	 * @param int $id
	 *
	 * @return false|string Either Title as string or false
	 */
	public static function getNSFromId( int $id ) {
		$article = WikiPage::newFromId( $id );
		if ( $article instanceof WikiPage ) {
			return $article->getTitle()->getNamespace();
		} else {
			return false;
		}
	}

	/**
	 * @param int $id
	 *
	 * @return array|false
	 */
	private static function getSlotNamesForPageAndRevision( int $id ) {
		$page = WikiPage::newFromId( $id );
		if ( $page === false || $page === null ) {
			return false;
		}
		$latest_revision = $page->getRevisionRecord();
		if ( $latest_revision === null ) {
			return false;
		}

		return [
			"slots"           => $latest_revision->getSlotRoles(),
			"latest_revision" => $latest_revision
		];
	}

	/**
	 * @param int $id
	 *
	 * @return array|false
	 * @throws MWException
	 * @throws MWUnknownContentModelException
	 */
	public static function getSlotsContentForPage( int $id ) {
		$slot_result = self::getSlotNamesForPageAndRevision( $id );
		if ( $slot_result === false ) {
			return false;
		}
		$slot_roles      = $slot_result['slots'];
		$latest_revision = $slot_result['latest_revision'];

		$slot_contents = [];

		foreach ( $slot_roles as $slot_role ) {
			if ( strtolower( self::$config['contentSlotsToBeSynced'] ) !== 'all' ) {
				if ( ! array_key_exists(
					$slot_role,
					self::$config['contentSlotsToBeSynced']
				) ) {
					continue;
				}
			}
			if ( ! $latest_revision->hasSlot( $slot_role ) ) {
				continue;
			}

			$content_object = $latest_revision->getContent( $slot_role );

			if ( $content_object === null ) {
				continue;
			}
			$content_handler = MediaWikiServices::getInstance()->getContentHandlerFactory()->getContentHandler(
				$content_object->getModel()
			);

			$contentOfSLot = $content_handler->serializeContent( $content_object );

			if ( empty( $contentOfSLot ) && $slot_role !== 'main' ) {
				continue;
			}

			$slot_contents[$slot_role] = $contentOfSLot;
		}

		return $slot_contents;
	}

	/**
	 * Convert .info content to version 0.9.9.9+
	 *
	 * @param string $content
	 *
	 * @return false|string
	 */
	private static function convertContentTov0999( string $content ) {
		$json = json_decode(
			$content,
			true
		);
		if ( ! isset( $json['slots'] ) ) {
			$json['slots'] = [ 'main' ];
		}
		if ( isset( $json['isFile'] ) && $json['isFile'] !== false ) {
			$json['isFile']['isFile'] = true;
			$json['isFile']['url']    = $json['fileurl'];
			$json['isFile']['name']   = $json['fileoriginalname'];
			$json['isFile']['owner']  = $json['fileowner'];
		}
		if ( ! isset( $json['isFile'] ) ) {
			$json['isFile'] = false;
		}
		if ( !isset( $json['description'] ) ) {
			$json['description'] = '';
		}
		if ( !isset( $json['tags'] ) ) {
			$json['tags'] = '';
		}

		if ( !isset( $json['ns'] ) ) {
			$json['ns'] = self::getNSFromId( $json['pageid'] );
		}

		return json_encode(
			self::setInfoContent(
				$json['filename'],
				$json['pagetitle'],
				$json['ns'],
				$json['username'],
				$json['pageid'],
				$json['slots'],
				$json['isFile'],
				$json['changed'],
				$json['description'],
				$json['tags']
			)
		);
	}

	/**
	 * @param string $newFName
	 * @param string $oldFName
	 * @param array $infoContent
	 * @param array $slots
	 *
	 * @return void
	 */
	public static function rewriteFileToVersion2(
		string $newFName,
		string $oldFName,
		array $infoContent,
		array $slots
	) {
		if ( self::$config !== false ) {
			self::setConfig();
		}
		$path = self::$config['exportPath'];

		echo "\nCreating new file : " . $path . $newFName . ".info'\n";

		file_put_contents(
			$path . $newFName . '.info',
			json_encode(
				$infoContent,
				JSON_PRETTY_PRINT
			)
		);
		foreach ( $slots as $slot ) {
			$oldFile = $path . $oldFName . '_slot_' . $slot . '.wiki';
			$newFile = $path . $newFName . '_slot_' . $slot . '.wiki';
			if ( $oldFile === $newFile ) {
				echo "\nSkipping renaming slots, filenames are equal " . $newFName . "\n";
			} else {
				echo "\nRenaming " . $oldFName . '_slot_' . $slot . '.wiki' . "\nTo: " . $newFName . '_slot_' . $slot . '.wiki' . "\n";
				rename(
					$oldFile,
					$newFile
				);
			}
			//echo "\nDeleting " . $oldFile . "\n";
			//unlink( $oldFile );
		}
		echo "\nDeleting " . $path . $oldFName . ".info\n";
		unlink( $path . $oldFName . '.info' );
	}

	/**
	 * @param string  $id
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


	/**
	 * @return array
	 * @throws MWException
	 */
	public static function convertToVersion2() : array {
		if ( self::$config !== false ) {
			self::setConfig();
		}

		$path      = self::$config['exportPath'];
		$infoFilesList = glob( $path . "*.info" );
		$cnt           = 0;
		$skipped = 0;
		$index = [];
		foreach ( $infoFilesList as $infoFile ) {
			$content = json_decode( file_get_contents( $infoFile ), true );

			// Get titleobject
			$tObject = Title::newFromText( $content['pagetitle'] );
			$ns = $tObject->getNamespace();
			$oldFilename = $content['pagetitle'];
			$content['pagetitle'] = self::getPageTitleForFileNameFromText( $oldFilename );
			if ( $content['pagetitle'] === false ) {
				echo "\nCould not find page " . $oldFilename . " in the Wiki. Skipping..\n";
				$skipped++;
				continue;
			}
			$content['ns'] = $ns;

			// Create EN title
			echo "\nOld File Name = " . $oldFilename . "\n";
			$newFileName = self::cleanFileName( self::getPageTitleForFileNameFromText( $oldFilename ) );
			echo "\nNew File Name = " . $newFileName . "\n";

			if ( $oldFilename === $newFileName ) {
				echo "\nSkipping renaming slots, file names are equal " . $oldFilename . "\n";
				$skipped++;
				$fName = $content['filename'];
				$fTitle = $content['pagetitle'];
				$index[$fName] = $fTitle;
				$cnt++;
				continue;
			}

			$oldFName = $content['filename'];

			// Set new filename
			$content['filename'] = self::cleanFileName( $content['pagetitle'] );

			$slots = explode( ',', $content['slots'] );

			self::rewriteFileToVersion2( $content['filename'], $oldFName, $content, $slots );

			$fName = $content['filename'];
			$fTitle = $content['pagetitle'];
			$index[$fName] = $fTitle;
			$cnt++;
		}
		self::saveFileIndex( $index );
		return [
			'total' => $cnt,
			'skipped' => $skipped
		];
	}

	/**
	 * Full function to convert synced file to version 0.9.9.9+
	 *
	 * @return array
	 */
	public static function convertFilesTov0999() : array {
		if ( self::$config !== false ) {
			self::setConfig();
		}
		$path      = self::$config['exportPath'];
		$indexList = self::getFileIndex();
		$cnt       = 0;
		$converted = 0;
		foreach ( $indexList as $file => $title ) {
			$convertedFile = false;
			$wikiFileList  = glob( $path . $file . "*.wiki" );
			if ( file_exists( $path . $file . '.wiki' ) && count( $wikiFileList ) <= 1 ) {
				// we have an old version here
				$newFileName = $path . $file . '_slot_main' . '.wiki';
				file_put_contents(
					$newFileName,
					file_get_contents( $path . $file . '.wiki' )
				);
				unlink( $path . $file . '.wiki' );
				$converted++;
				$convertedFile = true;
			} elseif ( file_exists( $path . $file . '.wiki' ) && count( $wikiFileList ) > 1 ) {
				// we have some new files, but it looks the main slot is still the old version.
				$newFileName = $path . $file . '_slot_main' . '.wiki';
				if ( in_array(
					$file . '_slot_main' . '.wiki',
					$wikiFileList
				) ) {
					// we should be fine, new main slot exists
					unlink( $path . $file . '.wiki' );
				} else {
					file_put_contents(
						$newFileName,
						file_get_contents( $path . $file . '.wiki' )
					);
					unlink( $path . $file . '.wiki' );
					$converted++;
					$convertedFile = true;
				}
			}
			if ( $convertedFile === true ) {
				$infoFile = $path . $file . ".info";
				if ( file_exists( $infoFile ) ) {
					file_put_contents(
						$infoFile,
						self::convertContentTov0999( file_get_contents( $infoFile ) )
					);
				}
			}
			$cnt++;
		}

		return [
			'total'     => $cnt,
			'converted' => $converted
		];
	}

	/**
	 * @param bool $returnCnt
	 * @param bool $returnFileNames
	 *
	 * @return array|bool|int
	 */
	public static function checkFileConsistency( bool $returnCnt = false, bool $returnFileNames = false ) {
		if ( self::$config === false ) {
			self::setConfig();
		}

		$flag          = true;
		$path          = self::$config['exportPath'];
		$infoFilesList = glob( $path . "*.info" );
		$cnt           = 0;
		$markedFiles   = [];
		if ( ! empty( $infoFilesList ) ) {
			foreach ( $infoFilesList as $infoFile ) {
				$fileContent = json_decode(
					file_get_contents( $infoFile ),
					true
				);
				if ( ! isset( $fileContent['slots'] ) ) {
					$flag          = false;
					$markedFiles[] = $fileContent['pagetitle'];
					$cnt++;
				}
			}
		}

		if ( $returnFileNames ) {
			return $markedFiles;
		}
		if ( $returnCnt ) {
			return $cnt;
		}

		return $flag;
	}

	/**
	 * @param bool $returnCnt
	 * @param bool $returnFileNames
	 *
	 * @return array|bool|int
	 */
	public static function checkFileConsistency2( bool $returnCnt = false, bool $returnFileNames = false ) {
		if ( self::$config === false ) {
			self::setConfig();
		}

		$flag          = true;
		$path          = self::$config['exportPath'];
		$infoFilesList = glob( $path . "*.info" );
		$cnt           = 0;
		$markedFiles   = [];
		if ( ! empty( $infoFilesList ) ) {
			foreach ( $infoFilesList as $infoFile ) {
				$fileContent = json_decode(
					file_get_contents( $infoFile ),
					true
				);
				if ( ! isset( $fileContent['ns'] ) ) {
					$flag          = false;
					$markedFiles[] = $fileContent['pagetitle'];
					$cnt++;
				}
			}
		}

		if ( $returnFileNames ) {
			return $markedFiles;
		}
		if ( $returnCnt ) {
			return $cnt;
		}

		return $flag;
	}

	/**
	 * @param SkinTemplate $sktemplate
	 * @param array $links
	 *
	 * @return bool|void
	 */
	public static function nav( SkinTemplate &$sktemplate, array &$links ) {
		global $wgUser, $wgScript;
		$title = null;
		$url   = str_replace(
			'index.php',
			'',
			$wgScript
		);
		// If not sysop.. return
		if ( empty( array_intersect(
			self::$config['allowedGroups'],
			MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $wgUser )
		) ) ) {
			return;
		}

		if ( method_exists(
			$sktemplate,
			'getTitle'
		) ) {
			$title = $sktemplate->getTitle();
		}
		if ( $title === null ) {
			return;
		}

		$articleId = $title->getArticleID();

		if ( self::checkFileConsistency() === false || self::checkFileConsistency2() === false ) {
			global $wgArticlePath;
			$url                    = str_replace(
				'$1',
				'Special:PageSync',
				$wgArticlePath
			);
			$class                  = "wsps-notice";
			$links['views']['wsps'] = [
				"class"     => $class,
				"text"      => "",
				"href"      => $url,
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'PageSync cannot be currently used. Please click this button to visit the Special page',
				'rel'       => 'PageSync'
			];

			return true;
		}

		$fIndex = self::getFileIndex();
		$tags = [];
		if ( $articleId !== 0 ) {
			$class = "wsps-toggle";
			$classt = "wspst-toggle";
			if ( false !== $fIndex && in_array(
					self::getPageTitleForFileName( $articleId ),
					$fIndex
				) ) {
				$tags = self::getTagsFromPage( $articleId );
				$class .= ' wsps-active';
				if ( !empty( $tags ) ) {
					$classt .= ' wspst-active';
				}
			} else {
				$classt .= ' wspst-hide';
			}
			$links['views']['wsps'] = [
				"class"     => $class,
				"text"      => "",
				"href"      => '#',
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'PageSync',
				'rel'       => 'PageSync'
			];
			$links['views']['wspst'] = [
				"class"     => $classt,
				"text"      => "",
				"href"      => '#',
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'PageSync Tags',
				'rel'       => 'PageSync Tags'
			];
		} else {
			$class                  = "wsps-error";
			$links['views']['wsps'] = [
				"class"     => $class,
				"text"      => "",
				"href"      => '#',
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'PageSync - Not syncable',
				'rel'       => 'PageSync'
			];
		}

		return true;
	}

	/**
	 * Converts an array of values in form [0] => "name=value" into a real
	 * associative array in form [name] => value. If no = is provided,
	 * true is assumed like this: [name] => true
	 *
	 * @param array string $options
	 *
	 * @return array $results
	 */
	public static function extractOptions( array $options ) : array {
		$results = [];
		foreach ( $options as $option ) {
			$pair = explode(
				'=',
				$option,
				2
			);
			if ( count( $pair ) === 2 ) {
				$name           = trim( $pair[0] );
				$value          = trim( $pair[1] );
				$results[$name] = $value;
			}
			if ( count( $pair ) === 1 ) {
				$name           = trim( $pair[0] );
				$results[$name] = true;
			}
		}

		return $results;
	}

	/**
	 * Implements AdminLinks hook from Extension:Admin_Links.
	 *
	 * @param ALTree &$adminLinksTree
	 *
	 * @return bool
	 */
	public static function addToAdminLinks( ALTree &$adminLinksTree ) : bool {
		global $wgServer;
		$wsSection = $adminLinksTree->getSection( 'WikiBase Solutions' );
		if ( $wsSection === null ) {
			$section = new ALSection( 'WikiBase Solutions' );
			$adminLinksTree->addSection(
				$section,
				wfMessage( 'adminlinks_general' )->text()
			);
			$wsSection     = $adminLinksTree->getSection( 'WikiBase Solutions' );
			$extensionsRow = new ALRow( 'extensions' );
			$wsSection->addRow( $extensionsRow );
		}

		$extensionsRow = $wsSection->getRow( 'extensions' );

		if ( $extensionsRow === null ) {
			$extensionsRow = new ALRow( 'extensions' );
			$wsSection->addRow( $extensionsRow );
		}
		$extensionsRow->addItem(
			ALItem::newFromExternalLink(
				$wgServer . '/index.php/Special:WSps',
				'PageSync'
			)
		);

		return true;
	}
}
