<?php
/**
 * Hooks for WSps extension
 *
 * @file
 * @ingroup Extensions
 */
//error_reporting( -1 );
//ini_set( 'display_errors', 1 );


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
		$parser->setFunctionHook( 'wsps', 'WSpsHooks::wsps' );
		$wgOut->getOutput()->addModules( 'ext.WSPageSync.scripts' );
		self::setConfig();
	}

	/**
	 * Read config and set appropriately
	 */
	public static function setConfig() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if( $config->has( "WSPageSync" ) ) {
			$wgWSPageSync = $config->get( "WSPageSync" );

			if( !isset( $wgWSPageSync['fileNameSpaces'] ) && !is_array( $wgWSPageSync['fileNameSpaces'] ) ) {
				self::$config['fileNameSpaces'] = [	6, -2 ];
			}

			if( isset( $wgWSPageSync['filePath'] ) && !empty( $wgWSPageSync['filePath'] ) ) {
				$filePath = rtrim( $wgWSPageSync['filePath'], '/' );
				$filePath .=  '/';
				$filesPath              = $filePath . 'files/';
				$exportPath             = $filePath . 'files/export/';
				$currentPermissionsPath = $filePath;
				$permissions            = substr( sprintf( '%o', fileperms( $currentPermissionsPath ) ), - 4 );
				if ( ! file_exists( $filesPath ) ) {
					mkdir( $filesPath, $permissions );
				}
				if ( ! file_exists( $exportPath ) ) {
					mkdir( $exportPath, $permissions );
				}
				self::$config['filesPath'] = $filesPath;
				self::$config['exportPath'] = $exportPath;
				return;
			}
		}
		self::setDefaultConfig();

	}

	/**
	 * old behaviour. Set export inside WSPageSync folder
	 */
	private static function setDefaultConfig(){
		global $IP;
		self::$config['filePath'] = $IP . '/extensions/WSPageSync/';
		self::$config['filesPath'] = self::$config['filePath'] . 'files/';
		self::$config['exportPath'] = self::$config['filesPath'] . 'export/';
		$currentPermissionsPath = self::$config['filePath'];
		$permissions            = substr( sprintf( '%o', fileperms( $currentPermissionsPath ) ), - 4 );
		if ( ! file_exists( self::$config['filesPath'] ) ) {
			mkdir( self::$config['filesPath'], $permissions );
		}
		if ( ! file_exists( self::$config['exportPath'] ) ) {
			mkdir( self::$config['exportPath'], $permissions );
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
		// Called in MW text like this: {{#something: }}


		// For named parameters like {{#something: foo=bar | apple=orange | banana }}
		// See: https://www.mediawiki.org/wiki/Manual:Parser_functions#Named_parameters
		$options = WSgetContentHooks::extractOptions( array_slice( func_get_args(), 1 ) );
		global $wgOut;
		if ( isset( $options['id'] ) && $options['id'] != '' ) {
			$artikel = Article::newFromId( $options['id'] );
			if ( $artikel !== false || $artikel !== null ) {
				$content = $artikel->fetchContent();
				//$wgOut->addHTML($content);
				$getridoff = array( '{{', '}}' );
				$content   = str_replace( $getridoff, '', $content );
				//$content = self::db_real_escape($content);
				$details = explode( "|", $content );
				unset( $details[0] );
				$back = "";
				foreach ( $details as $d ) {
					$back .= $d . ';;';
				}
				$back = rtrim( $back, ';;' );

				return array( $back, 'noparse' => false );
			}
		}
	}

	/**
	 * Read the list of files that need to be synced
	 * @param false|array $modules Are we dealing with modules
	 *
	 *
	 * @return array|false|mixed
	 */
	public static function getFileIndex( $modules = false ) {
		global $IP;
		if( self::$config === false ) {
			echo wfMessage('wsps-api-error-no-config-body')->text();
			return false;
		}
		if ( $modules === false ) {
			$indexFile = self::$config['filesPath'] . 'export.index';
			if ( ! file_exists( $indexFile ) ) {
				file_put_contents( $indexFile, '' );
				$data = array();

				return $data;
			}
			$data = json_decode( file_get_contents( $indexFile ), true );

			return $data;
		}

		// If we are dealing with modules
		return self::setAndCreateModuleFolders( $modules );
	}

	/**
	 * @param array $modules
	 *
	 * @return array|false|mixed
	 */
	private static function setAndCreateModuleFolders( array $modules ){
		if ( isset( $modules['indexFile'] ) ) {
			$indexFile = $modules['indexFile'];
		} else {
			echo "no Module path";
			return false;
		}

		if ( isset( $modules['pagePath'] ) ) {
			$pPath = $modules['pagePath'];
		} else {
			echo "no Module Pages path";
			return false;
		}

		if ( isset( $modules['modulePath'] ) ) {
			$mPath = $modules['modulePath'];
		} else {
			echo "no Module path";
			return false;
		}

		if ( ! file_exists( $mPath ) ) {
			if ( ! mkdir( rtrim( $mPath, '/' ), 0777 ) ) {
				echo "error creating WSps folder";
			}
		}

		if ( ! file_exists( $pPath ) ) {
			if ( ! mkdir( rtrim( $pPath, '/' ), 0777 ) ) {
				echo "error creating folder";
			}
		}
		if ( ! file_exists( $indexFile ) ) {
			file_put_contents( $indexFile, '' );
			$data = array();

			return $data;
		}
		$data = json_decode( file_get_contents( $indexFile ), true );
		return $data;
	}

	/**
	 * @param $fname string original filename
	 *
	 * @return string cleaned filename, replacing not valid character with an _
	 */
	public static function cleanFileName( string $fname ): string {
		return preg_replace( '/[^a-z0-9]+/', '_', strtolower( $fname ) );
	}

	/**
	 * Get all pages and their detailed info
	 *
	 * @param $modules bool|array Are we dealing with modules
	 *
	 * @return array|false all pages and their detailed info
	 */
	public static function getAllPageInfo( $modules = false ) {
		global $IP;
		if( self::$config === false ) {
			echo wfMessage('wsps-api-error-no-config-body')->text();
			return false;
		}

		if ( $modules === false ) {
			$filesPath = self::$config['exportPath'];
			$fList     = WSpsHooks::getFileIndex();
			$data      = array();
			if( !empty( $fList ) ) {
				foreach ( $fList as $k => $v ) {
					$infoFile = $filesPath . $k . '.info';
					if ( file_exists( $infoFile ) ) {
						$data[] = json_decode( file_get_contents( $infoFile ), true );
					}
				}
			}

			return $data;
		}
		// If we are dealing with modules
		if ( isset( $modules['indexFile'] ) ) {
			$mPath = $modules['indexFile'];
		} else {
			echo "no Module index File";

			return false;
		}
		if ( isset( $modules['pagePath'] ) ) {
			$pPath = $modules['pagePath'];
		} else {
			echo "no page path defined";

			return false;
		}
		$fList = WSpsHooks::getFileIndex( $modules );
		$data  = array();
		foreach ( $fList as $k => $v ) {
			$infoFile = $pPath . $k . '.info';
			if ( file_exists( $infoFile ) ) {
				$data[] = json_decode( file_get_contents( $infoFile ), true );
			}
		}

		return $data;
	}

	/**
	 * Get the content of one specific file
	 *
	 * @param $fname string filename
	 * @param $module bool|array are we dealing with modules
	 *
	 * @return bool|false|string false when not found, otherwise the content of the file.
	 */
	public static function getFileContent( string $fname, $module = false ) {
		global $IP;
		if ( $module === false ) {
			$filesPath = self::$config['exportPath'];

			if ( file_exists( $filesPath . $fname . '.wiki' ) ) {
				return file_get_contents( $filesPath . $fname . '.wiki' );
			} else {
				return false;
			}
		}
		$filesPath = $module['pagePath'];

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
	private static function setWikiName( string $fname ) {
		return self::$config['exportPath'] . $fname . '.wiki';
	}

	/**
	 * @param string $fname
	 *
	 * @return string
	 */
	private static function setInfoName( string $fname ) {
		return self::$config['exportPath'] . $fname . '.info';
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
	 * @param $module bool|array Are we dealing wit Modules
	 *
	 * @return array result from the @WSpsHooks::makeMessage function
	 * @throws Exception
	 */
	public static function putFileIndex( string $fname, string $title, string $content, string $uname, int $id, $isFile, $module = false ): array {
		global $IP;

		if ( $module === false ) {
			// get current indexfile
			$index = WSpsHooks::getFileIndex();

			// add or replace page
			$index[ $fname ] = $title;

			$filesPath = self::$config['filesPath'];
			$indexFile = $filesPath . 'export.index';

			//wiki export folder
			$exportFolder = self::$config['exportPath'];

			//set wiki filename
			$wikiFile = self::setWikiName( $fname );

			//set info filename
			$infoFile = self::setInfoName( $fname );

			// save index file
			$result = file_put_contents( $indexFile, json_encode( $index ) );
			if ( $result === false ) {
				return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_index_file' )->text() );
			}
			//Set content for info file
			$datetime                 = new DateTime();
			$date                     = $datetime->format( 'd-m-Y H:i:s' );
			$infoContent              = array();
			$infoContent['filename']  = $fname;
			$infoContent['pagetitle'] = $title;
			$infoContent['username']  = $uname;
			$infoContent['changed']   = $date;
			$infoContent['pageid']    = $id;

			if ( $isFile !== false ) {
				$infoContent['isFile']           = true;
				$infoContent['fileurl']          = $isFile['url'];
				$infoContent['fileoriginalname'] = $isFile['name'];
				$infoContent['fileowner']        = $isFile['owner'];
				file_put_contents( $exportFolder . $isFile['name'], file_get_contents( $isFile['url'] ) );
			} else {
				$infoContent['isFile'] = false;
			}

			// save the info file
			$result = file_put_contents( $infoFile, json_encode( $infoContent ) );
			if ( $result === false ) {
				return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_info_file' )->text() );
			}

			// save the content file
			$result = file_put_contents( $wikiFile, $content );
			if ( $result === false ) {
				return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_content_file' )->text() );
			}

			return WSpsHooks::makeMessage( true, '' );
		}
		// get current indexfile
		$index = WSpsHooks::getFileIndex( $module );

		// add or replace page
		$index[ $fname ] = $title;

		$fPath     = $module['pagePath'];
		$indexFile = $module['indexFile'];

		//set wiki filename
		$wikiFile = $fPath . $fname . '.wiki';

		//set info filename
		$infoFile = $fPath . $fname . '.info';

		// save index file
		$result = file_put_contents( $indexFile, json_encode( $index ) );
		if ( $result === false ) {
			return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_index_file' )->text() );
		}
		//Set content for info file
		$datetime                 = new DateTime();
		$date                     = $datetime->format( 'd-m-Y H:i:s' );
		$infoContent              = array();
		$infoContent['filename']  = $fname;
		$infoContent['pagetitle'] = $title;
		$infoContent['username']  = $uname;
		$infoContent['changed']   = $date;
		$infoContent['pageid']    = $id;
		// save the info file
		$result = file_put_contents( $infoFile, json_encode( $infoContent ) );
		if ( $result === false ) {
			return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_info_file' )->text() );
		}

		// save the content file
		$result = file_put_contents( $wikiFile, $content );
		if ( $result === false ) {
			return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_content_file' )->text() );
		}

		return WSpsHooks::makeMessage( true, '' );

	}

	/**
	 * Helper function to create standardized response
	 * @param string $type
	 * @param mixed$result
	 *
	 * @return array
	 */
	public static function makeMessage( string $type, $result ): array {
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
	 * @throws Exception
	 */
	public static function addFileForExport( $id, string $uname, $module = false ): array {
		$isFile = false;
		if ( $id === null || $id === 0 ) {
			return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_no_page_id' )->text() );
		}
		$title = WSpsHooks::getPageTitle( $id );
		if ( $title === false || $title === null ) {
			return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_page_not_found' )->text() );
		}
		if ( WSpsHooks::isFile( $id ) !== false ) {
			// we are dealing with a file or image
			$f            = wfFindFile( WSpsHooks::isFile( $id ) );
			$canonicalURL = $f->getCanonicalURL();
			$baseName     = $f->getName();
			$fileOwner    = $f->getUser();
			$isFile       = array(
				'url'   => $canonicalURL,
				'name'  => $baseName,
				'owner' => $fileOwner
			);
		}
		$content = WSpsHooks::getPageContent( $id );
		if ( $content === false ) {
			return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_page_not_retrievable' )->text() );
		}
		$fname = WSpsHooks::cleanFileName( $title );

		$result = WSpsHooks::putFileIndex( $fname, $title, $content, $uname, $id, $isFile, $module );
		if ( $result['status'] !== true ) {
			return WSpsHooks::makeMessage( false, $result['info'] );
		}
		$ret          = array();
		$ret['fname'] = $fname;
		$ret['title'] = $title;

		return WSpsHooks::makeMessage( true, $ret );
	}

	/**
	 * @param string $title
	 * @param false|array $module
	 *
	 * @return false|mixed
	 */
	public static function isTitleInIndex( string $title, $module = false ) {
		if ( $module === false ) {
			$index = WSpsHooks::getFileIndex();
			if ( in_array( $title, $index ) ) {
				$fname     = WSpsHooks::cleanFileName( $title );
				$infoFile  = self::setInfoName( $fname );
				if ( file_exists( $infoFile ) ) {
					$info = json_decode( file_get_contents( $infoFile ), true );
					return $info['pageid'];
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
		$index = WSpsHooks::getFileIndex( $module );

		if ( $index === null ) {
			return false;
		}
		if ( in_array( $title, $index ) ) {
			$fname     = WSpsHooks::cleanFileName( $title );
			$filesPath = $module['pagePath'];
			$infoFile  = $filesPath . $fname . '.info';
			if ( file_exists( $infoFile ) ) {
				$info = json_decode( file_get_contents( $infoFile ), true );

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
	public static function removeFileForExport( int $id, string $uname, $module = false ): array {
		if ( $module === false ) {
			$title = WSpsHooks::getPageTitle( $id );
			if ( $title === false ) {
				return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_page_not_found' )->text() );
			}

			$fname = WSpsHooks::cleanFileName( $title );

			$index = WSpsHooks::getFileIndex();
			if ( isset( $index[ $fname ] ) && $index[ $fname ] === $title ) {
				unset( $index[ $fname ] );
				$indexFile = self::$config['filesPath'] . 'export.index';
				//set wiki filename
				$wikiFile = self::setWikiName( $fname );
				//set info filename
				$infoFile = self::setInfoName( $fname );
				// save index file
				$result = file_put_contents( $indexFile, json_encode( $index ) );
				if ( $result === false ) {
					return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_index_file' )->text() );
				}
				if ( file_exists( $wikiFile ) ) {
					unlink( $wikiFile );
				}
				if ( file_exists( $infoFile ) ) {
					$contents = json_decode( file_get_contents( $infoFile ), true );
					if( isset( $contents['isFile'] ) && $contents['isFile'] === true ) {

						if( file_exists( self::$config['exportPath'] . $contents['fileoriginalname'] ) ) {
							unlink( self::$config['exportPath'] . $contents['fileoriginalname'] );
						}
					}
					unlink( $infoFile );
				}

				return WSpsHooks::makeMessage( true, '' );
			}
		}
		$title = WSpsHooks::getPageTitle( $id );
		if ( $title === false ) {
			return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_page_not_found' )->text() );
		}

		$fname = WSpsHooks::cleanFileName( $title );

		$index = WSpsHooks::getFileIndex( $module );
		if ( isset( $index[ $fname ] ) && $index[ $fname ] === $title ) {
			unset( $index[ $fname ] );
			$filesPath = $module['pPath'];
			$indexFile = $module['indexFile'];
			//set wiki filename
			$wikiFile = $filesPath . $fname . '.wiki';
			//set info filename
			$infoFile = $filesPath . $fname . '.info';
			// save index file
			$result = file_put_contents( $indexFile, json_encode( $index ) );
			if ( $result === false ) {
				return WSpsHooks::makeMessage( false, wfMessage( 'wsps-error_index_file' )->text() );
			}
			if ( file_exists( $wikiFile ) ) {
				unlink( $wikiFile );
			}
			if ( file_exists( $infoFile ) ) {
				unlink( $infoFile );
			}

			return WSpsHooks::makeMessage( true, '' );
		}
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
	static function pageSaved( WikiPage $article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId ) {
		$content  = $content->getTextForSearchIndex();
		$t_title  = $article->getTitle();
		$id       = $article->getId();
		$title    = $t_title->getFullText();
		$fname    = WSpsHooks::cleanFileName( $title );
		$username = $user->getName();
		$index    = WSpsHooks::getFileIndex();
		if ( isset( $index[ $fname ] ) && $index[ $fname ] === $title ) {
			//$fname, $title, $content, $uname, $id, $isFile, $module = false )
			$result = WSpsHooks::putFileIndex( $fname, $title, $content, $username, $id, false, false );
		}

		return true;
	}

	public static function isFile( $id ) {
		$title = Title::newFromId( $id );

		if ( ! is_null( $title ) && $title !== false ) {
			$ns = $title->getNamespace();
			if ( $ns === 6 || $ns === - 2 ) {
				return $title;
			}
		}

		return false;
	}

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

	public static function nav( SkinTemplate &$sktemplate, array &$links ) {

		global $wgUser, $wgScript;
		$url    = rtrim( $wgScript, 'index.php' );
		$assets = $url . 'extensions/WSPageSync/assets/images/';
		// If not sysop.. return
		if ( ! in_array( 'sysop', $wgUser->getEffectiveGroups() ) ) {
			return;
		}

		if ( method_exists( $sktemplate, 'getTitle' ) ) {
			$title = $sktemplate->getTitle();
		} else {
			$title = $sktemplate->mTitle;
		}

		$class  = "wsps-toggle";
		$fIndex = WSpsHooks::getFileIndex();
		if ( in_array( $title, $fIndex ) ) {
			$class .= ' wsps-active';
		}
		$links['views']['wsps'] = array(
			"class"     => $class,
			"text"      => "",
			"href"      => '#',
			"exists"    => '1',
			"primary"   => '1',
			'redundant' => '1',
			'rel'       => 'WSPageSync'
		);
		//print_r($links);

		// grab user persmissions

		//echo  $title;


		//echo "</pre>";

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
	public static function extractOptions( array $options ) {
		$results = array();
		foreach ( $options as $option ) {
			$pair = explode( '=', $option, 2 );
			if ( count( $pair ) === 2 ) {
				$name             = trim( $pair[0] );
				$value            = trim( $pair[1] );
				$results[ $name ] = $value;
			}
			if ( count( $pair ) === 1 ) {
				$name             = trim( $pair[0] );
				$results[ $name ] = true;
			}
		}
		//Now you've got an array that looks like this:
		//  [foo] => "bar"
		//	[apple] => "orange"
		//	[banana] => true
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
			$adminLinksTree->addSection( $section, wfMessage( 'adminlinks_general' )->text() );
			$wsSection     = $adminLinksTree->getSection( 'WikiBase Solutions' );
			$extensionsRow = new ALRow( 'extensions' );
			$wsSection->addRow( $extensionsRow );
		}

		$extensionsRow = $wsSection->getRow( 'extensions' );

		if ( is_null( $extensionsRow ) ) {
			$extensionsRow = new ALRow( 'extensions' );
			$wsSection->addRow( $extensionsRow );
		}
		$extensionsRow->addItem( ALItem::newFromExternalLink( $wgServer . '/index.php/Special:WSps', 'WS PageSync' ) );

		return true;
	}
}

