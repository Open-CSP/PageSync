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

		foreach ( $serverFullList as $file ) {
			$pathInfo = pathinfo( $file );
			$fileName = $pathInfo['filename'];
			if ( $pathInfo['extension'] === 'info' || $pathInfo['extension'] === 'data' ) {
				$this->serverList[] = $fileName;
				continue;
			}
			$explodedFileName = explode( '_', $fileName );
			$cnt = count( $explodedFileName );
			if ( $cnt > 2 ) {
				unset( $explodedFileName[ $cnt - 1 ] );
				unset( $explodedFileName[ $cnt - 2 ] );
				$this->serverList[] = implode( '_', $explodedFileName );
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
		$cntNotDeleted = 0;
		foreach ( $this->serverList as $infoFile ) {
			if ( !array_key_exists( $infoFile, $this->indexList ) ) {
				$this->deleteInfoFile( $infoFile );
				$this->deleteSlotFiles( $infoFile );
				$this->deleteDatFiles( $infoFile );
			} else {
				$cntNotDeleted++;
			}
		}

		echo Colors::cEcho(
			wfMessage( "wsps-maintenance-clean-header" )->plain(),
			"blue+bold",
			true,
			"",
			wfMessage( "wsps-maintenance-analyze-end" )->plain()
		);
	}

	/**
	 * @param string $file
	 * @param string $function
	 *
	 * @return void
	 */
	private function echoDeleted( string $file, string $function ): void {
		echo Colors::cEcho(
			$file,
			"yellow",
			false,
			$function,
			"deleted"
		);
	}

	/**
	 * @param string $file
	 * @param string $function
	 *
	 * @return void
	 */
	private function echoNotDeleted( string $file, string $function ): void {
		echo Colors::cEcho(
			$file,
			"red",
			false,
			'Could not be deleted',
			$function
		);
	}

	/**
	 * @param string $file
	 *
	 * @return void
	 */
	private function deleteInfoFile( string $file ): void {
		$infoFile = $this->exportPath . $file . '.info';
		if ( file_exists( $infoFile ) ) {
			if ( unlink( $infoFile ) ) {
				$this->echoDeleted( $infoFile, __function__ );
			} else {
				$this->echoNotDeleted( $infoFile, __function__ );
			}
		}
	}

	/**
	 * @param string $file
	 *
	 * @return void
	 */
	private function deleteSlotFiles( string $file ): void {
		$slotFiles = $this->exportPath . $file . '_slot*.wiki';
		$filesList = glob( $slotFiles );
		foreach ( $filesList as $singleFile ) {
			if ( unlink( $singleFile ) ) {
				$this->echoDeleted( $singleFile, __function__ );
			} else {
				$this->echoNotDeleted( $singleFile, __function__ );
			}
		}
	}

	/**
	 * @param string $file
	 *
	 * @return void
	 */
	private function deleteDatFiles( string $file ): void {
		$dataFiles = $this->exportPath . $file . '.data';
		if ( file_exists( $dataFiles ) ) {
			if ( unlink( $dataFiles ) ) {
				$this->echoDeleted( $dataFiles, __function__ );
			} else {
				$this->echoNotDeleted( $dataFiles, __function__ );

			}
		}
	}
}
