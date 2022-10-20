<?php

namespace PageSync\Core;

class PSConfig {

	/**
	 * @var bool|array
	 */
	public static $config = false;

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
		self::$config['version'] = $json['version'];
	}

	/**
	 * @return void
	 */
	private function setDefaultAllowedGroups(): void {
		self::$config[ 'allowedGroups' ] = [ 'sysop' ];
	}

	/**
	 * @return void
	 */
	private function setDefaultFileNameSpaces(): void {
		self::$config['fileNameSpaces'] = [
			6,
			-2
		];
	}

	/**
	 * @return void
	 */
	private function setDefaultMaintenance(): void {
		self::$config['maintenance']['doNotRestoreThesePages'] = [];
		self::$config['maintenance']['restoreFrom']            = '';
	}

	/**
	 * @return void
	 */
	private function setDefaultcontentSlotsToBeSynced(): void {
		self::$config['contentSlotsToBeSynced'] = 'all';
	}

	/**
	 * @return void
	 */
	private function setDefaultcontentFilePath(): void {
		global $IP;
		self::$config['filePath'] = $IP . '/extensions/PageSync/files/';
		self::$config['exportPath'] = self::$config['filePath'] . 'export/';
		$this->createIfNeededPath( self::$config['filePath'] );
		$this->createIfNeededPath( self::$config['exportPath'] );
	}

	/**
	 * @return void
	 */
	private function setDefaultTempFilePath(): void {
		global $IP;
		self::$config['tempFilePath'] = $IP . '/extensions/PageSync/Temp/';
		$this->createIfNeededPath( self::$config['tempFilePath'] );
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
		self::$config['uri'] = $url . 'extensions/PageSync/';
	}

	/**
	 * @return string
	 */
	private function getDefaultExportPath(): string {
		return self::$config['filePath'] . 'export/';
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
	 * @param array $PageSync
	 *
	 * @return void
	 */
	public function checkConfigFromMW( array $PageSync ): void {
		if ( !isset( $PageSync['allowedGroups'] )
			 || !is_array( $PageSync['allowedGroups'] )
			 || empty( $PageSync['allowedGroups'] ) ) {
			$this->setDefaultAllowedGroups();
		} else {
			self::$config[ 'allowedGroups' ] = $PageSync['allowedGroups'];
		}
		if ( !isset( $PageSync['fileNameSpaces'] ) && !is_array( $PageSync['fileNameSpaces'] ) ) {
			$this->setDefaultFileNameSpaces();
		} else {
			self::$config['fileNameSpaces'] = $PageSync['fileNameSpaces'];
		}

		if ( !isset( $PageSync['maintenance'] ) ) {
			$this->setDefaultMaintenance();
		} else {
			self::$config['maintenance'] = $PageSync['maintenance'];
		}

		if ( !isset( $PageSync['contentSlotsToBeSynced'] ) ) {
			$this->setDefaultcontentSlotsToBeSynced();
		} else {
			self::$config['contentSlotsToBeSynced'] = $PageSync['contentSlotsToBeSynced'];
		}

		if ( isset( $PageSync['filePath'] ) && !empty( $PageSync['filePath'] ) ) {
			self::$config['filePath'] = $this->createIfNeededPath( $PageSync['filePath'] );
			self::$config['exportPath'] = $this->createIfNeededPath( $this->getDefaultExportPath() );
		} else {
			$this->setDefaultcontentFilePath();
		}

		if ( isset( $PageSync['tempFilePath'] ) && !empty( $PageSync['tempFilePath'] ) ) {
			$this->createIfNeededPath( $PageSync['tempFilePath'] );
		} else {
			$this->setDefaultTempFilePath();
		}

		$this->setDefaultUri();
	}

}