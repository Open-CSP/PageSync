<?php
/**
 * Overview for the WSps extension
 *
 * @file
 * @ingroup Extensions
 */

namespace PageSync\Special;

use ApiMain;
use DerivativeRequest;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use PageSync\Core\PSConfig;
use PageSync\Core\PSConverter;
use PageSync\Core\PSCore;
use PageSync\Handlers\WSpsBackupHandler;
use PageSync\Handlers\WSpsConvertHandler;
use PageSync\Handlers\WSpsShareHandler;
use PageSync\Helpers\PSGitHub;
use PageSync\Helpers\PSRender;
use PageSync\Helpers\PSShare;
use PageSync\Helpers\WSpsHooksBackup;
use SpecialPage;

/**
 * Class WSpsSpecial
 */
class WSpsSpecial extends SpecialPage {

	/**
	 * @var string
	 */
	public $url, $version, $logo, $assets;

	/**
	 * WSpsSpecial constructor.
	 */
	public function __construct() {
		parent::__construct( 'WSps' );
	}

	/**
	 * Special page group
	 *
	 * @return string
	 */
	public function getGroupName() : string {
		return 'Wikibase';
	}

	/**
	 * @param string $text
	 * @param string $type
	 *
	 * @return string
	 */
	public static function makeAlert( string $text, string $type = "danger" ) : string {
		$ret = '<div class="uk-alert-' . $type . ' uk-margin-large-top" uk-alert>';
		$ret .= '<a class="uk-alert-close" uk-close></a>';
		$ret .= '<p>' . $text . '</p></div>';

		return $ret;
	}

	/**
	 * @param string $name
	 * @param bool $checkIfEmpty
	 *
	 * @return false|mixed
	 */
	public static function getPost( string $name, bool $checkIfEmpty = true ) {
		if ( $checkIfEmpty ) {
			if ( isset( $_POST[$name] ) && ! empty( $_POST[$name] ) ) {
				return $_POST[$name];
			} else {
				return false;
			}
		}

		return $_POST[$name] ?? false;
	}

	/**
	 * @param string $name
	 * @param bool $checkIfEmpty
	 *
	 * @return false|mixed
	 */
	public static function getGet( string $name, bool $checkIfEmpty = true ) {
		if ( $checkIfEmpty ) {
			if ( isset( $_GET[$name] ) && ! empty( $_GET[$name] ) ) {
				return $_GET[$name];
			} else {
				return false;
			}
		}

		return $_GET[$name] ?? false;
	}

	/**
	 * @param PSRender $render
	 * @param int $activeTab
	 *
	 * @return string
	 */
	private function setResourcesAndMenu( PSRender $render, int $activeTab ) : string {
		$ret = $render->loadResources();
		$ret .= $render->renderMenu(
			$this->url,
			$this->logo,
			$this->version,
			$activeTab
		);

		return $ret;
	}

