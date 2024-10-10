<?php
/**
 * Created by  : Open CSP
 * Project     : PageSync
 * Filename    : PSAnalyzer.php
 * Description :
 * Date        : 2-10-2024
 * Time        : 12:07
 */

namespace PageSync\Core;


use PageSync\Helpers\Colors;

class PSAnalyzer {

	/**
	 * @var array
	 */
	private array $indexList;

	/**
	 * @var array
	 */
	private array $serverFullList;

	/**
	 * @var array
	 */
	private array $serverFileList = [];

	/**
	 * @var int
	 */
	private int $totalErrors = 0;

	/**
	 * @var int
	 */
	private int $indexListCount;

	/**
	 * @var int
	 */
	private int $serverFileCount;

	/**
	 * @var array
	 */
	private array $errorList = [];

	/**
	 * @param int $done
	 * @param int $total
	 * @param string $info
	 * @param int $width
	 *
	 * @return string
	 */
	private function progressBar( $done, $total, $info = "", $width = 50 ) {
		$perc = round( ( $done * 100 ) / $total );
		$bar = round( ( $width * $perc ) / 100 );

		if ( $bar > $width ) {
			$bar = $width;
		}

		return sprintf(
			"\033[K%s%%[%s>%s] %s/%s %s\r",
			$perc,
			str_repeat( "=", $bar ),
			str_repeat( " ", $width - $bar ),
			$done,
			$total,
			$info
		);
	}

	/**
	 * @param string $type
	 * @param string $message
	 * @param string $entry
	 *
	 * @return void
	 */
	private function addError( string $type, string $message, string $entry ) {
		$this->errorList[] = [ "type" => $type, "message" => $message, "entry" => $entry ];
	}

	/**
	 * @param string $infoFile
	 *
	 * @return ?array
	 */
	private function getInfoFile( string $infoFile ): ?array {
		if ( !file_exists( $infoFile ) ) {
			return null;
		}
		return json_decode( file_get_contents( $infoFile ), true );
	}

	/**
	 * @param string $infoFileName
	 * @param string $slotName
	 *
	 * @return string
	 */
	private function getFileNameForSlot( string $infoFileName, string $slotName ): string {
		return $infoFileName . "_slot_" . $slotName . ".wiki";
	}

	/**
	 * @return int
	 */
	private function checkInfoFiles(): int {
		$keys = [ 'filename','pagetitle', 'ns', 'username', 'changed', 'pageid', 'slots', 'models',
			'isFile', 'description', 'tags' ];
		echo Colors::cEcho(
			wfMessage( "wsps-maintenance-analyze-info-structure-heading" )->plain(),
			"blue+bold",
			true,
			"",
			wfMessage( "wsps-maintenance-analyze-start" )->plain(),
			true );
		$i = 1;
		$indexErrors = 0;
		foreach ( $this->serverFullList as $infoFile ) {
			$info = $this->getInfoFile( $infoFile );
			$pathInfo = pathinfo( $infoFile );
			$infoFileName = $pathInfo['filename'];
			$number = str_pad( $i, 5 ) . ": ";
			if ( $info === null ) {
				$this->addError( wfMessage( "wsps-maintenance-analyze-info-structure" )->plain(),
					wfMessage( "wsps-maintenance-analyze-missing-file-or-invalid-json" )->plain(), $infoFileName );
				echo Colors::cEcho(
					str_pad( $number . $infoFileName, 100, "." ),
					"yellow",
					false,
					wfMessage( "wsps-maintenance-analyze-fail" )->plain(),
					"",
					true
				);
				$indexErrors++;
				continue;
			}
			foreach ( $keys as $k ) {
				if ( !array_key_exists( $k, $info ) ) {
					$indexErrors++;
					$this->addError( wfMessage( "wsps-maintenance-analyze-info-structure" )->plain(),
						wfMessage( "wsps-maintenance-analyze-info-structure-missing-variable", $k )->plain(),
						$infoFileName );
					echo Colors::cEcho(
						str_pad( $number . $infoFileName, 100, "." ),
						"yellow",
						false,
						wfMessage( "wsps-maintenance-analyze-fail" )->plain(),
						"",
						true
					);
				}
			}
			if ( array_key_exists( 'slots', $info ) ) {
				$slots = $info['slots'];
				$slotsExploded = explode( ",", $slots );
				foreach ( $slotsExploded as $slotName ) {
					$slotFileName = $this->getFileNameForSlot( $infoFileName, $slotName );
					if ( !file_exists( PSConfig::$config['exportPath'] . $slotFileName ) ) {
						$indexErrors++;
						$this->addError( wfMessage( "wsps-maintenance-analyze-info-structure" )->plain(),
							wfMessage( "wsps-maintenance-analyze-info-structure-missing-wiki", $slotFileName )->plain(),
							$infoFileName );
						echo Colors::cEcho(
							str_pad( $number . $infoFileName, 100, "." ),
							"yellow",
							false,
							wfMessage( "wsps-maintenance-analyze-fail" )->plain(),
							"",
							true
						);
					}
				}
			}
			echo $this->progressBar( $i, $this->serverFileCount,
				Colors::cEcho( $infoFileName,
					"yellow",
					false,
					"",
					"",
					false
				) );
			// sleep( 1 );
			$i++;
		}

		echo "\033[K";
		echo Colors::cEcho( wfMessage( "wsps-maintenance-analyze-info-structure-heading" )->plain(),
			"blue+bold",
			true,
			wfMessage( "wsps-maintenance-analyze-errors-found", $indexErrors )->plain(),
			wfMessage( "wsps-maintenance-analyze-end" )->plain(),
			true );
		return $indexErrors;
	}

