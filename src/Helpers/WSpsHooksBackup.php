<?php

namespace PageSync\Helpers;

use DateTime;
use PageSync\Core\PSConfig;
use PageSync\Core\PSCore;
use WSpsHooks;

use ZipArchive;

use function count;
use function wfMessage;

/**
 * Created by  : Wikibase Solution
 * Project     : csp
 * Filename    : WSpsHooksBackup.class.php
 * Description :
 * Date        : 15-10-2021
 * Time        : 21:00
 */
class WSpsHooksBackup {

	/**
	 * Delete a backup file
	 *
	 * @param string $backupFile
	 *
	 * @return bool
	 */
	public function deleteBackupFile( string $backupFile ) : bool {
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}
		$path = PSConfig::$config['exportPath'];
		if ( file_exists( $path . $backupFile ) ) {
			unlink( $path . $backupFile );

			return true;
		}

		return false;
	}

	/**
	 * Remove files recursively
	 *
	 * @param string $dir
	 * @param string $finalDir
	 */
	public function removeRecursively( string $dir, string $finalDir ) {
		if ( is_dir( $dir ) ) {
			$objects = scandir( $dir );
			foreach ( $objects as $object ) {
				if ( $object != "." && $object != ".." ) {
					if ( is_dir( $dir . DIRECTORY_SEPARATOR . $object ) && ! is_link( $dir . "/" . $object ) ) {
						$this->removeRecursively(
							$dir . DIRECTORY_SEPARATOR . $object,
							$finalDir
						);
					} else {
						if ( strpos(
								 basename( $object ),
								 'backup_'
							 ) === false ) {
							unlink( $dir . DIRECTORY_SEPARATOR . $object );
						}
					}
				}
			}
			if ( $dir !== $finalDir ) {
				if ( $this->isDirEmpty( $dir ) ) {
					rmdir( $dir );
				}
			}
		}
	}

	/**
	 * @param string $dir
	 *
	 * @return bool
	 */
	private function isDirEmpty( string $dir ) : bool {
		$handle = opendir( $dir );
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( $entry != "." && $entry != ".." ) {
				closedir( $handle );

				return false;
			}
		}
		closedir( $handle );

		return true;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	private function getVersionFromBackupName( string $name ): bool {
		$tmp = explode(
			'_',
			$name
		);
		if ( count( $tmp ) < 3 ) {
			return false;
		}
		$fVersion = $tmp[2];
		if ( $fVersion[0] === '1' ) {
			return false;
		}

		return true;
	}

	/**
	 * Restore a backup
	 *
	 * @param string $backupFile
	 *
	 * @return array
	 */
	public function restoreBackupFile( string $backupFile ) : array {
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}

		if ( $this->getVersionFromBackupName( $backupFile ) === false ) {
			return [
				false,
				wfMessage(
					'wsps-special_backup_restore_file_failure_old',
					$backupFile
				)->text()
			];
		}

		$path      = PSConfig::$config['exportPath'];
		$indexPath = PSConfig::$config['filePath'];
		$zip       = new ZipArchive();
		if ( $zip->open( $path . $backupFile ) === true ) {
			$this->removeRecursively(
				$indexPath,
				$indexPath
			);
			PSCore::setConfig(); // re-initiate deleted folder
			$zip->extractTo( $indexPath );
			$zip->close();

			return [
				true,
				wfMessage(
					'wsps-special_backup_restore_file_success',
					$backupFile
				)->text()
			];
		} else {
			return [
				false,
				wfMessage(
					'wsps-special_backup_restore_file_failure',
					$backupFile
				)->text()
			];
		}
	}

	/**
	 * Create a backup file
	 */
	public static function createZipFileBackup() {
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}
		$path            = PSConfig::$config['exportPath'];
		$indexPath       = PSConfig::$config['filePath'];
		$version         = str_replace(
			'.',
			'-',
			( PSConfig::$config['version'] )
		);
		$allFileinfo     = PSCore::getAllPageInfo();
		$addUploadedFile = [];
		foreach ( $allFileinfo as $fileToCheck ) {
			if ( isset( $fileToCheck['isFile'] ) && $fileToCheck['isFile'] === true ) {
				$addUploadedFile[] = $path . $fileToCheck['filestoredname'];
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

	/**
	 * Get a list of all backup files
	 *
	 * @return array
	 */
	public function getBackupList() : array {
		$data = [];
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}
		$path       = PSConfig::$config['exportPath'];
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

}
