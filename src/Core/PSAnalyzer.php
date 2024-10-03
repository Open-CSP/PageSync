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

	private const FILE_IN_INDEX_NOT_ON_SERVER = "Entry in Index File, but not on server as .info File";
	private const FILE_ON_SERVER_NOT_IN_INDEX = ".info File is on server, but not in Index File";

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
	 * @return array
	 */
	private function getInfoFile( string $infoFile ): array {
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
		echo Colors::cEcho( "Checking .info File structure (info-structure)",
			"blue+bold",
			true,
			"",
			"START",
			true );
		$i = 1;
		$indexErrors = 0;
		foreach ( $this->serverFullList as $infoFile ) {
			$info = $this->getInfoFile( $infoFile );
			$pathInfo = pathinfo( $infoFile );
			$infoFileName = $pathInfo['filename'];
			$number = str_pad( $i, 5 ) . ": ";
			foreach ( $keys as $k ) {
				if ( !array_key_exists( $k, $info ) ) {
					$indexErrors++;
					$this->addError( "info-structure", "Missing variable in .info file: " . $k, $infoFileName );
					echo Colors::cEcho(
						str_pad( $number . $infoFileName, 100, "." ),
						"yellow",
						false,
						"FAIL",
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
						$this->addError( "info-structure", "Missing .wiki file on server: "
							. $slotFileName, $infoFileName );
						echo Colors::cEcho(
							str_pad( $number . $infoFileName, 100, "." ),
							"yellow",
							false,
							"FAIL",
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
		echo Colors::cEcho( "Checking .info File structure (info-structure)",
			"blue+bold",
			true,
			$indexErrors . " error(s) found",
			"END",
			true );
		return $indexErrors;
	}

	/**
	 * @return int
	 */
	private function checkServerFiles(): int {
		echo Colors::cEcho( "Checking if .info File exists in index (server2index)",
			"blue+bold",
			true,
			"",
			"START",
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
						"OK",
						"",
						false
					) );
				// sleep( 1 );
			} else {
				$indexErrors++;
				$this->addError( "server2index", self::FILE_ON_SERVER_NOT_IN_INDEX, $infoFile );
				echo Colors::cEcho(
					str_pad( $number . $infoFile, 100, "." ),
					"yellow",
					false,
					"FAIL",
					"",
					true
				);
			}
			$i++;
		}
		echo "\033[K";
		echo Colors::cEcho( "Checking if .info File exists in index (server2index)",
			"blue+bold",
			true,
			$indexErrors . " error(s) found",
			"END",
			true );
		return $indexErrors;
	}

	/**
	 * @return int
	 */
	private function checkIndex(): int {
		echo Colors::cEcho( "Checking if Index entry exists on server (index2server)",
			"blue+bold",
			true,
			"",
			"START",
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
						"OK",
						"",
						false
					) );
				// sleep( 1 );
			} else {
				$indexErrors++;
				$this->addError( "index2server", self::FILE_IN_INDEX_NOT_ON_SERVER, $k );
				echo Colors::cEcho(
					str_pad( $number . $k, 100, "." ),
					"yellow",
					false,
					"FAIL",
					"",
					true
				);
			}
			$i++;
		}
		echo "\033[K";
		echo Colors::cEcho( "Checking if Index entry exists on server (index2server)",
			"blue+bold",
			true,
			$indexErrors . " error(s) found",
			"END",
			true );
		return $indexErrors;
	}

	/**
	 * @return int
	 */
	private function checkSync(): int {
		echo Colors::cEcho( "Checking if files on server are in sync with Wiki (server2wiki)",
			"blue+bold",
			true,
			"",
			"START",
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
			$infoContents = $this->getInfoFile( $fileBaseNameInfo );
			$pageContentFromWiki = PSSlots::getSlotsContentForPage( $pageId );
			if ( isset( $infoContents['slots'] ) ) {
				$infoSlots = explode( ',', $infoContents['slots'] );
			}
			$pageSlots = PSSlots::getSlotNamesForPageAndRevision( $pageId );
			foreach ( $pageSlots['slots'] as $slotToCheck ) {
				$slotFile = PSCore::getFileContent( $k, $slotToCheck );
				if ( $slotFile === false ) {
					$indexErrors++;
					$this->addError( "server2wiki", "Slot '$slotToCheck' is missing on server/", $k );
					echo Colors::cEcho(
						str_pad( $number . $k, 100, "." ),
						"yellow",
						false,
						"FAIL",
						"",
						true
					);
					continue;
				}
				// var_dump( $slotFile );
				if ( $pageContentFromWiki[$slotToCheck]['content'] === $slotFile ) {
					echo $this->progressBar( $i, $this->indexListCount,
						Colors::cEcho( $k . " Slot : $slotToCheck",
							"yellow",
							false,
							"OK",
							"",
							false
						) );
				} else {
					$indexErrors++;
					$this->addError( "server2wiki",
						"Slot '$slotToCheck' content in Wiki and on Server are not identical",
						$k );
					echo Colors::cEcho(
						str_pad( $number . $k, 100, "." ),
						"yellow",
						false,
						"FAIL",
						"",
						true
					);
				}
			}
			$i++;
		}
		echo "\033[K";
		echo Colors::cEcho( "Checking if files on server are in sync with Wiki (server2wiki)",
			"blue+bold",
			true,
			$indexErrors . " error(s) found",
			"END",
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
		echo Colors::cEcho( "Analyzing...", "blue+bold", true, "", "START", true );
		echo Colors::cEcho( "--------------------", "white", false, "", "", true );
		echo Colors::cEcho( "PageSync index path  : " . $psIndexPathColor, "white" );
		echo Colors::cEcho( "PageSync export path : " . $psExportPathColor, "white" );
		echo "\n";
		if ( empty( $this->indexList ) === false && empty( $this->serverFullList ) ) {
			echo Colors::cEcho( "Index and server files are empty. Nothing to work on", "white", false, "", "", true );
			echo Colors::cEcho( "Analyzing...", "blue+bold", true, "", "END", true );
			return;
		}
		if ( empty( $this->indexList ) && !empty( $this->serverFullList ) ) {
			echo Colors::cEcho( "Index is empty, but you have files on the server: ", "white", false, "", "", true );
			$i = 0;
			foreach ( $this->serverFileList as $file ) {
				$i++;
				echo Colors::cEcho( str_pad( $i, 3 ) . " : " . $file, "yellow", false, "", "", true );
			}
			echo Colors::cEcho( "Analyzing...", "blue+bold", true, "", "END", true );
			return;
		}

		$this->indexListCount = count( $this->indexList );
		$this->serverFileCount = count( $this->serverFullList );
		$indexColorCount = Colors::cEcho( $this->indexListCount, "bold+yellow", false, "", "", false );
		$filesColorCount = Colors::cEcho( $this->serverFileCount, "bold+yellow", false, "", "", false );
		echo Colors::cEcho( "Index  File entries : " . $indexColorCount, "white" );
		echo Colors::cEcho( "Server File entries : " . $filesColorCount, "white" );
		$this->totalErrors += $this->checkIndex();
		$this->totalErrors += $this->checkServerFiles();
		$this->totalErrors += $this->checkInfoFiles();
		$this->totalErrors += $this->checkSync();
		echo "\n\n";
		echo Colors::cEcho( "--------------------", "white", false, "", "", true );
		echo Colors::cEcho( "Analyzing...",
			"blue+bold",
			true,
			$this->totalErrors . " error(s) found",
			"END",
			true );
		if ( $this->totalErrors === 0 ) {
			echo "\n\n"
				. Colors::cEcho(
					"PageSync seems to be in top shape! No Admins have been messing around!" .
					" Give them a tap on the back for a good job!",
					"green+bold" );
			echo "\n\n";
		} else {
			$i = 1;
			echo "\n\n" . Colors::cEcho( "PageSync has found some inconsistencies! ($this->totalErrors)", "red+bold" );
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