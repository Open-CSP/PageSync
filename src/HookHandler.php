<?php

namespace PageSync;

use ALItem;
use ALRow;
use ALSection;
use ALTree;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\MediaWikiServices;
use PageSync\Core\PSConfig;

class HookHandler implements ParserFirstCallInitHook {

	/**
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->getOutput()->addModules( 'ext.WSPageSync.scripts' );
		self::setConfig();
	}

	/**
	 * Read config and set appropriately
	 */
	public static function setConfig() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wsConfig = new PSConfig();
		$wsConfig->setVersionNr();
		if ( $config->has( "PageSync" ) ) {
			$WSPageSync = $config->get( "PageSync" );
			$wsConfig->checkConfigFromMW( $WSPageSync );
		} else {
			$wsConfig->setAllDefaults();
		}
	}

	/**
	 * Implements AdminLinks hook from Extension:Admin_Links.
	 *
	 * @param ALTree &$adminLinksTree
	 *
	 * @return bool
	 */
	public static function AdminLinks( ALTree &$adminLinksTree ) : bool {
		global $wgServer;
		$wsSection = $adminLinksTree->getSection( 'WikiBase Solutions' );
		if ( $wsSection === null ) {
			$section = new ALSection( 'WikiBase Solutions' );
			$adminLinksTree->addSection(
				$section,
				wfMessage( 'adminlinks_general' )->text()
			);
			$wsSection     = $adminLinksTree->getSection( 'WikiBase Solutions' );
			$extensionsRow = new ALRow( 'extensions' );
			$wsSection->addRow( $extensionsRow );
		}

		$extensionsRow = $wsSection->getRow( 'extensions' );

		if ( $extensionsRow === null ) {
			$extensionsRow = new ALRow( 'extensions' );
			$wsSection->addRow( $extensionsRow );
		}
		$extensionsRow->addItem(
			ALItem::newFromExternalLink(
				$wgServer . '/index.php/Special:WSps',
				'PageSync'
			)
		);

		return true;
	}
}