	/**
	 * @return int
	 */
	private function checkServerFiles(): int {
		echo Colors::cEcho( wfMessage( "wsps-maintenance-analyze-server-index-heading" )->plain(),
			"blue+bold",
			true,
			"",
			wfMessage( "wsps-maintenance-analyze-start" )->plain(),
			true );
		$i = 1;
		$indexErrors = 0;
		foreach ( $this->serverFileList as $infoFile ) {
			$number = str_pad( $i, 5 ) . ": ";
			if ( array_key_exists( $infoFile, $this->indexList ) ) {
				echo $this->progressBar( $i, $this->indexListCount,
					Colors::cEcho( $infoFile,
						"yellow",
						false,
						wfMessage( "wsps-maintenance-analyze-ok" )->plain(),
						"",
						false
					) );
				// sleep( 1 );
			} else {
				$indexErrors++;
				$this->addError( wfMessage( "wsps-maintenance-analyze-server-index" )->plain(),
					wfMessage( 'wsps-maintenance-analyze-file-on-server-not-index' )->plain(), $infoFile );
				echo Colors::cEcho(
					str_pad( $number . $infoFile, 100, "." ),
					"yellow",
					false,
					wfMessage( "wsps-maintenance-analyze-fail" )->plain(),
					"",
					true
				);
			}
			$i++;
		}

		echo "\033[K";
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-server-index-heading' )->plain(),
			"blue+bold",
			true,
			wfMessage( 'wsps-maintenance-analyze-errors-found', $indexErrors )->plain(),
			wfMEssage( "wsps-maintenance-analyze-end" )->plain(),
			true );
		return $indexErrors;
	}

	/**
	 * @return int
	 */
	private function checkIndex(): int {
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-index-server-heading' )->plain(),
			"blue+bold",
			true,
			"",
			wfMessage( "wsps-maintenance-analyze-start" )->plain(),
			true );
		$i = 1;
		$indexErrors = 0;
		foreach ( $this->indexList as $k => $indexEntry ) {
			$number = str_pad( $i, 5 ) . ": ";
			if ( in_array( $k, $this->serverFileList ) ) {
				echo $this->progressBar( $i, $this->indexListCount,
					Colors::cEcho( $k,
						"yellow",
						false,
						wfMessage( "wsps-maintenance-analyze-ok" )->plain(),
						"",
						false
					) );
				  // sleep( 1 );
			} else {
				$indexErrors++;
				$this->addError( wfMessage( 'wsps-maintenance-analyze-index-server' )->plain(),
					wfMessage( 'wsps-maintenance-analyze-file-in-index-not-server' )->plain(), $k );
				echo Colors::cEcho(
					str_pad( $number . $k, 100, "." ),
					"yellow",
					false,
					wfMessage( "wsps-maintenance-analyze-fail" )->plain(),
					"",
					true
				);
			}
			$i++;
		}
		echo "\033[K";
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-index-server-heading' )->plain(),
			"blue+bold",
			true,
			wfMessage( 'wsps-maintenance-analyze-errors-found', $indexErrors )->plain(),
			wfMessage( "wsps-maintenance-analyze-end" )->plain(),
			true );
		return $indexErrors;
	}

	/**
	 * @return int
	 */
	private function checkSync(): int {
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-server-wiki-heading' )->plain(),
			"blue+bold",
			true,
			"",
			wfMessage( "wsps-maintenance-analyze-start" )->plain(),
			true );
		$i = 1;
		$indexErrors = 0;
		foreach ( $this->indexList as $k => $indexEntry ) {
			$number = str_pad( $i, 5 ) . ": ";
			$ns = PSNameSpaceUtils::getNSFromTitleString( $indexEntry );
			$pageTitle = PSNameSpaceUtils::titleForDisplay( $ns, $indexEntry );
			$pageId = PSCore::getPageIdFromTitle( $pageTitle );
			$fileBaseNameInfo = PSCore::getInfoFileFromPageID( $pageId );
			if ( $fileBaseNameInfo['status'] === false ) {
				echo $fileBaseNameInfo['info'];
			} else {
				$fileBaseNameInfo = $fileBaseNameInfo['info'];
			}
			// todo: Catch if file does not exist!
			$infoContents = $this->getInfoFile( $fileBaseNameInfo );
			if ( $infoContents === null ) {
				$indexErrors++;
				$this->addError( wfMessage( 'wsps-maintenance-analyze-server-wiki' )->plain(),
					wfMessage( 'wsps-maintenance-analyze-missing-file-or-invalid-json' )->plain(), $k );
				echo Colors::cEcho(
					str_pad( $number . $k, 100, "." ),
					"yellow",
					false,
					wfMessage( "wsps-maintenance-analyze-fail" )->plain(),
					"",
					true
				);
				continue;
			}
			$pageContentFromWiki = PSSlots::getSlotsContentForPage( $pageId );
			if ( isset( $infoContents['slots'] ) ) {
				$infoSlots = explode( ',', $infoContents['slots'] );
			}
			$pageSlots = PSSlots::getSlotNamesForPageAndRevision( $pageId );
			foreach ( $pageSlots['slots'] as $slotToCheck ) {
				$slotFile = PSCore::getFileContent( $k, $slotToCheck );
				if ( $slotFile === false ) {
					$indexErrors++;
					$this->addError( wfMessage( 'wsps-maintenance-analyze-server-wiki' )->plain(),
						wfMessage( 'wsps-maintenance-analyze-server-wiki-error-slot-missing',
							$slotToCheck )->plain(), $k );
					echo Colors::cEcho(
						str_pad( $number . $k, 100, "." ),
						"yellow",
						false,
						wfMessage( "wsps-maintenance-analyze-fail" )->plain(),
						"",
						true
					);
					continue;
				}
				// var_dump( $slotFile );
				if ( $pageContentFromWiki[$slotToCheck]['content'] === $slotFile ) {
					echo $this->progressBar( $i, $this->indexListCount,
						Colors::cEcho( $k . wfMessage( 'wsps-maintenance-analyze-server-wiki-slot' )->plain()
							. $slotToCheck,
							"yellow",
							false,
							wfMessage( "wsps-maintenance-analyze-ok" )->plain(),
							"",
							false
						) );
				} else {
					$indexErrors++;
					$this->addError( wfMessage( 'wsps-maintenance-analyze-server-wiki' )->plain(),
						wfMessage( 'wsps-maintenance-analyze-server-wiki-error-slot-unsynced', $slotToCheck )->plain(),
						$k );
					echo Colors::cEcho(
						str_pad( $number . $k, 100, "." ),
						"yellow",
						false,
						wfMessage( "wsps-maintenance-analyze-fail" )->plain(),
						"",
						true
					);
				}
			}
			$i++;
		}
		echo "\033[K";
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-server-wiki-heading' )->plain(),
			"blue+bold",
			true,
			wfMessage( 'wsps-maintenance-analyze-errors-found', $indexErrors )->plain(),
			wfMessage( "wsps-maintenance-analyze-end" )->plain(),
			true );
		return $indexErrors;
	}

	public function analyze() {
		$this->serverFullList = PSCore::getFilesFromServer();
		if ( !empty( $this->serverFullList ) ) {
			foreach ( $this->serverFullList as $file ) {
				$pathInfo = pathinfo( $file );
				$this->serverFileList[] = $pathInfo['filename'];
			}
		}
		$psIndexPath = PSConfig::$config['filePath'];
		$psExportPath = PSConfig::$config['exportPath'];
		$psIndexPathColor = Colors::cEcho( $psIndexPath, "bold+yellow", false, "", "", false );
		$psExportPathColor = Colors::cEcho( $psExportPath, "bold+yellow", false, "", "", false );
		$indexList = PSCore::getFileIndex();
		if ( $indexList === false ) {
			$this->indexList = [];
		} else {
			$this->indexList = $indexList;
		}
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-analyzing' )->plain(),
			"blue+bold", true, "", wfMessage( 'wsps-maintenance-analyze-start' )->plain(), true );
		echo Colors::cEcho( "--------------------",
			"white", false, "", "", true );
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-index-path' )->plain() . $psIndexPathColor,
			"white" );
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-export-path' )->plain() . $psExportPathColor,
			"white" );
		echo "\n";
		if ( empty( $this->indexList ) === false && empty( $this->serverFullList ) ) {
			echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-nothing' )->plain(),
				"white", false, "", "", true );
			echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-analyzing' )->plain(),
				"blue+bold", true, "", "END", true );
			return;
		}
		if ( empty( $this->indexList ) && !empty( $this->serverFullList ) ) {
			echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-empty-index-but-files' )->plain(),
				"white", false, "", "", true );
			$i = 0;
			foreach ( $this->serverFileList as $file ) {
				$i++;
				echo Colors::cEcho( str_pad( $i, 3 ) . " : " . $file, "yellow", false, "", "", true );
			}
			echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-analyzing' )->plain(),
				"blue+bold", true, "", wfMessage( 'wsps-maintenance-analyze-end' )->plain(), true );
			return;
		}

		$this->indexListCount = count( $this->indexList );
		$this->serverFileCount = count( $this->serverFullList );
		$indexColorCount = Colors::cEcho( $this->indexListCount, "bold+yellow", false, "", "", false );
		$filesColorCount = Colors::cEcho( $this->serverFileCount, "bold+yellow", false, "", "", false );
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-index-file-entries' )->plain()
			. $indexColorCount, "white" );
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-server-file-entries' )->plain()
			. $filesColorCount, "white" );
		$this->totalErrors += $this->checkIndex();
		$this->totalErrors += $this->checkServerFiles();
		$this->totalErrors += $this->checkInfoFiles();
		$this->totalErrors += $this->checkSync();
		echo "\n\n";
		echo Colors::cEcho( "--------------------", "white", false, "", "", true );
		echo Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-analyzing' )->plain(),
			"blue+bold",
			true,
			wfMessage( 'wsps-maintenance-analyze-errors-found', $this->totalErrors )->plain(),
			wfMessage( 'wsps-maintenance-analyze-end' )->plain(),
			true );
		if ( $this->totalErrors === 0 ) {
			echo "\n\n"
				. Colors::cEcho( wfMessage( 'wsps-maintenance-analyze-nothing-found' )->plain(),
					"green+bold" );
			echo "\n\n";
		} else {
			$i = 1;
			echo "\n\n" . Colors::cEcho(
				wfMessage( 'wsps-maintenance-analyze-errors-found-total', $this->totalErrors )->plain(), "red+bold" );
			foreach ( $this->errorList as $error ) {
				echo Colors::cEcho( '"' . $error["message"] . '"',
					"yellow",
					false,
					$error["entry"],
					str_pad( $i,
						4 ) . " : " . $error["type"] );
				$i++;
			}
		}
	}

}