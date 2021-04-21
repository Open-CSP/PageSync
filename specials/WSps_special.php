<?php
/**
 * Overview for the WSps extension
 *
 * @file
 * @ingroup Extensions
 */


/**
 * Class WSpsSpecial
 */
class WSpsSpecial extends SpecialPage {

	public $url, $version, $logo, $assets;

	/**
	 * WSpsSpecial constructor.
	 */
	public function __construct() {
		parent::__construct( 'WSps' );
	}


	/**
	 * Special page group
	 * @return string
	 */
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
	public function getPost( string $name, $checkIfEmpty = true ) {
		if ( $checkIfEmpty ) {
			if ( isset( $_POST[ $name ] ) && ! empty( $_POST[ $name ] ) ) {
				return $_POST[ $name ];
			} else {
				return false;
			}
		}
		if ( isset( $_POST[ $name ] ) ) {
			return $_POST[ $name ];
		} else {
			return false;
		}

	}

	/**
	 * @param string|false $query
	 * @param bool $returnUnFiltered
	 *
	 * @return array|false|mixed
	 */
	public function doAsk( $query = false, $returnUnFiltered = false ) {
		if( $query === false ) {
			$query = '[[Class::Managed item]] [[Status of managed item::Live]] |link=none |sep=<br> |limit=9999';
		}
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
	 */
	public function execute( $sub ) {
		global $IP, $wgScript, $wgUser;
		$out    = $this->getOutput();
		$usr    = $wgUser->getName();
		$groups = $wgUser->getGroups();
		if ( ! in_array( 'sysop', $groups ) ) {
			$out->addHTML( '<p>Nothing to see here, only interesting stuff for Admins</p>' );
			return true;
		}
		WSpsHooks::setConfig();

		if ( WSpsHooks::$config === false ) {
			$out->addHTML( '<p>' . wfMessage( 'wsps-api-error-no-config-body' )->text() . '</p>' );
			return true;
		}

		include( $IP . '/extensions/WSPageSync/assets/classes/render.class.php' );

		$render = new render();

		$this->url      = rtrim( $wgScript, 'index.php' );
		$this->version  = \ExtensionRegistry::getInstance()->getAllThings()["WSPageSync"]["version"];
		$this->logo     = '/extensions/WSPageSync/assets/images/wspagesync.png';
		$this->assets   = '/extensions/WSPageSync/assets/images/';
		$style          = $render->getStyle( $this->assets );

		$this->setHeaders();
		$out->setPageTitle('');



		// SMW Custom query
		if ( isset( $_GET['action'] ) && $_GET['action'] === strtolower( "exportcustom" ) ) {
			echo $render->loadResources();
			$pAction = $this->getPost( 'wsps-action' );
			$error   = '';

			switch ( $pAction ) {

				case "wsps-import-query" :
					$query = $this->getPost( 'wsps-query' );

					echo $render->loadResources();
					if ( $query === false ) {
						$error = $this->makeAlert( wfMessage( 'wsps-special_managed_query_not_found' )->text() );
					} else {
						$query       = base64_decode( $query );
						$listOfPages = $this->doAsk( $query );
						echo '<style>.container { display:ruby; }</style>';
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
						echo $status;
						echo $card;
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
					echo $render->loadResources();
					if ( $query === false ) {
						$error = $this->makeAlert( wfMessage( 'wsps-special_custom_query_not_found' )->text() );
					} else {
						$result = $this->doAsk( $query );

						$nr = count( $result );
						$out->addHTML( $render->renderMenu( $this->url, $this->logo, $this->version, 3 ) );
						$form  = $render->renderDoQueryForm( $query );
						$html = $form;
						$bodyResult  = $render->renderDoQueryBody( $result );
						$html .= $bodyResult['html'];

						$header = wfMessage( 'wsps-special_custom_query_result' )->text();
						$header .= '<p>' . wfMessage( 'wsps-special_custom_query' )->text() . '<span class="uk-text-warning">' . $query . '</span></p>';
						$header .= wfMessage( 'wsps-special_custom_query_result_text1', $nr )->text();
						$header .= wfMessage( 'wsps-special_custom_query_result_text2', $bodyResult['active'] )->text();
						$html   = $header . $html;
						$html   .= $form . '</div>';
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
			$out->addHTML( $render->loadResources() );
			$out->addHTML( '<div class="uk-container"><div style="height:450px;">' );
			$out->addHTML( $render->renderCustomQuery() );
			$out->addHTML( '</div></div>' );

			return true;
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === strtolower( "delete" ) ) {
			echo $render->loadResources();
			$pAction = $this->getPost( 'wsps-action' );
			$error   = '';

			if ( $pAction === "wsps-delete" ) {
				$files = WSpsHooks::getFileIndex();
				echo '<style>.container { display:ruby; }</style>';
				echo '<div class="uk-container"><div>';
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
				echo $status;
				echo $card;

				echo '</div></div>';
				$count   = 0;
				$success = 0;
				$fail    = 0;
				foreach ( $files as $file => $title ) {
					$f      = WSpsHooks::$config['exportPath'] . $file . '.wiki';
					$f2     = WSpsHooks::$config['exportPath'] . $file . '.info';
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
				unlink( WSpsHooks::$config['filePath'] . 'export.index' );

				$render->progress(
					$count,
					$count,
					$nr,
					wfMessage( 'wsps-special_delete_card_result', $success, $fail )->text()
				);
				WSpsHooks::getFileIndex();

				return true;

			}

			$data = WSpsHooks::getAllPageInfo();
			$nr   = count( $data );
			echo '<div class="uk-container"><div style="height:450px;">';
			$content = wfMessage( 'wsps-special_delete_card_current', $nr )->text();
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
			echo $card;
			echo '</div></div>';

			return true;
		}

		global $wgScript;

		$out->addHTML( $render->loadResources() );
		$out->addHTML( $render->renderMenu( $this->url, $this->logo, $this->version, 0 ) );
		$data = WSpsHooks::getAllPageInfo();
		$nr   = count( $data );
		$html = wfMessage( 'wsps-special_count', $nr )->text();
		$html .= $render->renderIndexPage( $data, $wgScript );
		$out->addHTML( '<h3>' . $this->msg( 'wsps-content' ) . '</h3>' );
		$out->addHTML( $style );
		$out->addHTML( $html );

		return true;

	}
}
