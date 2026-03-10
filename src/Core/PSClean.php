<?php

namespace PageSync\Core;

use PageSync\Helpers\Colors;

class PSClean {

	/**
	 * @var array
	 */
	private array $indexList;

	/**
	 * @var array
	 */
	private array $serverList;

	/**
	 * @var string
	 */
	private string $exportPath;

	public function __construct() {
		$serverFullList = PSCore::getFilesFromServer( true );
		if ( !empty( $serverFullList ) ) {
			foreach ( $serverFullList as $file ) {
				$pathInfo = pathinfo( $file );
				$fileName = $pathInfo['filename'];
				if ( $pathInfo['extension'] === 'info' || $pathInfo['extension'] === 'data' ) {
					$this->serverList[] = $fileName;
					continue;
				}
				$explodedFileName = explode( '_', $fileName );
				$cnt = count( $explodedFileName ) - 2;
				$mergedFileName = '';
				if ( $cnt >= 1 ) {
					for ( $i = 0; $i < $cnt; $i++ ) {
						if ( $i === 0 ) {
							$mergedFileName .= $explodedFileName[$i];
						} else {
							$mergedFileName .= '_' . $explodedFileName[$i];
						}
					}
					$this->serverList[] = $mergedFileName;
				}
			}
		}
		$this->exportPath = PSConfig::$config['exportPath'];
		$indexList = PSCore::getFileIndex();
		if ( $indexList !== false ) {
			$this->indexList = $indexList;
		}
	}

	/**
	 * @return void
	 */
	public function cleanServerFiles(): void {
		echo Colors::cEcho(
			wfMessage( "wsps-maintenance-clean-header" )->plain(),
			"blue+bold",
			true,
			"",
			wfMessage( "wsps-maintenance-analyze-start" )->plain()
		);
		if ( empty( $this->serverList ) ) {
			echo Colors::cEcho(
				'No files on server',
				"yellow+bold",
				true
			);
			echo Colors::cEcho(
				wfMessage( "wsps-maintenance-clean-header" )->plain(),
				"blue+bold",
				true,
				"",
				wfMessage( "wsps-maintenance-analyze-end" )->plain()
			);

			return;
		}
		$this->serverList = array_unique( $this->serverList );
		$cntDeleted = 0;
		$cntNotDeleted = 0;
		$cntErrors = 0;
		$notDeleted = [];
		foreach ( $this->serverList as $infoFile ) {
			if ( !array_key_exists( $infoFile, $this->indexList ) ) {
				if ( $this->deleteInfoFile( $infoFile ) ) {
					$cntDeleted++;
				} else {
					$cntErrors++;
					$notDeleted[$infoFile] = false;
				}
				if ( $this->deleteSlotFiles( $infoFile ) ) {
					$cntDeleted++;
				} else {
					$cntErrors++;
					$notDeleted[$infoFile] = false;
				}
				if ( $this->deleteDatFiles( $infoFile ) ) {
					$cntDeleted++;
				} else {
					$cntErrors++;
					$notDeleted[$infoFile] = false;
				}
			} else {
				$cntNotDeleted++;
			}
		}

		if ( !empty( $notDeleted ) ) {
			$this->notDeletedInfo( $notDeleted );
		}

		echo Colors::cEcho(
			$cntErrors - 1 . ' could not be deleted',
			"red+bold",
			true
		);
		echo Colors::cEcho(
			$cntDeleted - 1 . ' files deleted',
			"yellow+bold",
			true
		);
		echo Colors::cEcho(
			$cntNotDeleted . ' info files not deleted',
			"green+bold",
			true
		);

		echo Colors::cEcho(
			wfMessage( "wsps-maintenance-clean-header" )->plain(),
			"blue+bold",
			true,
			"",
			wfMessage( "wsps-maintenance-analyze-end" )->plain()
		);
	}

	/**
	 * @param array $notDeleted
	 *
	 * @return void
	 */
	private function notDeletedInfo( array $notDeleted ): void {
		foreach ( $notDeleted as $file => $tmp ) {
			echo Colors::cEcho(
				$this->exportPath . $file,
				"red+bold",
				true,
				'NOT DELETED',
				'ERROR'
			);
		}
	}

	/**
	 * @param string $file
	 *
	 * @return void
	 */
	private function echoDeleted( string $file ): void {
		echo Colors::cEcho(
			$file,
			"yellow",
			false,
			'',
			"deleted"
		);
	}

	/**
	 * @param string $file
	 *
	 * @return void
	 */
	private function echoNotDeleted( string $file ): void {
		echo Colors::cEcho(
			$file,
			"red",
			false,
			'Could not be deleted',
			"ERROR"
		);
	}

	/**
	 * @param string $file
	 *
	 * @return bool
	 */
	private function deleteInfoFile( string $file ): bool {
		$infoFile = $file . '.info';
		if ( file_exists( $this->exportPath . $infoFile ) ) {
			if ( unlink( $this->exportPath . $infoFile ) ) {
				$this->echoDeleted( $infoFile );

				return true;
			} else {
				$this->echoNotDeleted( $infoFile );

				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param string $file
	 *
	 * @return bool
	 */
	private function deleteSlotFiles( string $file ): bool {
		$slotFiles = $file . '_slot*.wiki';
		$filesList = glob( $this->exportPath . $slotFiles );
		foreach ( $filesList as $singleFile ) {
			if ( unlink( $singleFile ) ) {
				$this->echoDeleted( $singleFile );
			} else {
				$this->echoNotDeleted( $singleFile );

				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $file
	 *
	 * @return bool
	 */
	private function deleteDatFiles( string $file ): bool {
		$dataFiles = $file . '.data';
		if ( file_exists( $this->exportPath . $dataFiles ) ) {
			if ( unlink( $this->exportPath . $dataFiles ) ) {
				$this->echoDeleted( $dataFiles );

				return true;
			} else {
				$this->echoNotDeleted( $dataFiles );

				return false;
			}
		} else {
			return false;
		}
	}
}
