<?php
/**
 * Hooks for WSps extension
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

class WSpsHooks {

	public static $config = false;

	/**
	 * Hook from MW at first call to extension
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		global $wgOut;
		$parser->setFunctionHook(
			'wsps',
			'WSpsHooks::wsps'
		);
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
			file_get_contents( $IP . '/extensions/WSPageSync/extension.json' ),
			true
		);
		self::$config['version'] = $json['version'];
		if ( $config->has( "WSPageSync" ) ) {
			$wgWSPageSync = $config->get( "WSPageSync" );

			if ( ! isset( $wgWSPageSync['fileNameSpaces'] ) && ! is_array( $wgWSPageSync['fileNameSpaces'] ) ) {
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

			if ( isset( $wgWSPageSync['filePath'] ) && ! empty( $wgWSPageSync['filePath'] ) ) {
				$filePath               = rtrim(
					$wgWSPageSync['filePath'],
					'/'
				);
				$filePath               .= '/';
				$exportPath             = $filePath . 'export/';
				$currentPermissionsPath = $filePath;

				if ( ! file_exists( $filePath ) ) {
					mkdir(
						$filePath,
						0777
					);
					chmod(
						$filePath,
						0777
					);
				}

				if ( ! file_exists( $exportPath ) ) {
					mkdir(
						$exportPath,
						0777
					);
					chmod(
						$exportPath,
						0777
					);
				}
				self::$config['filePath']   = $filePath;
				self::$config['exportPath'] = $exportPath;

				return;
			}
		}
		self::setDefaultConfig();
	}

	/**
	 * old behaviour. Set export inside WSPageSync folder
	 */
	private static function setDefaultConfig() {
		global $IP;
		self::$config['contentSlotsToBeSynced']                = 'all';
		self::$config['maintenance']['doNotRestoreThesePages'] = [];
		self::$config['maintenance']['restoreFrom']            = '';
		self::$config['filePath']                              = $IP . '/extensions/WSPageSync/files/';
		self::$config['exportPath']                            = self::$config['filePath'] . 'export/';
		$currentPermissionsPath                                = self::$config['filePath'];
		//$permissions            = substr( sprintf( '%o', fileperms( $currentPermissionsPath ) ), - 4 );
		if ( ! file_exists( self::$config['filePath'] ) ) {
			mkdir(
				self::$config['filePath'],
				0777
			);
			chmod(
				self::$config['filePath'],
				0777
			);
		}
		if ( ! file_exists( self::$config['exportPath'] ) ) {
			mkdir(
				self::$config['exportPath'],
				0777
			);
			chmod(
				self::$config['exportPath'],
				0777
			);
		}
	}


	/**
	 * [not used as yet]
	 *
	 * @param Parser $parser [description]
	 *
	 * @return [type]         [description]
	 */
	public static function wsps( Parser &$parser ) {
		$options = WSgetContentHooks::extractOptions(
			array_slice(
				func_get_args(),
				1
			)
		);
		global $wgOut;
		if ( isset( $options['id'] ) && $options['id'] != '' ) {
			$artikel = Article::newFromId( $options['id'] );
			if ( $artikel !== false || $artikel !== null ) {
				$content   = $artikel->fetchContent();
				$getridoff = array(
					'{{',
					'}}'
				);
				$content   = str_replace(
					$getridoff,
					'',
					$content
				);
				$details   = explode(
					"|",
					$content
				);
				unset( $details[0] );
				$back = "";
				foreach ( $details as $d ) {
					$back .= $d . ';;';
				}
				$back = rtrim(
					$back,
					';;'
				);

				return array(
					$back,
					'noparse' => false
				);
			}
		}
	}

	/**
	 * Read the list of files that need to be synced
	 *
	 *
	 * @return array|false|mixed
	 */
	public static function getFileIndex() {
		if ( self::$config === false ) {
			//echo wfMessage( 'wsps-api-error-no-config-body' )->text();

			return false;
		}

		$indexFile = self::$config['filePath'] . 'export.index';
		if ( ! file_exists( $indexFile ) ) {
			file_put_contents( $indexFile,
							   array() );

			return array();
		}

		return json_decode(
			file_get_contents( $indexFile ),
			true
		);
	}


	/**
	 * @param $fname string original filename
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
	 * Get all pages and their detailed info
	 *
	 * @return array|false all pages and their detailed info
	 */
	public static function getAllPageInfo() {
		if ( self::$config === false ) {
			//echo wfMessage( 'wsps-api-error-no-config-body' )->text();

			return false;
		}
		$filesPath = self::$config['exportPath'];
		$fList     = WSpsHooks::getFileIndex();
		$data      = array();
		if ( false !== $fList && ! empty( $fList ) ) {
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

		return $data;
	}

	/**
	 * Get the content of one specific file
	 *
	 * @param $fname string filename
	 *
	 * @return bool|false|string false when not found, otherwise the content of the file.
	 */
	public static function getFileContent( string $fname ) {
		$filesPath = self::$config['exportPath'];

		if ( file_exists( $filesPath . $fname . '.wiki' ) ) {
			return file_get_contents( $filesPath . $fname . '.wiki' );
		} else {
			return false;
		}
	}

	/**
	 * Get a Page ID from a given Page Title
	 *
	 * @param $title string Titlename
	 *
	 * @return bool|integer Either false or the id of the Page
	 */
	public static function getPageIdFromTitle( string $title ) {
		$t          = Title::newFromText( $title );
		$wikiObject = WikiPage::factory( $t );
		if ( $wikiObject !== false || $wikiObject !== null ) {
			return $wikiObject->getId();
		} else {
			return false;
		}
	}

	/**
	 * @param string $fname
	 *
	 * @return string
	 */
	private static function setWikiName( string $fname, string $slotName ) {
		return self::$config['exportPath'] . $fname . '_slot_' . $slotName . '.wiki';
	}

	/**
	 * @param string $fname
	 *
	 * @return string
	 */
	private static function setInfoName( string $fname ) {
		return self::$config['exportPath'] . $fname . '.info';
	}

	private static function addOrUpdateToFileIndex( $fname, $title ) {
		// get current indexfile
		$index = WSpsHooks::getFileIndex();

		// add or replace page
		$index[$fname] = $title;

		$filesPath = self::$config['filePath'];
		$indexFile = $filesPath . 'export.index';

		// save index file
		return file_put_contents(
			$indexFile,
			json_encode( $index )
		);
	}

	private static function removeFileFromIndex( $fName, $title ) {
		$index = WSpsHooks::getFileIndex();
		if ( isset( $index[$fName] ) && $index[$fName] === $title ) {
			unset( $index[$fName] );
			$indexFile = self::$config['filePath'] . 'export.index';

			return file_put_contents(
				$indexFile,
				json_encode( $index )
			);
		}
	}


	/**
	 * Save the specific file details and content and add to Index
	 *
	 * @param $fname string filename
	 * @param $title string page title
	 * @param $content string page content
	 * @param $uname string username
	 * @param $isFile bool|array false if not, array with info if yes
	 * @param $id int PageID
	 *
	 * @return array result from the @WSpsHooks::makeMessage function
	 * @throws Exception
	 */
	public static function putFileIndex(
		string $fname,
		string $title,
		array $slotsContents,
		string $uname,
		int $id,
		$isFile
	) : array {
		// get current indexfile
		$index = WSpsHooks::getFileIndex();

		// add or replace page
		$index[$fname] = $title;

		$filesPath = self::$config['filePath'];
		$indexFile = $filesPath . 'export.index';

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
			return WSpsHooks::makeMessage(
				false,
				wfMessage( 'wsps-error_index_file' )->text()
			);
		}
		//Set content for info file

		$slots = array();
		foreach ( $slotsContents as $k => $v ) {
			$slots[] = $k;
		}

		$infoContent = self::setInfoContent(
			$fname,
			$title,
			$uname,
			$id,
			$slots,
			$isFile
		);

		if ( $isFile !== false ) {
			self::copyFile(
				$exportFolder,
				$isFile['name'],
				$isFile['url']
			);
		}

		// save the info file
		$result = file_put_contents(
			$infoFile,
			json_encode( $infoContent )
		);
		if ( $result === false ) {
			return WSpsHooks::makeMessage(
				false,
				wfMessage( 'wsps-error_info_file' )->text()
			);
		}
		//set wiki filenames
		$storeResults = array();
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
			return WSpsHooks::makeMessage(
				false,
				wfMessage( 'wsps-error_content_file' )->text() . ': ' . implode(
					',',
					$storeResults
				)
			);
		}

		return WSpsHooks::makeMessage(
			true,
			''
		);
	}

	private static function copyFile( $exportFolder, $fName, $fUrl ) {
		file_put_contents(
			$exportFolder . $fName,
			file_get_contents( $fUrl )
		);
	}

	private static function setInfoContent( $fname, $title, $uname, $id, $slots, $isFile, $changed = false ) {
		if ( $changed === false ) {
			$datetime = new DateTime();
			$date     = $datetime->format( 'd-m-Y H:i:s' );
		} else {
			$date = $changed;
		}
		$infoContent              = array();
		$infoContent['filename']  = $fname;
		$infoContent['pagetitle'] = $title;
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
		} else {
			$infoContent['isFile'] = false;
		}

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
		$data           = array();
		$data['status'] = $type;
		$data['info']   = $result;

		return $data;
	}

	/**
	 * @param int $id
	 * @param string $uname
	 * @param false|array $module
	 *
	 * @return array
	 */
	public static function addFileForExport( $id, string $uname, $module = false ) : array {
		$isFile = false;
		if ( $id === null || $id === 0 ) {
			return WSpsHooks::makeMessage(
				false,
				wfMessage( 'wsps-error_no_page_id' )->text()
			);
		}
		$title = WSpsHooks::getPageTitle( $id );
		if ( $title === false || $title === null ) {
			return WSpsHooks::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}
		if ( WSpsHooks::isFile( $id ) !== false ) {
			// we are dealing with a file or image
			$f            = wfFindFile( WSpsHooks::isFile( $id ) );
			$canonicalURL = $f->getLocalRefPath();
			if( $canonicalURL === false ){
				$canonicalURL = $f->getCanonicalUr();
			}
			$baseName     = $f->getName();
			$fileOwner    = $f->getUser();
			$isFile       = array(
				'url'   => $canonicalURL,
				'name'  => $baseName,
				'owner' => $fileOwner
			);
		}
		$slotContent = WSpsHooks::getSlotsContentForPage( $id );
		if ( $slotContent === false || empty( $slotContent ) ) {
			return WSpsHooks::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_retrievable' )->text()
			);
		}
		$fname = WSpsHooks::cleanFileName( $title );

		/*
		$content = WSpsHooks::getPageContent( $id );
		if ( $content === false ) {
			return WSpsHooks::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_retrievable' )->text()
			);
		}
		*/

		$result = WSpsHooks::putFileIndex(
			$fname,
			$title,
			$slotContent,
			$uname,
			$id,
			$isFile
		);
		if ( $result['status'] !== true ) {
			return WSpsHooks::makeMessage(
				false,
				$result['info']
			);
		}
		$ret          = array();
		$ret['fname'] = $fname;
		$ret['title'] = $title;

		return WSpsHooks::makeMessage(
			true,
			$ret
		);
	}

	/**
	 * @param string $title
	 * @param false|array $module
	 *
	 * @return false|mixed
	 */
	public static function isTitleInIndex( string $title ) {
		$index = WSpsHooks::getFileIndex();
		if ( in_array(
			$title,
			$index
		) ) {
			$fname    = WSpsHooks::cleanFileName( $title );
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
	 * @param false|array $module
	 *
	 * @return array
	 */
	public static function removeFileForExport( int $id, string $uname, $module = false ) : array {
		$title = WSpsHooks::getPageTitle( $id );
		if ( $title === false ) {
			return WSpsHooks::makeMessage(
				false,
				wfMessage( 'wsps-error_page_not_found' )->text()
			);
		}

		$fname = WSpsHooks::cleanFileName( $title );

		$index = WSpsHooks::getFileIndex();
		if ( isset( $index[$fname] ) && $index[$fname] === $title ) {
			unset( $index[$fname] );
			$indexFile = self::$config['filePath'] . 'export.index';

			// save index file
			$result = self::removeFileFromIndex(
				$fname,
				$title
			);
			if ( $result === false ) {
				return WSpsHooks::makeMessage(
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
					if ( file_exists( self::$config['exportPath'] . $contents['fileoriginalname'] ) ) {
						unlink( self::$config['exportPath'] . $contents['fileoriginalname'] );
					}
				}
				unlink( $infoFile );
			}
		}

		return WSpsHooks::makeMessage(
			true,
			''
		);
	}


	/**
	 * onPageSaveHook
	 *
	 * @param WikiPage $article
	 * @param $user
	 * @param $content
	 * @param $summary
	 * @param $isMinor
	 * @param $isWatch
	 * @param $section
	 * @param $flags
	 * @param $revision
	 * @param $status
	 * @param $baseRevId
	 *
	 * @return bool
	 * @throws Exception
	 */
	static function pageSaved(
		WikiPage $article,
		$user,
		$content,
		$summary,
		$isMinor,
		$isWatch,
		$section,
		$flags,
		$revision,
		$status,
		$baseRevId
	) {
		$content  = $content->getTextForSearchIndex();
		$t_title  = $article->getTitle();
		$id       = $article->getId();
		$title    = $t_title->getFullText();
		$fname    = WSpsHooks::cleanFileName( $title );
		$username = $user->getName();
		$index    = WSpsHooks::getFileIndex();
		if ( isset( $index[$fname] ) && $index[$fname] === $title ) {
			$result = WSpsHooks::putFileIndex(
				$fname,
				$title,
				$content,
				$username,
				$id,
				false
			);
		}

		return true;
	}

	/**
	 * Check to see if we are dealing with a file page
	 *
	 * @param $id
	 *
	 * @return mixed Either Title of false
	 */
	public static function isFile( $id ) {
		$title = Title::newFromId( $id );

		if ( ! is_null( $title ) && $title !== false ) {
			$ns = $title->getNamespace();
			if ( $ns === 6 || $ns === -2 ) {
				return $title;
			}
		}

		return false;
	}

	/**
	 * @param $id
	 *
	 * @return mixed Either Title as string or false
	 */
	public static function getPageTitle( $id ) {
		$id      = (int) ( $id );
		$artikel = WikiPage::newFromId( $id );
		if ( $artikel !== false || $artikel !== null ) {
			$t = $artikel->getTitle();

			return $t->getFullText();
		} else {
			return false;
		}
	}

	public static function getPageLastTimeStamp( $id ) {
		$id      = (int) ( $id );
		$artikel = WikiPage::newFromId( $id );
		if ( $artikel !== false || $artikel !== null ) {
			return $artikel->getTimestamp();
		} else {
			return false;
		}
	}

	private static function getSlotNamesForPageAndRevision( $id ) {
		$id   = (int) ( $id );
		$page = WikiPage::newFromId( $id );
		if ( $page === false || $page === null ) {
			return false;
		}
		$latest_revision = $page->getRevisionRecord();
		if ( $latest_revision === null ) {
			return false;
		}

		return array(
			"slots"           => $latest_revision->getSlotRoles(),
			"latest_revision" => $latest_revision
		);
	}

	/**
	 * @param int $id
	 *
	 * @return array|false|null
	 */
	public static function getSlotsContentForPage( $id ) {
		$slot_result = self::getSlotNamesForPageAndRevision( $id );
		if ( $slot_result === false ) {
			return false;
		}
		$slot_roles      = $slot_result['slots'];
		$latest_revision = $slot_result['latest_revision'];

		$slot_contents = [];

		foreach ( $slot_roles as $slot_role ) {
			echo $slot_role;
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

			if ( $content_object === null || ! ( $content_object instanceof TextContent ) ) {
				continue;
			}

			$slot_contents[$slot_role] = ContentHandler::getContentText( $content_object );
		}

		return $slot_contents;
		/*
		if ( $artikel !== false || $artikel !== null ) {
			$revision = $artikel->getRevisionRecord();
			if( null === $revision ) return false;
			if( !$revision->hasSlot( $slotName ) ) return false;
			$content  = $revision->getContent( $slotName );
			return ContentHandler::getContentText( $content );
		} else {
			return false;
		}
		*/
	}

	/**
	 * @param $id
	 *
	 * @return false
	 */
	public static function getPageContent( $id ) {
		$id      = (int) ( $id );
		$artikel = WikiPage::newFromId( $id );
		if ( $artikel !== false || $artikel !== null ) {
			$revision = $artikel->getRevision();
			$content  = $revision->getContent( Revision::RAW );
			$text     = ContentHandler::getContentText( $content );

			return $text;
		} else {
			return false;
		}
	}

	public static function deleteBackupFile( $backupFile ){
		if ( self::$config !== false ) {
			self::setConfig();
		}
		$path          = self::$config['exportPath'];
		if( file_exists( $path . $backupFile ) ) {
			unlink( $path . $backupFile );
			return true;
		}
		return false;
	}

	private static function removeRecursively( $dir, $finalDir ) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
						self::removeRecursively($dir. DIRECTORY_SEPARATOR .$object, $finalDir);
					else {
						if( strpos( basename( $object ), 'backup_' ) === false ) {
							unlink( $dir . DIRECTORY_SEPARATOR . $object );
						}
					}
				}
			}
			if( $dir !== $finalDir ) {
				rmdir( $dir );
			}
		}
	}

	public static function restoreBackupFile( $backupFile ){
		if ( self::$config !== false ) {
			self::setConfig();
		}
		$path          = self::$config['exportPath'];
		$indexPath     = self::$config['filePath'];
		$zip           = new ZipArchive();
		if( $zip->open( $path . $path ) === true ) {
			self::removeRecursively( $indexPath, $indexPath );
			self::setConfig(); // re-initiate deleted folder
			$zip->extractTo( $indexPath );
			$zip->close();
		}
	}

	public static function createZipFileBackup() {
		if ( self::$config !== false ) {
			self::setConfig();
		}
		$path          = self::$config['exportPath'];
		$indexPath     = self::$config['filePath'];
		$version       = str_replace(
			'.',
			'-',
			( self::$config['version'] )
		);
		$allFileinfo = self::getAllPageInfo();
		$addUploadedFile = array();
		foreach( $allFileinfo as $fileToCheck ) {
			if( isset( $fileToCheck['isFile']) && $fileToCheck['isFile'] === true ) {
				$addUploadedFile[] = $path . $fileToCheck['fileoriginalname'];
			}
		}
		$infoFilesList = glob( $path . "*.info" );
		$wikiFilesList = glob( $path . "*.wiki" );
		$indexFile     = $indexPath . 'export.index';
		$datetime      = new DateTime();
		$date          = $datetime->format( 'd-m-Y-H-i-s' );
		$zip           = new ZipArchive();
		if ( $zip->open(
				$path . 'backup_' . $date . '_' . $version . '.zip',
				zipArchive::CREATE
			) !== true ) {
			die( "cannot create " . $path . 'backup_' . $date );
		}
		$zip->addFile(
			$indexFile,
			basename( $indexFile )
		);
		$zip->addemptyDir( 'export' );
		foreach ( $infoFilesList as $infoFile ) {
			$zip->addFile(
				$infoFile,
				'export/' . basename( $infoFile )
			);
		}
		foreach ( $wikiFilesList as $wikiFile ) {
			$zip->addFile(
				$wikiFile,
				'export/' . basename( $wikiFile )
			);
		}
		foreach ( $addUploadedFile as $sepFile ) {
			$zip->addFile(
				$sepFile,
				'export/' . basename( $sepFile )
			);
		}
		$zip->close();
	}

	private static function convertContentTov0999( $content ) {
		$json = json_decode(
			$content,
			true
		);
		if ( ! isset( $json['slots'] ) ) {
			$json['slots'] = array( 'main' );
		}
		//$infoContent['isFile']           = true;
		//			$infoContent['fileurl']          = $isFile['url'];
		//			$infoContent['fileoriginalname'] = $isFile['name'];
		//			$infoContent['fileowner']        = $isFile['owner'];
		if ( $json['isFile'] !== false ) {
			$json['isFile'] = true;
			$json['url']    = $json['fileurl'];
			$json['name']   = $json['fileoriginalname'];;
			$json['owner'] = $json['fileowner'];;
		}

		return json_encode(
			self::setInfoContent(
				$json['filename'],
				$json['pagetitle'],
				$json['username'],
				$json['pageid'],
				$json['slots'],
				$json['isFile'],
				$json['changed']
			)
		);
	}

	public static function convertFilesTov0999() {
		if ( self::$config !== false ) {
			self::setConfig();
		}
		$path      = self::$config['exportPath'];
		$indexList = self::getFileIndex();
		$cnt       = 0;
		$converted = 0;
		foreach ( $indexList as $file=>$title ) {
			$convertedFile = false;
			echo "<p>Working on $title</p>";
			$wikiFileList = glob( $path . $file . "*.wiki" );
			echo "<p>Checking File:" . $path . $file . '.wiki</p>';
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
			if( $convertedFile === true ) {
				$infoFile = $path . $file . ".info";
				echo "<p>Working on $infoFile</p>";
				if( file_exists( $infoFile ) ) {
					file_put_contents( $infoFile, self::convertContentTov0999( file_get_contents( $infoFile ) ) );
				}
			}
			$cnt++;
		}

		return array(
			'total'     => $cnt,
			'converted' => $converted
		);
	}

	public static function getBackupList() {
		$data = array();
		if ( self::$config !== false ) {
			self::setConfig();
		}
		$path       = self::$config['exportPath'];
		$backupList = glob( $path . "backup_*.zip" );
		if ( empty( $backupList ) ) {
			return $data;
		}
		$t = 0;
		foreach ( $backupList as $backup ) {
			$exploded            = explode(
				'_',
				basename( $backup )
			);
			$version             = str_replace(
				'.zip',
				'',
				str_replace(
					'-',
					'.',
					$exploded[2]
				)
			);
			$data[$t]['file']    = basename( $backup );
			$data[$t]['version'] = $version;
			$data[$t]['date']    = date(
				'd-m-Y H:i:s',
				filemtime( $backup )
			);
			$t++;
		}

		return $data;
	}

	public static function checkFileConsistency( $returnCnt = false, $returnFileNames =  false ) {
		if ( self::$config !== false ) {
			self::setConfig();
		}
		$flag          = true;
		$path          = self::$config['exportPath'];
		$infoFilesList = glob( $path . "*.info" );
		$cnt           = 0;
		$markedFiles   = array();
		if ( empty( $infoFilesList ) ) {
			$flag = true;
		} else {
			foreach ( $infoFilesList as $infoFile ) {
				$fileContent = json_decode(
					file_get_contents( $infoFile ),
					true
				);
				if ( ! isset( $fileContent['slots'] ) ) {
					$flag = false;
					$markedFiles[] = $fileContent['pagetitle'];
					$cnt++;
				}
			}
		}
		if( $returnFileNames ) {
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
		$url    = rtrim(
			$wgScript,
			'index.php'
		);
		$assets = $url . 'extensions/WSPageSync/assets/images/';
		// If not sysop.. return
		if ( ! in_array(
			'sysop',
			$wgUser->getEffectiveGroups()
		) ) {
			return;
		}

		if ( method_exists(
			$sktemplate,
			'getTitle'
		) ) {
			$title = $sktemplate->getTitle();
		} else {
			$title = $sktemplate->mTitle;
		}

		$articleId = $title->getArticleID();

		if ( self::checkFileConsistency() === false ) {
			global $wgArticlePath;
			$url                    = str_replace(
				'$1',
				'Special:WSPageSync',
				$wgArticlePath
			);
			$class                  = "wsps-notice";
			$links['views']['wsps'] = array(
				"class"     => $class,
				"text"      => "",
				"href"      => $url,
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'WSPageSync cannot be currently used. Please click this button to visit the Special page',
				'rel'       => 'WSPageSync'
			);

			return true;
		}

		if ( $articleId !== 0 ) {
			$class  = "wsps-toggle";
			$fIndex = WSpsHooks::getFileIndex();
			if ( false !== $fIndex && in_array(
					$title,
					$fIndex
				) ) {
				$class .= ' wsps-active';
			}
			$links['views']['wsps'] = array(
				"class"     => $class,
				"text"      => "",
				"href"      => '#',
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'WSPageSync',
				'rel'       => 'WSPageSync'
			);
		} else {
			$class                  = "wsps-error";
			$links['views']['wsps'] = array(
				"class"     => $class,
				"text"      => "",
				"href"      => '#',
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'WSPageSync - Not syncable',
				'rel'       => 'WSPageSync'
			);
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
		$results = array();
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
	public static function addToAdminLinks( ALTree &$adminLinksTree ) {
		global $wgServer;
		$wsSection = $adminLinksTree->getSection( 'WikiBase Solutions' );
		if ( is_null( $wsSection ) ) {
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

		if ( is_null( $extensionsRow ) ) {
			$extensionsRow = new ALRow( 'extensions' );
			$wsSection->addRow( $extensionsRow );
		}
		$extensionsRow->addItem(
			ALItem::newFromExternalLink(
				$wgServer . '/index.php/Special:WSps',
				'WS PageSync'
			)
		);

		return true;
	}
}

