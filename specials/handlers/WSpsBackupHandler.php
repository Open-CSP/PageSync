<?php

class WSpsBackupHandler {


	private $backup_file = false;

	/**
	 * @param string $name
	 */
	public function setBackFile( string $name ) {
		$this->backup_file = $name;
	}

	/**
	 * Server a download to the browser
	 */
	public function downloadBackup(){
		if ( false !== $this->backup_file ) {
			if ( file_exists( WSpsHooks::$config['exportPath'] . $this->backup_file ) ) {
				header( 'Content-type: application/zip' );
				header( 'Content-Disposition: attachment; filename="' . $this->backup_file . '"' );
				readfile( WSpsHooks::$config['exportPath'] . $this->backup_file );
				exit();
			}
		}
	}

}