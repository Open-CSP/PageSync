<?php

class WSpsShareHandler {

	/**
	 * @var bool
	 */
	private $share_file = false;

	/**
	 * @param string $name
	 */
	public function setShareFile( string $name ) {
		$this->share_file = $name;
	}

	/**
	 * Serve a download to the browser
	 */
	public function downloadShare() {
		if ( $this->share_file !== false ) {
			if ( file_exists( WSpsHooks::$config['filePath'] . $this->share_file ) ) {
				header( 'Content-type: application/zip' );
				header( 'Content-Disposition: attachment; filename="' . $this->share_file . '"' );
				readfile( WSpsHooks::$config['filePath'] . $this->share_file );
				exit();
			}
		}
	}

}
