<?php
/**
 * Overview for the WSps extension
 *
 * @file
 * @ingroup Extensions
 */

class WSpsSpecial extends SpecialPage {
	public function __construct() {
		parent::__construct( 'WSps' );
	}


	function getGroupName() {
		return 'Wikibase';
	}

	/**
	 * @param string $text
	 * @param string $type
	 *
	 * @return string
	 */
	function makeAlert( string $text, $type = "danger" ): string {
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
	public function getPost( $name, $checkIfEmpty = true ) {
		if ( $checkIfEmpty ) {
			if ( isset( $_POST[ $name ] ) && ! empty( $_POST[ $name ] ) ) {
				return $_POST[ $name ];
			} else {
				return false;
			}
		} else {
			if ( isset( $_POST[ $name ] ) ) {
				return $_POST[ $name ];
			} else {
				return false;
			}
		}
	}

	/**
	 * @param string|false $query
	 * @param bool $returnUnFiltered
	 *
	 * @return array|false|mixed
	 */
	public function doAsk( $query = false, $returnUnFiltered = false ) {
		if ( $query === false ) {
			$api = new ApiMain(
				new DerivativeRequest(
					$this->getRequest(), // Fallback upon $wgRequest if you can't access context
					array(
						'action' => 'ask',
						'query'  => '[[Class::Managed item]] [[Status of managed item::Live]] |link=none |sep=<br> |limit=9999'
					),
					true // treat this as a POST
				),
				false // not write.
			);
		} else {
			$api = new ApiMain(
				new DerivativeRequest(
					$this->getRequest(), // Fallback upon $wgRequest if you can't access context
					array(
						'action' => 'ask',
						'query'  => $query
					),
					true // treat this as a POST
				),
				false // not write.
			);
		}

		$api->execute();
		$data = $api->getResult()->getResultData();

		if ( isset( $data['query']['results'] ) ) {
			$data = $data['query']['results'];
		} else {
			return false;
		}

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
	 */
	public function execute( $sub ) {
		global $IP;
		global $wgScript, $wgUser;
		$out    = $this->getOutput();
		$usr    = $wgUser->getName();
		$groups = $wgUser->getGroups();
		if ( ! in_array( 'sysop', $groups ) ) {
			$out->addHTML( '<p>Nothing to see here, only interesting stuff for Admins</p>' );

			return;

		}
		WSpsHooks::setConfig();

		if ( WSpsHooks::$config === false ) {
			$out->addHTML( '<p>' . wfMessage( 'wsps-api-error-no-config-body' )->text() . '</p>' );

			return;
		}

		$url           = rtrim( $wgScript, 'index.php' );
		$extensionFile = $IP . '/extensions/WSPageSync/extension.json';
		if ( file_exists( $extensionFile ) ) {
			$extension = json_decode( file_get_contents( $extensionFile ), true );
			$version   = $extension['version'];
		} else {
			$version = 'N/A';
		}
		$logo     = '/extensions/WSPageSync/assets/images/wspagesync.png';
		$assets   = '/extensions/WSPageSync/assets/images/';
		$filePath = $IP . '/extensions/WSPageSync/files/';
		$style    = "<style>";
		$style    .= '.wsps-td {
	        font-size:10px;
	        padding:5px;
	    }';
		$style    .= '.wsps-toggle-special {
            width : 22px;
            height: 12px;
            display:inline-block;
            vertical-align:middle;
            background-image:url(' . $assets . 'off.png);
            background-size:cover;
        }';
		$style    .= '.wsps-active {
        background-image:url(' . $assets . 'on.png);   
        }';
		$style    .= '</style>';

		$this->setHeaders();
		//$out->setPageTitle( $this->msg( 'wsps-title' ) );
		$out->setPageTitle( '' );
		include( $IP . '/extensions/WSPageSync/assets/classes/render.class.php' );
		$render = new render();


