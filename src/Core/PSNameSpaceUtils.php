<?php
/**
 * Created by  : Wikibase Solutions
 * Project     : PageSync
 * Filename    : PSNameSpaceUtils.php
 * Description :
 * Date        : 4-10-2022
 * Time        : 14:17
 */

namespace PageSync\Core;

use MediaWiki\MediaWikiServices;
use WikiPage;

class PSNameSpaceUtils {

	/**
	 * @param int $ns
	 *
	 * @return string
	 */
	public static function getNameSpaceNameFromID( int $ns ): string {
		return MediaWikiServices::getInstance()->getContentLanguage()->getFormattedNsText( $ns );
	}

	/**
	 * @param int $id
	 *
	 * @return false|int Either Title as string or false
	 */
	public static function getPageNS( int $id ) {
		$article = WikiPage::newFromId( $id );
		if ( $article instanceof WikiPage ) {
			return $article->getTitle()->getNamespace();
		} else {
			return false;
		}
	}

	/**
	 * @param string $title
	 *
	 * @return int
	 */
	public static function getNSFromTitleString( string $title ): int {
		$res = explode( '_', $title );
		return $res[0];
	}

	/**
	 * @param string $title
	 *
	 * @return string
	 */
	public static function removeNSFromTitle( string $title ): string {
		$res = explode( '_', $title );
		unset( $res[0] );
		return implode( '_', $res );
	}

	/**
	 * @param int $ns
	 * @param string $title
	 *
	 * @return string
	 */
	public static function titleForDisplay( $ns, string $title ): string {
		$nsName = self::getNameSpaceNameFromID( $ns );
		if ( $ns !== 0 ) {
			return $nsName . ':' . self::removeNSFromTitle( $title );
		} else {
			return self::removeNSFromTitle( $title );
		}
	}

	/**
	 * @param int $id
	 *
	 * @return false|string Either Title as string or false
	 */
	public static function getNSFromId( int $id ) {
		$article = WikiPage::newFromId( $id );
		if ( $article instanceof WikiPage ) {
			return $article->getTitle()->getNamespace();
		} else {
			return false;
		}
	}

}
