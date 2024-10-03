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


class PSAnalyzer {

	public function analyze() {
		$fileStored = PSCore::getFilesFromServer();
		$index = PSCore::getFileIndex();
		var_dump( count( $fileStored ) );
		var_dump( count( $index ) );
		var_dump( $fileStored, $index );
	}

}