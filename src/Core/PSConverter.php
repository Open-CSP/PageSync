<?php
/**
 * Created by  : Wikibase Solutions
 * Project     : PageSync
 * Filename    : PSConverter.php
 * Description :
 * Date        : 4-10-2022
 * Time        : 14:24
 */

namespace PageSync\Core;

use MWException;
use Title;

class PSConverter {

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
		if ( !isset( $json['slots'] ) ) {
			$json['slots'] = [ 'main' ];
		}
		if ( isset( $json['isFile'] ) && $json['isFile'] !== false ) {
			$json['isFile']['isFile'] = true;
			$json['isFile']['url']    = $json['fileurl'];
			$json['isFile']['name']   = $json['fileoriginalname'];
			$json['isFile']['owner']  = $json['fileowner'];
		}
		if ( !isset( $json['isFile'] ) ) {
			$json['isFile'] = false;
		}
		if ( !isset( $json['description'] ) ) {
			$json['description'] = '';
		}
		if ( !isset( $json['tags'] ) ) {
			$json['tags'] = '';
		}

		if ( !isset( $json['ns'] ) ) {
			$json['ns'] = PSNameSpaceUtils::getNSFromId( $json['pageid'] );
		}

		return json_encode(
			PSCore::setInfoContent(
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
		if ( PSConfig::$config !== false ) {
			PSCore::setConfig();
		}
		$path = PSConfig::$config['exportPath'];

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
			// echo "\nDeleting " . $oldFile . "\n";
			//unlink( $oldFile );
		}
		echo "\nDeleting " . $path . $oldFName . ".info\n";
		unlink( $path . $oldFName . '.info' );
	}

	/**
	 * @return array
	 * @throws MWException
	 */
	public static function convertToVersion2() : array {
		if ( PSConfig::$config !== false ) {
			PSCore::setConfig();
		}

		$path      = PSConfig::$config['exportPath'];
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
			$content['pagetitle'] = PSCore::getPageTitleForFileNameFromText( $oldFilename );
			if ( $content['pagetitle'] === false ) {
				echo "\nCould not find page " . $oldFilename . " in the Wiki. Skipping..\n";
				$skipped++;
				continue;
			}
			$content['ns'] = $ns;

			// Create EN title
			echo "\nOld File Name = " . $oldFilename . "\n";
			$newFileName = PSCore::cleanFileName( PSCore::getPageTitleForFileNameFromText( $oldFilename ) );
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
			$content['filename'] = PSCore::cleanFileName( $content['pagetitle'] );

			$slots = explode( ',', $content['slots'] );

			self::rewriteFileToVersion2( $content['filename'], $oldFName, $content, $slots );

			$fName = $content['filename'];
			$fTitle = $content['pagetitle'];
			$index[$fName] = $fTitle;
			$cnt++;
		}
		PSCore::saveFileIndex( $index );
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
		if ( PSConfig::$config !== false ) {
			PSCore::setConfig();
		}
		$path      = PSConfig::$config['exportPath'];
		$indexList = PSCore::getFileIndex();
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
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}

		$flag          = true;
		$path          = PSConfig::$config['exportPath'];
		$infoFilesList = glob( $path . "*.info" );
		$cnt           = 0;
		$markedFiles   = [];
		if ( !empty( $infoFilesList ) ) {
			foreach ( $infoFilesList as $infoFile ) {
				$fileContent = json_decode(
					file_get_contents( $infoFile ),
					true
				);
				if ( !isset( $fileContent['slots'] ) ) {
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
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}

		$flag          = true;
		$path          = PSConfig::$config['exportPath'];
		$infoFilesList = glob( $path . "*.info" );
		$cnt           = 0;
		$markedFiles   = [];
		if ( !empty( $infoFilesList ) ) {
			foreach ( $infoFilesList as $infoFile ) {
				$fileContent = json_decode(
					file_get_contents( $infoFile ),
					true
				);
				if ( !isset( $fileContent['ns'] ) ) {
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
}
