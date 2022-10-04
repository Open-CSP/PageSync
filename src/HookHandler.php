<?php

namespace PageSync;

use ALItem;
use ALRow;
use ALSection;
use ALTree;
use MediaWiki\MediaWikiServices;
use OutputPage;
use PageSync\Core\PSConfig;
use PageSync\Core\PSConverter;
use PageSync\Core\PSCore;
use Parser;
use Skin;
use SkinTemplate;
use User;
use WikiPage;

class HookHandler {

	/**
	 * @param Parser $parser
	 *
	 * @return void
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		PSCore::setConfig();
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 *
	 * @return void
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$out->addModules( 'ext.WSPageSync.scripts' );
		PSCore::setConfig();
	}

	/**
	 * Implements AdminLinks hook from Extension:Admin_Links.
	 *
	 * @param ALTree &$adminLinksTree
	 *
	 * @return bool
	 */
	public static function addToAdminLinks( ALTree &$adminLinksTree ) : bool {
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

	/**
	 * @param WikiPage &$article
	 * @param User &$user
	 * @param &$reason
	 * @param &$error
	 *
	 *
	 */
	public static function onArticleDelete( WikiPage &$article, User &$user, &$reason, &$error ) {
		$id       = $article->getId();
		$title    = PSCore::getPageTitleForFileName( $id );
		$fName    = PSCore::cleanFileName( $title );
		$username = $user->getName();
		$index    = PSCore::getFileIndex();
		if ( isset( $index[$fName] ) && $index[$fName] === $title ) {
			$result = PSCore::removeFileForExport(
				$id,
				$username
			);
		}

		return true;
	}

	/**
	 * @param SkinTemplate &$sktemplate
	 * @param array &$links
	 *
	 * @return bool|void
	 */
	public static function nav( SkinTemplate &$sktemplate, array &$links ) {
		global $wgUser, $wgScript;
		$title = null;
		$url   = str_replace(
			'index.php',
			'',
			$wgScript
		);
		// If not sysop.. return
		if ( empty(
		array_intersect(
			PSConfig::$config['allowedGroups'],
			MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $wgUser )
		)
		) ) {
			return;
		}

		if ( method_exists(
			$sktemplate,
			'getTitle'
		) ) {
			$title = $sktemplate->getTitle();
		}
		if ( $title === null ) {
			return;
		}

		$articleId = $title->getArticleID();

		if ( PSConverter::checkFileConsistency() === false || PSConverter::checkFileConsistency2() === false ) {
			global $wgArticlePath;
			$url                    = str_replace(
				'$1',
				'Special:PageSync',
				$wgArticlePath
			);
			$class                  = "wsps-notice";
			$links['views']['wsps'] = [
				"class"     => $class,
				"text"      => "",
				"href"      => $url,
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'PageSync cannot be currently used. Please click this button to visit the Special page',
				'rel'       => 'PageSync'
			];

			return true;
		}

		$fIndex = PSCore::getFileIndex();
		$tags   = [];
		if ( $articleId !== 0 ) {
			$class  = "wsps-toggle";
			$classt = "wspst-toggle";
			if ( $fIndex !== false && in_array(
					PSCore::getPageTitleForFileName( $articleId ),
					$fIndex
				) ) {
				$tags  = PSCore::getTagsFromPage( $articleId );
				$class .= ' wsps-active';
				if ( ! empty( $tags ) ) {
					$classt .= ' wspst-active';
				}
			} else {
				$classt .= ' wspst-hide';
			}
			$links['views']['wsps']  = [
				"class"     => $class,
				"text"      => "",
				"href"      => '#',
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'PageSync',
				'rel'       => 'PageSync'
			];
			$links['views']['wspst'] = [
				"class"     => $classt,
				"text"      => "",
				"href"      => '#',
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'PageSync Tags',
				'rel'       => 'PageSync Tags'
			];
		} else {
			$class                  = "wsps-error";
			$links['views']['wsps'] = [
				"class"     => $class,
				"text"      => "",
				"href"      => '#',
				"exists"    => '1',
				"primary"   => '1',
				'redundant' => '1',
				'title'     => 'PageSync - Not syncable',
				'rel'       => 'PageSync'
			];
		}

		return true;
	}
}
