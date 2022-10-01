<?php

/**
 * Created by  : Wikibase Solutions
 * Project     : PageSync
 * Filename    : PSGitHub.class.php
 * Description :
 * Date        : 30-9-2022
 * Time        : 21:54
 */
class PSGitHub {

	private const PAGESYNC_SHARED_FILES_REPO = 'https://api.github.com/repos/Open-CSP/PageSync-SharedFiles/contents/';

	public string $error = '';

	/**
	 * @param string $url
	 *
	 * @return bool|string
	 */
	private function get( string $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_USERAGENT, "php/curl" );
		$output = curl_exec( $ch );
		$err     = curl_errno( $ch );
		$errMsg  = curl_error( $ch );
		curl_close( $ch );
		if ( $err === 0 ) {
			return $output;
		}
		$this->error = $errMsg;
		return false;
	}

	public function getFileList() {
		$content = $this->get( self::PAGESYNC_SHARED_FILES_REPO );
		if ( !$content ) {
			return $this->error;
		}
		$content = json_decode( $content, true );

		$lst = [];
		$t = 0;
		foreach ( $content as $folder ) {
			$folderContent = $this->get( self::PAGESYNC_SHARED_FILES_REPO . $folder['path'] . '/' );
			$folderContent = json_decode( $folderContent, true );
			foreach ( $folderContent as $file ) {
				$parts = pathinfo( $file['name'] );
				if ( $parts['extension'] === 'info' ) {
					$lst[$t]['info'] = file_get_contents( $file['download_url'] );
					$lst[$t]['name'] = $parts['filename'];
					$lst[$t]['zip']  = str_replace(
						'.info',
						'.zip',
						$file['download_url']
					);
				}
			}
		}
		return print_r( $lst, true ) ;
	}
}