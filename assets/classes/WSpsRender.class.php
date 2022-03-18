<?php
/**
 * Created by  : Wikibase Solution
 * Project     : i
 * Filename    : render.class.php
 * Description :
 * Date        : 25/01/2019
 * Time        : 22:13
 */

class WSpsRender {


	/**
	 * @return string
	 */
	public function loadResources() : string {
		global $wgScript;
		$url = rtrim(
			$wgScript,
			'index.php'
		);
		$dir = $url . 'extensions/PageSync/assets/';

		return '<link rel="stylesheet" href="' . $dir . 'css/uikit.min.css" /><script src="' . $dir . 'js/uikit.min.js"></script><script src="' . $dir . 'js/uikit-icons.min.js"></script>';
	}

	/**
	 * @param string $name
	 *
	 * @return false|string
	 */
	function getTemplate( string $name ) {
		global $IP;
		$file = $IP . '/extensions/PageSync/assets/templates/' . $name . '.html';
		if ( file_exists( $file ) ) {
			return file_get_contents( $file );
		} else {
			return "";
		}
	}


	/**
	 * @param $query
	 *
	 * @return string
	 */
	function renderDoQueryForm( $query ) {
		$form = '<form method="post">';
		$form .= '<input type="hidden" name="wsps-action" value="wsps-import-query">';
		$form .= '<input type="hidden" name="wsps-query" value="' . base64_encode( $query ) . '">';
		$form .= '<input type="submit" class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-bottom uk-text-large" value="' . wfMessage(
				'wsps-special_custom_query_add_results'
			)->text() . '">';
		$form .= '</form>';

		return $form;
	}

	/**
	 * @param $data
	 * @param string $wgScript
	 *
	 * @return string
	 */
	function renderIndexPage( $data, string $wgScript ) : string {
		$html = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_slots' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_user' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_date' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_sync' )->text() . '</th></tr>';
		$row  = 1;
		foreach ( $data as $page ) {
			$html .= '<tr><td class="wsps-td">' . $row . '</td>';
			$html .= '<td class="wsps-td"><a href="' . $wgScript . '/' . $page['pagetitle'] . '">' . $page['pagetitle'] . '</a></td>';
			if ( isset( $page['slots'] ) ) {
				$html .= '<td class="wsps-td">' . $page['slots'] . '</td>';
			} else {
				$html .= '<td class="wsps-td">main</td>';
			}
			$html   .= '<td class="wsps-td">' . $page['username'] . '</td>';
			$html   .= '<td class="wsps-td">' . $page['changed'] . '</td>';
			$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $page['pageid'] . '"></a>';
			$html   .= '<td class="wsps-td">' . $button . '</td>';
			$html   .= '</tr>';
			$row++;
		}
		$html .= '</table>';