	/**
	 * @param string|false $query
	 * @param bool $returnUnFiltered
	 *
	 * @return array|false|mixed
	 */
	public function doAsk( $query = false, bool $returnUnFiltered = false ) {
		if ( $query === false ) {
			$query = '[[Class::Managed item]] [[Status of managed item::Live]] |link=none |sep=<br> |limit=9999';
		}
		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(),
				// Fallback upon $wgRequest if you can't access context
				[
					'action' => 'ask',
					'query'  => $query
				],
				true // treat this as a POST
			),
			false // not write.
		);
		$api->execute();
		$data = $api->getResult()->getResultData();
		if ( !isset( $data['query']['results'] ) ) {
			return false;
		}

		$data = $data['query']['results'];

		if ( !$returnUnFiltered ) {
			$listOfPages = [];
			foreach ( $data as $page ) {
				$listOfPages[] = $page['fulltext'];
			}
			sort( $listOfPages );

			return $listOfPages;
		} else {
			return $data;
		}
	}

	/**
	 * Show the page to the user
	 *
	 * @param string|null $sub The subpage string argument (if any).
	 *
	 * @throws Exception
	 */
	public function execute( $sub ) {
		$user = $this->getUser();
		global $IP, $wgScript;
		$out            = $this->getOutput();
		$usr            = $user->getName();
		$groups         = $user->getGroups();
		$showAnyMessage = false;
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}

		if ( PSConfig::$config === false ) {
			$out->addHTML( '<p>' . wfMessage( 'wsps-api-error-no-config-body' )->text() . '</p>' );

			return true;
		}
		if ( empty( array_intersect(
			PSConfig::$config['allowedGroups'],
			MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $user )
		) ) ) {
			$out->addHTML( '<p>Nothing to see here, only interesting stuff for Admins</p>' );

			return true;
		}
		//include( $IP . '/extensions/PageSync/assets/classes/WSpsRender.class.php' );

		$render = new PSRender();

		$this->url     = str_replace(
			'index.php',
			'',
			$wgScript
		);
		$this->version = \ExtensionRegistry::getInstance()->getAllThings()["PageSync"]["version"];
		$this->logo    = '/extensions/PageSync/assets/images/pagesync.png';
		$this->assets  = '/extensions/PageSync/assets/images/';
		$style         = $render->getStyle( $this->assets );

		$wspsAction = self::getGet( 'action' );

		// First handle serving backup file for download, before we output anything
		if ( $wspsAction !== false && strtolower( $wspsAction ) === 'backup' ) {
			$pAction = self::getPost( 'wsps-action' );
			if ( $pAction === 'download-backup' ) {
				$backupHandler = new WSpsBackupHandler();
				$backupHandler->setBackFile( self::getPost( 'ws-backup-file' ) );
				$backupHandler->downloadBackup();
			}
		}

		// First handle serving share file for download, before we output anything
		if ( false !== $wspsAction && strtolower( $wspsAction ) === 'share' ) {
			$pAction = self::getPost( 'wsps-action' );
			if ( $pAction === 'download-share' ) {
				$backupHandler = new WSpsShareHandler();
				$backupHandler->setShareFile( self::getPost( 'ws-share-file' ) );
				$backupHandler->downloadShare();
			}
		}


		$this->setHeaders();
		$out->setPageTitle( '' );
		if ( PSConverter::checkFileConsistency2() === false ) {
			// Preview files affected
			$out->addHTML('<p>Please use maintenance script with --convert-2-version-2 first</p>' );
			return true;
		}
		switch ( strtolower( $wspsAction ) ) {
			case "pedit":

				$pAction = self::getPost( 'wsps-action' );
				$peditSpecial = new PSSpecialEdit();
				switch ( $pAction ) {
					case "wsps-edit-information":
						$res = $peditSpecial->editInformation();
						if ( $res !== false ) {
							$out->addHTML( $res );
						}
						break;
					case "wsps-edit":
						$pageId = self::getPost( 'id' );
						if ( $pageId !== false ) {
							$pagePath = PSCore::getInfoFileFromPageID( $pageId );
							if ( $pagePath['status'] === false ) {
								$out->addHTML( 'page not found: ' . $pageId );
								break;
							}
							$out->addHTML(
								$this->setResourcesAndMenu(
									$render,
									3
								)
							);
							$out->addHTML( $peditSpecial->edit( $render, $pagePath, $pageId ) );
							return true;
						}
				}
				break;
			case "convert":
				$out->addHTML(
					$this->setResourcesAndMenu(
						$render,
						0
					)
				);
				$convertHandler = new WSpsConvertHandler();
				if ( PSConverter::checkFileConsistency() === false ) {
					$pAction = self::getPost( 'wsps-action' );

					// Do the actual conversion
					if ( $pAction === 'wsps-convert-real' ) {
						$out->addHTML( $convertHandler->convertForReal( $render ) );
						$out->addHTML( $style );

						return true;
					}

					// Preview files affected
					$out->addHTML( $convertHandler->preview( $render ) );
					$out->addHTML( $style );

					return true;
				}
				if ( PSConverter::checkFileConsistency2() === false ) {
					// Preview files affected
					$out->addHTML('<p>Please use maintenance script with --convert-2-version-2 first</p>' );
					return true;
				}
				break;
			case "share":
				$out->addHTML(
					$this->setResourcesAndMenu(
						$render,
						3
					)
				);
				if ( !extension_loaded( 'zip' ) ) {
					$out->addHTML(
						self::makeAlert( $this->msg( 'wsps-special_backup_we_need_zip_extension' )->text() )
					);
					$out->addHTML( $style );

					return true;
				}
				$share = new PSShare();
				$specialShare = new PSSpecialShare();
				//Handle any backup actions
				$backActionResult = '';
				$pAction = self::getPost( 'wsps-action' );
				switch ( $pAction ) {
					case "wsps-do-download-install":
						$out->addHTML( $specialShare->installShare( $usr ) );
						break;
					case "wsps-share-downloadurl":
						$out->addHTML( $specialShare->showDownloadShareInformation( $share, $render ) );
						return true;
					case "delete-share":
						$backActionResult = $specialShare->deleteShare( $share );
						break;
					case "wsps-share-docancel":
						break;
					case "wsps-share-doshare":
						$result = $specialShare->doShare( $usr, $share, $render );
						if ( $result !== false ) {
							$out->addHTML( $result );
						}
						break;
					case "wsps-share-select-tags":
						$result = $specialShare->selecTags( $share, $render );
						if ( $result !== false ) {
							$out->addHTML( $result );
							return true;
						}
						$out->addHTML( self::makeAlert( 'No pages found with these tags' ) );
						break;
					case "wsps-share-install":
						$out->addHTML( $specialShare->showInstallShare( $share, $render ) );
						return true;
					case "wsps-share-create":
						$out->addHTML( $specialShare->createShare( $share, $render ) );
						return true;
				}
				$body = $backActionResult;
				$body .= '<p>' . $this->msg( 'wsps-content_share_information' ) . '</p>';
				$listOfsharePages = $share->getShareList();
				$nr   = count( $listOfsharePages );
				$body .= wfMessage(
					'wsps-special_share_count',
					$nr
				)->text();
				$body .= $share->renderShareList( $listOfsharePages );
				$footer = $share->renderChooseAction();

				$out->addHTML( $render->renderCard( $this->msg( 'wsps-content_share' ),"Create or Download Shared File", $body, $footer ) );
				$out->addHTML( $style );
				return true;
			case "backup":
				$out->addHTML(
					$this->setResourcesAndMenu(
						$render,
						2
					)
				);
				$psBackup         = new WSpsHooksBackup();
				$backActionResult = false;

				// check if we have zip extension
				if ( ! extension_loaded( 'zip' ) ) {
					$out->addHTML(
						self::makeAlert( wfMessage( 'wsps-special_backup_we_need_zip_extension' )->text() )
					);
					$out->addHTML( $style );

					return true;
				}

				//Handle any backup actions
				$pAction = self::getPost( 'wsps-action' );
				switch ( $pAction ) {
					case "wsps-backup":
						$psBackup->createZipFileBackup();
						break;
					case "delete-backup":
						$resultDeleteBackup = false;
						$backupFile         = self::getPost( 'ws-backup-file' );
						if ( $backupFile !== false ) {
							$resultDeleteBackup = $psBackup->deleteBackupFile( $backupFile );
						}
						if ( $resultDeleteBackup === true ) {
							$backActionResult = wfMessage(
								'wsps-special_backup_delete_file_success',
								$backupFile
							)->text();
						} else {
							$backActionResult = wfMessage(
								'wsps-special_backup_delete_file_error',
								$backupFile
							)->text();
						}
						break;
					case "restore-backup":
						$backActionResult = false;
						$backupFile       = self::getPost( 'ws-backup-file' );
						if ( $backupFile !== false ) {
							$resRestore = $psBackup->restoreBackupFile( $backupFile );
							if ( $resRestore[0] === true ) {
								$backActionResult = $resRestore[1];
							} else {
								$backActionResult = $resRestore[1];
							}
						}
						break;
				}

				// Show list of backups
				$data = $psBackup->getBackupList();
				$nr   = count( $data );
				$html = wfMessage(
					'wsps-special_backup_count',
					$nr
				)->text();
				if ( $nr >= 1 ) {
					$html .= $render->renderBackups(
						$data
					);
				}
				$btn_backup = '<form method="post" action="' . $wgScript . '/Special:WSps?action=backup">';
				$btn_backup .= '<input type="hidden" name="wsps-action" value="wsps-backup">';
				$btn_backup .= '<input type="submit" class="uk-button uk-button-primary uk-margin-small-bottom uk-text-small" value="';
				$btn_backup .= wfMessage( 'wsps-error_file_consistency_btn_backup' )->text();
				$btn_backup .= '"></form>';

				$html .= $btn_backup;
				if ( $backActionResult !== false ) {
					$out->addHTML( $backActionResult );
				}
				$out->addHTML( '<h3>' . $this->msg( 'wsps-content_backups' ) . '</h3>' );
				$out->addHTML( $style );
				$out->addHTML( $html );

				return true;
			case "exportcustom":
				$out->addHTML(
					$this->setResourcesAndMenu(
						$render,
						1
					)
				);
				// First check if we have SMW
				$specialSMW = new PSSpecialSMWQeury();
				if ( !$specialSMW->isExtensionInstalled( 'SemanticMediaWiki' ) ) {
					$out->addHTML( self::makeAlert( wfMessage( 'wsps-special_custom_query_we_need_smw' )->text() ) );
					$out->addHTML( $style );

					return true;
				}

				$pAction = self::getPost( 'wsps-action' );
				$error   = '';

				switch ( $pAction ) {
					case "wsps-import-query" :
						$request = $this->getRequest();
						$out->addHTML( $specialSMW->importQuery( $request, $usr ) );
						$error = $specialSMW->error;
						return true;
					case "doQuery" :
						$query = self::getPost( 'wsps-query' );

						if ( $query === false ) {
							$error = self::makeAlert( wfMessage( 'wsps-special_custom_query_not_found' )->text() );
						} else {
							$result = $this->doAsk( $query );

							$nr = count( $result );

							$form       = $render->renderDoQueryForm( $query, true );
							$html       = $form;
							$bodyResult = $render->renderDoQueryBody( $result );
							$html       .= $bodyResult['html'];

							$header = wfMessage( 'wsps-special_custom_query_result' )->text();
							$header .= '<p>' . wfMessage( 'wsps-special_custom_query' )->text(
								) . '<span class="uk-text-warning">' . htmlspecialchars( $query ) . '</span></p>';
							$header .= wfMessage(
								'wsps-special_custom_query_result_text1',
								$nr
							)->text();
							$header .= wfMessage(
								'wsps-special_custom_query_result_text2',
								$bodyResult['active']
							)->text();
							$html   = $header . $html;
							$out->addHTML( $style );
							$out->addHTML( $html );

							return true;
						}
						break;
					case false :
						break;
				}

				if ( $error !== '' ) {
					echo $error;
				}

				$out->addHTML( $render->renderCustomQuery() );

				return true;
		}

		// Render Main page

		$out->addHTML(
			$this->setResourcesAndMenu(
				$render,
				0
			)
		);

		// Do we have any results here then show them
		if ( false !== $showAnyMessage ) {
			$out->addHTML( $showAnyMessage );
		}

		// Render file consistency check failed
		if ( PSConverter::checkFileConsistency() === false ) {
			$numberOfBadFiles = PSConverter::checkFileConsistency( true );
			$btn_backup       = '<form method="post" action="' . $wgScript . '/Special:WSps?action=backup">';
			$btn_backup       .= '<input type="hidden" name="wsps-action" value="wsps-backup">';
			$btn_backup       .= '<input type="submit" class="uk-button uk-button-primary uk-margin-small-bottom uk-text-small" value="';
			$btn_backup       .= wfMessage( 'wsps-error_file_consistency_btn_backup' )->text();
			$btn_backup       .= '"></form>';
			$btn_convert      = '<form method="post" action="' . $wgScript . '/Special:WSps?action=convert">';
			$btn_convert      .= '<input type="hidden" name="wsps-action" value="wsps-convert">';
			$btn_convert      .= '<input type="submit" class="uk-button uk-button-primary uk-margin-small-bottom uk-text-small" value="';
			$btn_convert      .= wfMessage( 'wsps-error_file_consistency_btn_convert' )->text();
			$btn_convert      .= '"></form>';
			$out->addHTML(
				$render->renderCard(
					$this->msg( 'wsps-error_file_consistency_0' ),
					$this->msg( 'wsps-error_file_consistency_1' ),
					'<p>' . $this->msg( 'wsps-error_file_consistency_2' ) . '<br>' . $this->msg(
						'wsps-error_file_consistency_count',
						$numberOfBadFiles
					) . '<br>' . $this->msg( 'wsps-error_file_consistency_3' ) . '<br>' . $this->msg(
						'wsps-error_file_consistency_4'
					),
					'<table><tr><td>' . $btn_backup . '</td><td>' . $btn_convert . '</td></tr></table>'
				)
			);
			$out->addHTML( $style );

			return true;
		}
		if ( PSConverter::checkFileConsistency2() === false ) {
			// Preview files affected
			$out->addHTML('<p>Please use maintenance script with --convert-2-version-2 first</p>' );
			return true;
		}

		// Render default main page

		$data = PSCore::getAllPageInfo();
		$nr   = count( $data );
		$html = wfMessage(
			'wsps-special_count',
			$nr
		)->text();
		if ( $nr >= 1 ) {
			$html .= $render->renderIndexPage(
				$data,
				$wgScript
			);
		}
		$out->addHTML( '<h3>' . $this->msg( 'wsps-content' ) . '</h3>' );
		$out->addHTML( $style );
		$out->addHTML( $html );

		return true;
	}
}