		// SMW Custom query
		if ( isset( $_GET['action'] ) && $_GET['action'] === strtolower( "exportcustom" ) ) {
			echo $render->loadResources();
			$pAction = $this->getPost( 'wsps-action' );
			$error   = '';

			switch ( $pAction ) {

				case "wsps-import-query" :
					$query = $this->getPost( 'wsps-query' );

					//echo "<HR><HR><HR><HR>";
					//var_dump( $query );
					if ( $query === false ) {
						$error = $this->makeAlert( wfMessage( 'wsps-special_managed_query_not_found' )->text() );
					} else {
						$listOfPages = $this->doAsk( $query );
						echo '<div class="uk-container"><div style="height:450px;">';
						//$title, $subTitle, $content, $footer, $width='-1-1', $type="default"
						$nr      = count( $listOfPages );
						$content = $render->drawProgress( $nr );
						$footer  = wfMessage( 'wsps-special_managed_query_card_footer' )->text();
						$card    = $render->renderCard(
							wfMessage( 'wsps-special_managed_query_card_header' )->text(),
							wfMessage( 'wsps-special_managed_query_card_subheader' )->text(),
							$content,
							$footer );
						$status  = $render->renderStatusCard( wfMessage( 'wsps-special_status_card' )->text(), '' );
						//$out->addHTML( $card );
						echo $status;
						echo $card;

						//$out->addHTML( '</div></div>' );
						echo '</div></div>';
						$count = 1;
						foreach ( $listOfPages as $page ) {
							$pageId = WSpsHooks::getPageIdFromTitle( $page );
							if ( $pageId === false ) {
								$render->statusUpdate( $page . wfMessage( 'wsps-special_status_card_failed' )->text(), true, 'warning' );
							} else {
								$result = WSpsHooks::addFileForExport( $pageId, $usr );
								if ( $result['status'] === false ) {
									$render->statusUpdate( $page . ': ' . $result['info'], true, 'warning' );
								}
							}
							$render->progress( $count, $count, $nr, $extraInfo = $page );
							$count ++;
						}
						$render->progress( $count, $count, $nr, wfMessage( 'wsps-special_status_card_done' )->text() );

						return;
					}
					break;
				case "doQuery" :
					$query = $this->getPost( 'wsps-query' );
					//echo "<HR><HR><HR><HR>";
					//var_dump( $query );
					if ( $query === false ) {
						$error = $this->makeAlert( wfMessage( 'wsps-special_custom_query_not_found' )->text() );
					} else {
						$result = $this->doAsk( $query );

						$nr = count( $result );
						$out->addHTML( $render->renderMenu( $url, $logo, $version, 3 ) );
						$form = '<form method="post">';
						$form .= '<input type="hidden" name="wsps-action" value="wsps-import-query">';
						$form .= '<input type="hidden" name="wsps-query" value="' . $query . '">';
						$form .= '<input type="submit" class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-bottom uk-text-large" value="' . wfMessage( 'wsps-special_custom_query_add_results' )->text() . '">';

						$form   .= '</form>';
						$html   = $form;
						$html   .= '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover">';
						$html   .= '<thead><tr><th>#</th>';
						$html   .= '<th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
						$html   .= '<th class="uk-table-shrink">' . wfMessage( 'wsps-special_table_header_sync' )->text() . '</th>';
						$html   .= '</tr></thead><tbody>';
						$row    = 1;
						$active = 0;
						foreach ( $result as $page ) {
							$html   .= '<tr><td>' . $row . '</td>';
							$html   .= '<td><a href="/' . $page . '">' . $page . '</a></td>';
							$pageId = WSpsHooks::isTitleInIndex( $page );
							if ( $pageId !== false ) {
								$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $pageId . '"></a>';
								$active ++;
							} else {
								$pageId = WSpsHooks::getPageIdFromTitle( $page );
								if ( $pageId === false || $pageId === 0 ) {
									$button = '<span class="uk-badge" style="color:white; background-color:#666;"><strong>N/A</strong></span>';
								} else {
									$button = '<a class="wsps-toggle-special" data-id="' . $pageId . '"></a>';
								}
							}
							//echo "<HR>".$pageId;
							$html .= '<td>' . $button . '</td>';
							$html .= '</tr>';
							$row ++;
						}
						$html   .= '</tbody></table>';
						$header = wfMessage( 'wsps-special_custom_query_result' )->text();
						$header .= '<p>' . wfMessage( 'wsps-special_custom_query' )->text() . '<span class="uk-text-warning">' . $query . '</span></p>';
						$header .= wfMessage( 'wsps-special_custom_query_result_text1', $nr )->text();
						$header .= wfMessage( 'wsps-special_custom_query_result_text2', $active )->text();
						$html   = $header . $html;
						$html   .= $form . '</div>';
						$out->addHTML( $style );
						$out->addHTML( $html );

						return;


						//echo "<pre>";
						//print_r($result);
						//echo "</pre>";
					}

					break;
				case false :
					break;

			}

			//error_reporting( E_ALL );
			//ini_set( 'display_errors', 1 );
			//$out->addHTML( $render->renderMenu($url, $logo, $version, 2) );
			// $out->addHTML( '<div style="height:450px;">' );
			if ( $error !== '' ) {
				echo $error;
			}
			echo '<div class="uk-container"><div style="height:450px;">';

			//$title, $subTitle, $content, $footer, $width='-1-1', $type="default"
			$content = '<form method="POST" class="uk-form-horizontal uk-margin-large"><div class="uk-margin">';
			$content .= '<input type="hidden" name="wsps-action" value="doQuery">';
			$content .= '<label class="uk-form-label uk-text-large" for="wsps-query">';
			$content .= wfMessage( 'wsps-special_custom_query_card_label' )->text();
			$content .= '</label>';
			$content .= '<div class="uk-form-controls">';
			$content .= '<input class="uk-input" name="wsps-query" type="text" placeholder="';
			$content .= wfMessage( 'wsps-special_custom_query_card_placeholder' )->text();
			$content .= '">';
			$content .= '</div>';
			$footer  = '<input type="submit" class="uk-button uk-button-default" value="';
			$footer  .= wfMessage( 'wsps-special_custom_query_card_submit' )->text();
			$footer  .= '"></form>';
			$card    = $render->renderCard(
				wfMessage( 'wsps-special_custom_query_card_header' )->text(),
				wfMessage( 'wsps-special_custom_query_card_subheader' )->text(),
				$content,
				$footer
			);
			//$out->addHTML( $card );
			echo $card;
			//$out->addHTML( '</div></div>' );
			echo '</div></div>';

			return;
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === strtolower( "delete" ) ) {
			echo $render->loadResources();
			$pAction = $this->getPost( 'wsps-action' );
			$error   = '';

			if ( $pAction === "wsps-delete" ) {
				$files = WSpsHooks::getFileIndex();
				echo '<div class="uk-container"><div style="height:450px;">';
				$nr      = count( $files );
				$content = $render->drawProgress( $nr );
				$footer  = 'Deleting..';
				$card    = $render->renderCard(
					wfMessage( 'wsps-special_delete_card_header' )->text(),
					wfMessage( 'wsps-special_delete_card_subheader' )->text(),
					$content,
					$footer
				);
				$status  = $render->renderStatusCard( wfMessage( 'wsps-special_status_card' )->text(), '' );
				//$out->addHTML( $card );
				echo $status;
				echo $card;

				//$out->addHTML( '</div></div>' );
				echo '</div></div>';
				$count   = 0;
				$success = 0;
				$fail    = 0;
				foreach ( $files as $file => $title ) {
					$f      = $filePath . '/export/' . $file . '.wiki';
					$f2     = $filePath . '/export/' . $file . '.info';
					$status = unlink( $f );
					if ( $status === false ) {
						$render->statusUpdate( $f . wfMessage( 'wsps-special_delete_failed' )->text(), true, 'warning' );
						$fail ++;
					} else {
						$success ++;
					}
					$status = unlink( $f2 );
					if ( $status === false ) {
						$render->statusUpdate( $f2 . wfMessage( 'wsps-special_delete_failed' )->text(), true, 'warning' );
						$fail ++;
					} else {
						$success ++;
					}
					$render->progress( $count, $count, $nr, wfMessage( 'wsps-special_delete_card_deleting' )->text() . $title );
					$count ++;
				}
				unlink( $filePath . 'export.index' );

				$render->progress(
					$count,
					$count,
					$nr,
					wfMessage( 'wsps-special_delete_card_result', $success, $fail )->text()
				);
				WSpsHooks::getFileIndex();

				return;

			}

			$data = WSpsHooks::getAllPageInfo();
			$nr   = count( $data );
			echo '<div class="uk-container"><div style="height:450px;">';
			//$title, $subTitle, $content, $footer, $width='-1-1', $type="default"
			$content = wfMessage( 'wsps-special_delete_card_header', $nr )->text();
			$form    = '<form method="post">';
			$form    .= '<input type="hidden" name="wsps-action" value="wsps-delete">';
			$form    .= '<input type="submit" class="uk-button uk-button-primary uk-margin-small-bottom uk-text-large" value="';
			$form    .= wfMessage( 'wsps-special_delete_card_click_to_delete' )->text();
			$form    .= '">';

			$form   .= '</form>';
			$footer = $form;
			$card   = $render->renderCard(
				wfMessage( 'wsps-special_delete_card_header' )->text(),
				wfMessage( 'wsps-special_delete_card_subheader' )->text(),
				$content,
				$footer
			);
			//$out->addHTML( $card );
			echo $card;
			//$out->addHTML( '</div></div>' );
			echo '</div></div>';

			return;
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === strtolower( "importmanaged" ) ) {
			echo $render->loadResources();
			//error_reporting( E_ALL );
			//ini_set( 'display_errors', 1 );
			//$out->addHTML( $render->renderMenu($url, $logo, $version, 2) );
			// $out->addHTML( '<div style="height:450px;">' );
			echo '<div class="uk-container"><div style="height:450px;">';
			//$title, $subTitle, $content, $footer, $width='-1-1', $type="default"
			$listOfPages = $this->doAsk();
			$nr          = count( $listOfPages );
			$content     = wfMessage( 'wsps-special_managed_card_current', $nr )->text();
			$footer      = '<a href="' . $url . 'index.php/Special:WSps?action=importmanaged2">';
			$footer      .= wfMessage( 'wsps-special_managed_card_click_to_start' )->text();
			$footer      .= '</a>';
			$card        = $render->renderCard(
				wfMessage( 'wsps-special_managed_query_card_header' )->text(),
				wfMessage( 'wsps-special_managed_query_card_subheader' )->text(),
				$content,
				$footer );
			//$out->addHTML( $card );
			echo $card;
			//$out->addHTML( '</div></div>' );
			echo '</div></div>';

			return;
		}
		if ( isset( $_GET['action'] ) && $_GET['action'] === strtolower( "importmanaged2" ) ) {
			echo $render->loadResources();
			//error_reporting( E_ALL );
			//ini_set( 'display_errors', 1 );
			//$out->addHTML( $render->renderMenu($url, $logo, $version, 2) );
			// $out->addHTML( '<div style="height:450px;">' );
			echo '<div class="uk-container"><div style="height:450px;">';
			//$title, $subTitle, $content, $footer, $width='-1-1', $type="default"
			$listOfPages = $this->doAsk();
			$nr          = count( $listOfPages );
			$content     = $render->drawProgress( $nr );
			$footer      = wfMessage( 'wsps-special_managed_query_card_footer' )->text();
			$card        = $render->renderCard(
				wfMessage( 'wsps-special_managed_query_card_header' )->text(),
				wfMessage( 'wsps-special_managed_query_card_subheader' )->text(),
				$content,
				$footer
			);
			$status      = $render->renderStatusCard( wfMessage( 'wsps-special_status_card' )->text(), '' );
			//$out->addHTML( $card );
			echo $status;
			echo $card;

			//$out->addHTML( '</div></div>' );
			echo '</div></div>';
			$count = 0;
			foreach ( $listOfPages as $page ) {
				$pageId = WSpsHooks::getPageIdFromTitle( $page );
				if ( $pageId === false ) {
					$render->statusUpdate( $page . wfMessage( 'wsps-special_delete_failed' )->text(), true, 'warning' );
				} else {
					$result = WSpsHooks::addFileForExport( $pageId, $usr );
					if ( $result['status'] === false ) {
						$render->statusUpdate( $page . ': ' . $result['info'], true, 'warning' );
					}
				}
				$render->progress( $count, $count, $nr, $extraInfo = $page );
				$count ++;
			}
			$render->progress( $count, $count, $nr, wfMessage( 'wsps-special_status_card_done' )->text() );

			return;
		}
		//$out->addHTML('<img src="'.$logo.'"><br>Version ' . $version . '<br><br>');