		return $html;
	}

	function renderMarkedFiles( $data ) : string {
		$html = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><thead><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-error_file_consistency_convert_file' )->text(
			) . '</th></tr></thead>';
		$html .= '<tfoot><tr><td colspan="2">' . wfMessage( 'wsps-error_file_not_in_index' )->text(
			) . '</td></tr></tfoot><tbody>';
		$row  = 1;

		foreach ( $data as $markedFile ) {
			$html .= '<tr><td class="wsps-td">' . $row . '</td>';
			$html .= '<td class="wsps-td">' . $markedFile . '</td>';
			$html .= '</tr>';
			$row++;
		}
		$html .= '</tbody></table>';

		return $html;
	}

	function renderBackups( $data ) : string {
		$html = '<table style="width:100%;" class="uk-table uk-table-striped uk-table-hover"><thead><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_backup_name' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_date' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_version' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_delete' )->text() . '</th></tr></thead>';
		$row  = 1;
		if ( empty( $data ) ) { // content_no_backups
			$html .= '</table>';
			$html .= wfMessage( 'wsps-content_no_backups' )->text();
		} else {
			foreach ( $data as $backup ) {
				$html   .= '<tr><td class="wsps-td">' . $row . '</td>';
				$html   .= '<td class="wsps-td"><span uk-icon="icon: album"></span> ' . $backup['file'] . '</td>';
				$html   .= '<td class="wsps-td"><span uk-icon="icon: calendar"></span> ' . $backup['date'] . '</td>';
				$html   .= '<td class="wsps-td">' . $backup['version'] . '</td>';
				$button = '<a class="uk-icon-button wsps-download-backup" uk-icon="download" data-id="' . $backup['file'] . '" title="' . wfMessage(
						'wsps-special_backup_download'
					)->text() . '"></a> ';
				$button .= '<a class="uk-icon-button wsps-delete-backup" uk-icon="ban" data-id="' . $backup['file'] . '" title="' . wfMessage(
						'wsps-special_backup_delete'
					)->text() . '"></a> ';
				$button .= '<a class="uk-icon-button wsps-restore-backup" uk-icon="push" data-id="' . $backup['file'] . '" title="' . wfMessage(
						'wsps-special_backup_restore'
					)->text() . '"></a>';
				$html   .= '<td class="wsps-td">' . $button . '</td>';
				$html   .= '</tr>';
				$row++;
			}
			$html .= '</table>';
		}

		return $html;
	}


	/**
	 * @return string
	 */
	function renderCustomQuery() : string {
		$content = '<h3 class="uk-card-title uk-margin-remove-bottom">' . wfMessage(
				'wsps-special_custom_query_card_header'
			)->text() . '</h3>';
		$content .= '<p class="uk-text-meta uk-margin-remove-top">' . wfMessage(
				'wsps-special_custom_query_card_subheader'
			)->text() . '</p>';
		$content .= '<form method="POST" class="uk-form-horizontal uk-margin-large">';
		$content .= '<input type="hidden" name="wsps-action" value="doQuery">';
		$content .= '<label class="uk-form-label uk-text-medium" for="wsps-query">';
		$content .= wfMessage( 'wsps-special_custom_query_card_label' )->text();
		$content .= '</label>';
		$content .= '<div class="uk-form-controls">';
		$content .= '<input class="uk-input" name="wsps-query" type="text" placeholder="';
		$content .= wfMessage( 'wsps-special_custom_query_card_placeholder' )->text();
		$content .= '">';
		$content .= '</div>';
		$content .= '<input type="submit" class="uk-button uk-button-default" value="';
		$content .= wfMessage( 'wsps-special_custom_query_card_submit' )->text();
		$content .= '"></form>';

		return $content;
	}

	/**
	 * @param $result
	 *
	 * @return array
	 */
	function renderDoQueryBody( $result ) : array {
		$html   = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover">';
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
				$active++;
			} else {
				$pageId = WSpsHooks::getPageIdFromTitle( $page );
				if ( $pageId === false || $pageId === 0 ) {
					$button = '<span class="uk-badge" style="color:white; background-color:#666;"><strong>N/A</strong></span>';
				} else {
					$button = '<a class="wsps-toggle-special" data-id="' . $pageId . '"></a>';
				}
			}
			$html .= '<td>' . $button . '</td>';
			$html .= '</tr>';
			$row++;
		}
		$html .= '</tbody></table>';

		return array(
			'html'   => $html,
			'active' => $active
		);
	}

	/**
	 * @param $assets
	 *
	 * @return string
	 */
	function getStyle( string $assets ) : string {
		return str_replace(
			'%%assets%%',
			$assets,
			$this->getTemplate( 'renderStyle' )
		);
	}

	/**
	 * @param string $baseUrl
	 * @param string $logo
	 * @param string $version
	 * @param int $active
	 *
	 * @return string
	 */
	function renderMenu( string $baseUrl, string $logo, string $version, int $active ) : string {
		$item1class = '';
		$item2class = '';
		$item3class = '';
		if ( $active === 1 ) {
			$item1class = 'uk-active';
		}
		if ( $active === 2 ) {
			$item2class = 'uk-active';
		}
		if ( $active === 3 ) {
			$item3class = 'uk-active';
		}
		$search  = array(
			'%%baseUrl%%',
			'%%logo%%',
			'%%item1class%%',
			'%%item2class%%',
			'%%item3class%%',
			'%%wsps-special_menu_sync_custom_query%%',
			'%%wsps-special_menu_backup_files%%'
		);
		$replace = array(
			$baseUrl,
			$logo,
			$item1class,
			$item2class,
			$item3class,
			wfMessage( 'wsps-special_menu_sync_custom_query' )->text(),
			wfMessage( 'wsps-special_menu_backup_files' )->text()
		);

		$ret = str_replace(
			$search,
			$replace,
			$this->getTemplate( 'renderMenu' )
		);
		$ret .= wfMessage(
			'wsps-special_version',
			$version
		)->text();

		return $ret;
	}

	function renderCard( string $title, string $subTitle, string $body, string $footer ) : string {
		$content = '<div class="uk-card uk-card-default">';
		$content .= '<div class="uk-card-header"><h3 class="uk-card-title uk-margin-remove-bottom">' . $title . '</h3>';
		$content .= '<p class="uk-text-meta uk-margin-remove-top">' . $subTitle . '</p></div>';
		$content .= '<div class="uk-card-body"><p class="uk-text-meta uk-margin-remove-top">' . $body . '</p></div>';
		$content .= '<div class="uk-card-footer"><p>' . $footer . '</p></div>';
		$content .= '</div>';

		return $content;
	}


}