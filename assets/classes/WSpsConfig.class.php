<?php

class WSpsConfig {

	/**
	 * @return void
	 */
	public function setAllDefaults(): void {
		$this->setDefaultAllowedGroups();
		$this->setDefaultFileNameSpaces();
		$this->setDefaultMaintenance();
		$this->setDefaultcontentSlotsToBeSynced();
		$this->setDefaultcontentFilePath();
		$this->setDefaultTempFilePath();
		$this->setDefaultUri();
	}

	/**
	 * @return void
	 */
	public function setVersionNr(): void {
		global $IP;
		$json = json_decode(
			file_get_contents( $IP . '/extensions/PageSync/extension.json' ),
			true
		);
		WSpsHooks::$config['version'] = $json['version'];
	}

	/**
	 * @return void
	 */
	private function setDefaultAllowedGroups(): void {
		WSpsHooks::$config[ 'allowedGroups' ] = [ 'sysop' ];
	}

	/**
	 * @return void
	 */
	private function setDefaultFileNameSpaces(): void {
		WSpsHooks::$config['fileNameSpaces'] = [
			6,
			-2
		];
	}

	/**
	 * @return void
	 */
	private function setDefaultMaintenance(): void {
		WSpsHooks::$config['maintenance']['doNotRestoreThesePages'] = [];
		WSpsHooks::$config['maintenance']['restoreFrom']            = '';
	}

	/**
	 * @return void
	 */
	private function setDefaultcontentSlotsToBeSynced(): void {
		WSpsHooks::$config['contentSlotsToBeSynced'] = 'all';
	}

	/**
	 * @return void
	 */
	private function setDefaultcontentFilePath(): void {
		global $IP;
		WSpsHooks::$config['filePath'] = $IP . '/extensions/PageSync/files/';
		WSpsHooks::$config['exportPath'] = WSpsHooks::$config['filePath'] . 'export/';
		$this->createIfNeededPath( WSpsHooks::$config['filePath'] );
		$this->createIfNeededPath( WSpsHooks::$config['exportPath'] );
	}

	/**
	 * @return void
	 */
	private function setDefaultTempFilePath(): void {
		global $IP;
		WSpsHooks::$config['tempFilePath'] = $IP . '/extensions/PageSync/Temp/';
		$this->createIfNeededPath( WSpsHooks::$config['tempFilePath'] );
	}

	/**
	 * @return void
	 */
	private function setDefaultUri(): void {
		global $wgScript;
		$url   = str_replace(
			'index.php',
			'',
			$wgScript
		);
		WSpsHooks::$config['uri'] = $url . 'extensions/PageSync/';
	}

	/**
	 * @return string
	 */
	private function getDefaultExportPath(): string {
		return WSpsHooks::$config['filePath'] . 'export/';
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	private function createIfNeededPath( string $path ): string {
		$path   = rtrim(
			$path,
			'/'
		);
		$path   .= '/';
		if ( ! file_exists( $path ) ) {
			mkdir(
				$path
			);
			chmod(
				$path,
				0777
			);
		}
		return $path;
	}

	/**
	 * @param array $wgWSPageSync
	 *
	 * @return void
	 */
	public function checkConfigFromMW( array $wgWSPageSync ): void {
		global $IP;
		if ( !isset( $wgWSPageSync['allowedGroups'] ) || !is_array( $wgWSPageSync['allowedGroups'] ) ) {

			$this->setDefaultAllowedGroups();
		}
		else {
			WSpsHooks::$config[ 'allowedGroups' ] = $wgWSPageSync['allowedGroups'];
		}
		if ( !isset( $wgWSPageSync['fileNameSpaces'] ) && !is_array( $wgWSPageSync['fileNameSpaces'] ) ) {
			$this->setDefaultFileNameSpaces();
		} else {
			WSpsHooks::$config['fileNameSpaces'] = $wgWSPageSync['fileNameSpaces'];
		}

		if ( ! isset( $wgWSPageSync['maintenance'] ) ) {
			$this->setDefaultMaintenance();
		} else {
			WSpsHooks::$config['maintenance'] = $wgWSPageSync['maintenance'];
		}

		if ( ! isset( $wgWSPageSync['contentSlotsToBeSynced'] ) ) {
			$this->setDefaultcontentSlotsToBeSynced();
		} else {
			WSpsHooks::$config['contentSlotsToBeSynced'] = $wgWSPageSync['contentSlotsToBeSynced'];
		}

		if ( isset( $wgWSPageSync['filePath'] ) && !empty( $wgWSPageSync['filePath'] ) ) {
			WSpsHooks::$config['filePath'] = $this->createIfNeededPath( $wgWSPageSync['filePath'] );
			WSpsHooks::$config['exportPath'] = $this->createIfNeededPath( $this->getDefaultExportPath() );
		} else {
			$this->setDefaultcontentFilePath();
		}

		if ( isset( $wgWSPageSync['tempFilePath'] ) && !empty( $wgWSPageSync['tempFilePath'] ) ) {
			$this->createIfNeededPath( $wgWSPageSync['tempFilePath']  );
		} else {
			$this->setDefaultTempFilePath();
		}

		$this->setDefaultUri();
	}

}