		if ( isset( $_GET['action'] ) && $_GET['action'] === strtolower( "listmanaged" ) ) {
			$out->addHTML( $render->loadResources() );
			$out->addHTML( $render->renderMenu( $url, $logo, $version, 1 ) );

			$style       .= "<style>";
			$style       .= '.wsps-td {
	        font-size:12px;
	        padding:5px;
	    }';
			$style       .= '</style>';
			$listOfPages = $this->doAsk();
			$nr          = count( $listOfPages );
			$html        = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr><th>#</th>';
			$html        .= '<th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
			$html        .= '<th>' . wfMessage( 'wsps-special_table_header_sync' )->text() . '</th></tr>';
			$row         = 1;
			$active      = 0;
			foreach ( $listOfPages as $page ) {
				$html   .= '<tr><td class="wsps-td">' . $row . '</td>';
				$html   .= '<td class="wsps-td"><a href="/' . $page . '">' . $page . '</a></td>';
				$pageId = WSpsHooks::isTitleInIndex( $page );
				if ( $pageId !== false ) {
					$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $pageId . '"></a>';
					$active ++;
				} else {
					$pageId = WSpsHooks::getPageIdFromTitle( $page );
					$button = '<a class="wsps-toggle-special" data-id="' . $pageId . '"></a>';
				}
				//echo "<HR>".$pageId;
				$html .= '<td class="wsps-td">' . $button . '</td>';
				$html .= '</tr>';
				$row ++;
			}
			$html .= '</table>';

