<?php
/**
 * Overview for the WSps extension
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

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
	public function makeAlert( string $text, string $type = "danger" ) : string {
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
	public function getPost( string $name, bool $checkIfEmpty = true ) {
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
	public function getGet( string $name, bool $checkIfEmpty = true ) {
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
	 * @param WSpsRender $render
	 * @param int $activeTab
	 *
	 * @return string
	 */
	private function setResourcesAndMenu( WSpsRender $render, int $activeTab ) : string {
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
		if ( ! isset( $data['query']['results'] ) ) {
			return false;
		}

		$data = $data['query']['results'];

		if ( ! $returnUnFiltered ) {
			$listOfPages = array();
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
		global $IP, $wgScript, $wgUser;
		$out            = $this->getOutput();
		$usr            = $wgUser->getName();
		$groups         = $wgUser->getGroups();
		$showAnyMessage = false;
		WSpsHooks::setConfig();

		if ( WSpsHooks::$config === false ) {
			$out->addHTML( '<p>' . wfMessage( 'wsps-api-error-no-config-body' )->text() . '</p>' );

			return true;
		}
		if ( empty( array_intersect(
			WSpsHooks::$config['allowedGroups'],
			MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $wgUser )
		) ) ) {
			$out->addHTML( '<p>Nothing to see here, only interesting stuff for Admins</p>' );

			return true;
		}


		include( $IP . '/extensions/PageSync/assets/classes/WSpsRender.class.php' );

		$render = new WSpsRender();

		$this->url     = str_replace(
			'index.php',
			'',
			$wgScript
		);
		$this->version = \ExtensionRegistry::getInstance()->getAllThings()["PageSync"]["version"];
		$this->logo    = '/extensions/PageSync/assets/images/pagesync.png';
		$this->assets  = '/extensions/PageSync/assets/images/';
		$style         = $render->getStyle( $this->assets );

		$wspsAction = $this->getGet( 'action' );

		// First handle serving backup file for download, before we output anything
		if ( false !== $wspsAction && strtolower( $wspsAction ) === 'backup' ) {
			$pAction = $this->getPost( 'wsps-action' );
			if ( $pAction === 'download-backup' ) {
				$backupHandler = new WSpsBackupHandler();
				$backupHandler->setBackFile( $this->getPost( 'ws-backup-file' ) );
				$backupHandler->downloadBackup();
			}
		}

		// First handle serving share file for download, before we output anything
		if ( false !== $wspsAction && strtolower( $wspsAction ) === 'share' ) {
			$pAction = $this->getPost( 'wsps-action' );
			if ( $pAction === 'download-share' ) {
				$backupHandler = new WSpsShareHandler();
				$backupHandler->setShareFile( $this->getPost( 'ws-share-file' ) );
				$backupHandler->downloadShare();
			}
		}


		$this->setHeaders();
		$out->setPageTitle( '' );

		switch ( strtolower( $wspsAction ) ) {
			case "pedit":

				$pAction = $this->getPost( 'wsps-action' );
				switch ( $pAction ) {
					case "wsps-edit-information":
						$description = $this->getPost( 'description', false );
						$tags = $this->getPost( 'tags', false );
						$pageId = $this->getPost( 'id' );
						if ( $pageId === false ) {
							break;
						}
						if ( $description === false ) {
							$description = '';
						}
						$pagePath = WSpsHooks::getInfoFileFromPageID( $pageId );
						if ( $pagePath['status'] === false ) {
							$out->addHTML( $pagePath['info'] );
							break;
						}
						if ( $tags === false ) {
							$tags = [];
						}
						$result = WSpsHooks::updateInfoFile( $pagePath['info'], $description, implode( ',', $tags ) );
						if ( $result['status'] === false ) {
							$out->addHTML( $pagePath['info'] );
							break;
						}
						break;

					case "wsps-edit":
						$pageId = $this->getPost( 'id' );
						if ( $pageId !== false ) {
							$pagePath = WSpsHooks::getInfoFileFromPageID( $pageId );
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
							$pageInfo = json_decode(
								file_get_contents( $pagePath['info'] ),
								true
							);

							$body   = $render->renderEditEntry( $pageInfo );
							$title  = WSpsHooks::getPageTitle( $pageId );
							$footer = $render->renderEditEntry(
								$pageInfo,
								true
							);
							$out->addHTML(
								$render->renderCard(
									$this->msg( 'wsps-special_table_header_edit' ),
									$title,
									$body,
									$footer
								)
							);

							return true;
							break;
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
				if ( WSpsHooks::checkFileConsistency() === false ) {
					$pAction = $this->getPost( 'wsps-action' );

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
				break;
			case "share":
				$out->addHTML(
					$this->setResourcesAndMenu(
						$render,
						3
					)
				);
				if ( ! extension_loaded( 'zip' ) ) {
					$out->addHTML(
						$this->makeAlert( wfMessage( 'wsps-special_backup_we_need_zip_extension' )->text() )
					);
					$out->addHTML( $style );

					return true;
				}
				$share = new PSShare();
				//Handle any backup actions
				$backActionResult = '';
				$pAction = $this->getPost( 'wsps-action' );
				switch ( $pAction ) {
					case "wsps-do-download-install":
						$zipFile = $this->getPost( 'tmpfile' );
						$agreed = $this->getPost( 'agreed' );
						if ( $agreed === false ) {
							$out->addHTML( $this->makeAlert( 'No agreement found to install Share file' ) );
							break;
						}
						if ( $zipFile === false ) {
							$out->addHTML( $this->makeAlert( 'No Share file information found' ) );
							break;
						}
						global $IP;
						$userName = $this->getUser()->getName();
						$cmd = 'php ' . $IP . '/extensions/PageSync/maintenance/WSps.maintenance.php';
						$cmd .= ' --user="' . $userName.'"';
						$cmd .= ' --install-shared-file-from-temp="' . $zipFile . '"';
						$cmd .= ' --summary="Installed via PageSync Special page"';
						$cmd .= ' --special';
						//echo $cmd;

						$result = shell_exec( $cmd );
						//echo $result;
						$res = explode( '|', $result );
						if ( $res[0] === 'ok' ) {
							$out->addHTML( $this->makeAlert( $res[1], 'success' ) );
						}
						if ( $res[0] === 'error' ) {
							$out->addHTML( $this->makeAlert( $res[1] ) );
						}

						break;
					case "wsps-share-downloadurl":
						$fileUrl = $this->getPost( 'url' );

						if ( $fileUrl === false ) {
							$out->addHTML( $this->makeAlert( 'Missing Share Url' ) );
							break;
						}
						$tempPath = WSpsHooks::$config['tempFilePath'];
						// First remove any ZIP file in the temp folder
						$store = $share->getExternalZipAndStoreIntemp( $fileUrl );
						if ( $store !== true ) {
							$out->addHTML( $this->makeAlert( $store ) );
							break;
						}
						$fileInfo = [];
						$fileInfo['info'] = $share->getShareFileInfo( $tempPath . basename( $fileUrl ) );
						$fileInfo['file'] = $tempPath . basename( $fileUrl );
						$fileInfo['list'] = $share->getShareFileContent( $tempPath . basename( $fileUrl ) );
						$body = $share->renderShareFileInformation( $fileInfo );
						$footer = $share->renderShareFileInformation( $fileInfo, true );
						$out->addHTML( $render->renderCard( 'Install a Shared File', '', $body, $footer ) );
						return true;

						break;
					case "delete-share":
						$resultDeleteBackup = false;
						$backupFile         = $this->getPost( 'ws-share-file' );
						if ( $backupFile !== false ) {
							$resultDeleteBackup = $share->deleteBackupFile( $backupFile );
						}
						if ( $resultDeleteBackup === true ) {
							$backActionResult = wfMessage(
								'wsps-special_share_delete_file_success',
								$backupFile
							)->text();
						} else {
							$backActionResult = wfMessage(
								'wsps-special_share_delete_file_error',
								$backupFile
							)->text();
						}
						break;
					case "wsps-share-docancel":
						break;
					case "wsps-share-doshare":
						$project = $this->getPost( 'project' );
						$company = $this->getPost( 'company' );
						$name = $this->getPost( 'name' );
						$disclaimer = $this->getPost( 'disclaimer' );
						$uname = $usr;
						$tagType = $this->getPost( 'wsps-type' );
						$tags = $this->getPost( 'wsps-tags' );
						if ( $tags === false || $tagType === false || $disclaimer === false ) {
							$out->addHTML( $this->makeAlert( 'Missing elements' ) );
							break;
						}
						$tags = explode( ',', base64_decode( $tags ) );
						$pages = [];
						switch ( base64_decode( $tagType ) ) {
							case "ignore":
								$pages = WSpsHooks::getAllPageInfo();
								break;
							case "all":
								$pages = $share->returnPagesWithAllTage( $tags );
								break;
							case "one":
								$pages = $share->returnPagesWithAtLeastOneTag( $tags );
								break;
							default:
								$out->addHTML( $this->makeAlert( 'No type select recognized' ) );
								break;
						}
						if ( empty( $pages ) ) {
							break;
						}
						$nfoContent = $share->createNFOFile( $disclaimer, $project, $company, $name, $uname );
						if ( $res = $share->createShareFile( $pages, $nfoContent ) !== true ) {
							$out->addHTML( $res );
						} else {
							$out->addHTML( '<h3>Following files have been added</h3>' );
							$out->addHTML( $render->renderListOfPages( $pages ) );
						}
						break;
					case "wsps-share-select-tags":
						$tags = $this->getPost( "tags", false );
						$type = $this->getPost( "wsps-select-type", true );
						$query = $this->getPost( 'wsps-query' );
						/* REMOVED FEATURE
						if( $query !== false ) {
							$result = $this->doAsk( $query );

							$nr = count( $result );

							$form       = $render->renderDoQueryForm( $query );
							$html       = $form;
							$bodyResult = $render->renderDoQueryBody( $result );
							$html       .= $bodyResult['html'];
							$out->addHTML( $html );
							return true;
						}
						*/
						if ( $tags === false && $type !== 'ignore' ) {
							$out->addHTML( 'No tags selected' );
							break;
						}
						$pages = [];
						switch ( $type ) {
							case "ignore":
								$pages = WSpsHooks::getAllPageInfo();
								break;
							case "all":
								$pages = $share->returnPagesWithAllTage( $tags );
								break;
							case "one":
								$pages = $share->returnPagesWithAtLeastOneTag( $tags );
								break;
							default:
								$out->addHTML( $this->makeAlert( 'No type select recognized' ) );
								break;
						}
						if ( empty( $pages ) ) {
							break;
						}
						$body = $render->renderListOfPages( $pages );
						$data = [ 'tags' => implode( ',', $tags ), 'type' => $type ];
						$body .= $share->getFormHeader( false ) . $share->agreeSelectionShareFooter( 'body', $data );
						if ( count( $pages ) === 1 ) {
							$title = count( $pages ) . " page to be shared";
						} else {
							$title = count( $pages ) . " pages to be shared";
						}
						$footer = $share->agreeSelectionShareFooter( 'agreebtn' );
						$footer .= '</form>';
						$footer .= $share->agreeSelectionShareFooter( 'cancelbtn' );
						$out->addHTML( $render->renderCard( $title, "Agree or cancel", $body, $footer ) );
						return true;

						break;
					case "wsps-share-install":
						$body = $share->getFormHeader() . $share->renderDownloadUrlForm();
						$footer = $share->renderDownloadUrlForm( true ) . '</form>';
						$out->addHTML( $render->renderCard( $this->msg( 'wsps-content_share' ),"", $body, $footer ) );
						return true;
						break;
					case "wsps-share-create":
						$body = $share->getFormHeader() . $share->renderCreateSelectTagsForm();
						$footer = $share->renderCreateSelectTagsForm( true ) . '</form>';
						$out->addHTML( $render->renderCard( $this->msg( 'wsps-content_share' ),"", $body, $footer ) );
						return true;
						break;
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
				break;
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
						$this->makeAlert( wfMessage( 'wsps-special_backup_we_need_zip_extension' )->text() )
					);
					$out->addHTML( $style );

					return true;
				}

				//Handle any backup actions
				$pAction = $this->getPost( 'wsps-action' );
				switch ( $pAction ) {
					case "wsps-backup":
						$psBackup->createZipFileBackup();
						break;
					case "delete-backup":
						$resultDeleteBackup = false;
						$backupFile         = $this->getPost( 'ws-backup-file' );
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
						$backupFile       = $this->getPost( 'ws-backup-file' );
						if ( $backupFile !== false ) {
							$resRestore = $psBackup->restoreBackupFile( $backupFile );
							if ( $resRestore === true ) {
								$backActionResult = wfMessage(
									'wsps-special_backup_restore_file_success',
									$backupFile
								)->text();
							} else {
								$backActionResult = wfMessage(
									'wsps-special_backup_restore_file_failure',
									$backupFile
								)->text();
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
				if ( ! ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' ) ) {
					$out->addHTML( $this->makeAlert( wfMessage( 'wsps-special_custom_query_we_need_smw' )->text() ) );
					$out->addHTML( $style );

					return true;
				}

				$pAction = $this->getPost( 'wsps-action' );
				$error   = '';

				switch ( $pAction ) {
					case "wsps-import-query" :
						$query = $this->getPost( 'wsps-query' );
						$tags = $this->getPost( 'tags', false );
						if ( $tags !== false && is_array( $tags ) ) {
							$ntags = implode( ',', $tags );
						}

						if ( $query === false ) {
							$error = $this->makeAlert( wfMessage( 'wsps-special_managed_query_not_found' )->text() );
						} else {
							$query       = base64_decode( $query );
							$listOfPages = $this->doAsk( $query );
							$nr          = count( $listOfPages );
							$count       = 1;
							foreach ( $listOfPages as $page ) {
								if ( WSpsHooks::isTitleInIndex( $page ) === false ) {
									$pageId = WSpsHooks::getPageIdFromTitle( $page );
									if ( is_int( $pageId ) ) {
										$result = WSpsHooks::addFileForExport(
											$pageId,
											$usr,
											$ntags
										);
									}
									$count ++;
								}
							}
							$content = '<h2>' . wfMessage( 'wsps-special_status_card_done' )->text() . '</h2>';
							$content .= '<p>Added ' . ( $count - 1 ) . '/' . $nr . ' pages.</p>';
							$out->addHTML( $content );

							return true;
						}
						break;
					case "doQuery" :
						$query = $this->getPost( 'wsps-query' );

						if ( $query === false ) {
							$error = $this->makeAlert( wfMessage( 'wsps-special_custom_query_not_found' )->text() );
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
		if ( WSpsHooks::checkFileConsistency() === false ) {
			$numberOfBadFiles = WSpsHooks::checkFileConsistency( true );
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

		// Render default main page

		$data = WSpsHooks::getAllPageInfo();
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
