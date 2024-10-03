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

	public function analyze() {
		$storedFilesClean = [];
		$fileStored = PSCore::getFilesFromServer();
		if ( !empty( $fileStored ) ) {
			foreach ( $fileStored as $file ) {
				$pathInfo = pathinfo( $file );
				$storedFilesClean[] = $pathInfo['filename'];
			}
		}
		$index = PSCore::getFileIndex();
		echo Colors::cEcho( "Analyzing files...", "blue+bold", true, "", "START", true );
		echo Colors::cEcho( "------------------", "white", false, "", "", true );
		if ( $index === false && empty( $fileStored ) ) {
			echo Colors::cEcho( "Index and server files are empty. Nothing to work on", "white", false, "", "", true );
			echo Colors::cEcho( "Analyzing files   ", "blue+bold", true, "", "END", true );
			return;
		}
		if ( $index === false && !empty( $fileStored ) ) {
			echo Colors::cEcho( "Index is empty, but you have files on the server: ", "white", false, "", "", true );
			$i = 0;
			foreach ( $storedFilesClean as $file ) {
				$i++;
				echo Colors::cEcho( str_pad( $i, 3 ) . " : " . $file, "yellow", false, "", "", true );
			}
			echo Colors::cEcho( "Analyzing files   ", "blue+bold", true, "", "END", true );
			return;
		}

		//var_dump( count( $fileStored ) );
		//var_dump( count( $index ) );
		//var_dump( $fileStored, $index );
		$totalIndex = count( $index );
		$totalFiles = count( $fileStored );
		$i = 0;
		$indexColorCount = Colors::cEcho( $totalIndex, "bold+yellow", false, "", "", false );
		$filesColorCount = Colors::cEcho( $totalFiles, "bold+yellow", false, "", "", false );
		echo Colors::cEcho( "Index File entries : " . $indexColorCount, "white" );
		echo Colors::cEcho( "      File entries : " . $filesColorCount, "white" );
		echo Colors::cEcho( "Checking if Index entry exists on server", "blue+bold", true, "", "START", true );
		$i = 1;
		$indexErrors = 0;
		$totalErrors = 0;
		foreach ( $index as $k => $indexEntry ) {
			$number = str_pad( $i, 5 ) . ":";
			if ( in_array( $k, $storedFilesClean ) ) {
				echo $this->progressBar( $i, $totalIndex,
					Colors::cEcho( $k,
					"yellow",
					false,
					"OK",
					"",
					false
				) );
				sleep(1);
			} else {
				$indexErrors++;
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
		echo Colors::cEcho( "Checking if Index entry exists on server", "blue+bold", true, $indexErrors . " error(s) found", "END", true );
		$totalErrors += $indexErrors;

		echo Colors::cEcho( "Analyzing files   ", "blue+bold", true, $totalErrors . " erros(s) found analyzing", "END", true );
	}

}