			$html = wfMessage( 'wsps-special_managed_card_current_which', $nr, $active )->text() . $html;
			$html .= '</div>';

			$out->addHTML( $style );
			$out->addHTML( $html );
			//echo "<pre>";
			//print_r($data);
			//echo "</pre>";
			return;
		}
		$out->addHTML( $render->loadResources() );
		$out->addHTML( $render->renderMenu( $url, $logo, $version, 0 ) );
		$data = WSpsHooks::getAllPageInfo();
		$nr   = count( $data );
		$html = wfMessage( 'wsps-special_count', $nr )->text();
		$html .= '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_user' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_date' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_sync' )->text() . '</th></tr>';
		$row  = 1;
		foreach ( $data as $page ) {
			$html   .= '<tr><td class="wsps-td">' . $row . '</td>';
			$html   .= '<td class="wsps-td"><a href="/' . $page['pagetitle'] . '">' . $page['pagetitle'] . '</a></td>';
			$html   .= '<td class="wsps-td">' . $page['username'] . '</td>';
			$html   .= '<td class="wsps-td">' . $page['changed'] . '</td>';
			$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $page['pageid'] . '"></a>';
			$html   .= '<td class="wsps-td">' . $button . '</td>';
			$html   .= '</tr>';
			$row ++;
		}
		$html .= '</table>';
		$out->addHTML( '<h3>' . $this->msg( 'wsps-content' ) . '</h3>' );
		$out->addHTML( $style );
		$out->addHTML( $html );

		return;

	}
}
