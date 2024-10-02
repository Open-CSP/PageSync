<?php
/**
 * Created by  : Open CSP
 * Project     : PageSync
 * Filename    : PSAnalyzer.php
 * Description :
 * Date        : 2-10-2024
 * Time        : 12:07
 */

namespace MediaWiki\extensions\PageSync\src\Core;


use PageSync\Core\PSCore;

class PSAnalyzer {

	public function analyze() {
		$fileStored = PSCore::getFilesFromServer();
		$index = PSCore::getFileIndex();
		var_dump( $fileStored, $index );
	}

}