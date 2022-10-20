<?php
/**
 * Created by  : Wikibase Solution
 * Project     : i
 * Filename    : render.class.php
 * Description :
 * Date        : 25/01/2019
 * Time        : 22:13
 */

namespace PageSync\Helpers;

use PageSync\Core\PSCore;
use PageSync\Core\PSNameSpaceUtils;

class PSRender {

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
		$resources = '<link rel="stylesheet" href="' . $dir . 'css/uikit.min.css" />';
		$resources .= '<script src="' . $dir . 'js/uikit.min.js"></script>';
		$resources .= '<script src="' . $dir . 'js/uikit-icons.min.js"></script>';
		$resources .= '<link rel="stylesheet" href="' . $dir . 'css/select2.min.css" />';
		return $resources;
	}

	/**
	 * @param string $name
	 *
	 * @return false|string
	 */
	public function getTemplate( string $name ) {
		global $IP;
		$file = $IP . '/extensions/PageSync/assets/templates/' . $name . '.html';
		if ( file_exists( $file ) ) {
			return file_get_contents( $file );
		} else {
			return "";
		}
	}

	/**
	 * @param string $query
	 * @param bool $addTagsOption
	 *
	 * @return string
	 */
	public function renderDoQueryForm( string $query, bool $addTagsOption = false ): string {
		global $IP;
		$form = '<form method="post" class="uk-form-horizontal">';
		$form .= '<input type="hidden" name="wsps-action" value="wsps-import-query">';
		$form .= '<input type="hidden" name="wsps-query" value="' . base64_encode( $query ) . '">';
		if ( $addTagsOption ) {
			$form       .= '<div class="uk-margin uk-align-right"><label class="uk-form-label" for="ps-tags">';
			$form .= wfMessage( 'wsps-special_custom_query_add_tags' )->text();
			$form .= '</label>';
			$form .= '<div class="uk-form-controls">';
			$form       .= '<select id="ps-tags" class="uk-width-1-4" name="tags[]" multiple="multiple" >';
			$tags       = PSCore::getAllTags();
			foreach ( $tags as $tag ) {
				$form .= '<option value="' . $tag . '">' . $tag . '</option>';
			}
			$form .= '</select></div></div>';
			$form .= '<script>' . file_get_contents( $IP . '/extensions/PageSync/assets/js/loadSelect2.js' ) . '</script>';
		}
		$form .= '<input type="submit" class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-bottom uk-text-large" value="' . wfMessage(
				'wsps-special_custom_query_add_results'
			)->text() . '">';
		$form .= '</form>';

		return $form;
	}

	/**
	 * @param array $pageInfo
	 * @param bool $renderBottom
	 *
	 * @return string
	 */
	public function renderEditEntry( array $pageInfo, bool $renderBottom = false ): string {
		global $wgScript, $IP;

		// https://nw-wsform.wikibase.nl/index.php/Special:WSps?action=share
		//https://nw-wsform.wikibase.nl/index.php/Special:WSps?action=edit
		if ( !$renderBottom ) {
			$html       = '<form method="post" action="' . $wgScript . '/Special:WSps?action=pedit">';
			$html       .= '<input type="hidden" name="wsps-action" value="wsps-edit-information">';
			$html       .= '<input type="hidden" name="id" value="' . $pageInfo['pageid'] . '">';
			$description = '';
			if ( isset( $pageInfo['description'] ) ) {
				$description = $pageInfo['description'];
			}
			if ( isset( $pageInfo['tags'] ) ) {
				$tagsFile = explode( ',', $pageInfo['tags'] );
			} else {
				$tagsFile = [];
			}
			$html       .= '<label class="uk-form-label">Description</label>';
			$html       .= '<textarea class="uk-textarea uk-width-1-1" rows="5" name="description">' . $description . '</textarea>';
			$html       .= '<label class="uk-form-label">Tags</label>';
			$html       .= '<select id="ps-tags" class="uk-with-1-1" name="tags[]" multiple="multiple" >';
			$tags       = PSCore::getAllTags();
			foreach ( $tags as $tag ) {
				if ( !empty( $tag ) && in_array( $tag, $tagsFile ) ) {
					$html .= '<option selected="selected" value="' . $tag . '">' . $tag . '</option>';
				} else {
					$html .= '<option value="' . $tag . '">' . $tag . '</option>';
				}
			}
			$html .= '</select>';
			$html .= '<script>' . file_get_contents( $IP . '/extensions/PageSync/assets/js/loadSelect2.js' ) . '</script>';
		} else {
			$html = '<input type="submit" class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-bottom uk-text-large" value="' . wfMessage(
					'wsps-special_table_header_edit'
				)->text() . '">';
			$html .= '</form>';
		}
		return $html;
	}

	/**
	 * @param array $pages
	 *
	 * @return string
	 */
	public function renderListOfPages( array $pages ): string {
		global $wgScript;
		$html = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_slots' )->text() . '</th>';
		$html .= '<th class="uk-text-center">' . wfMessage( 'wsps-special_table_header_tags' )->text() . '</th>';
		$row  = 1;
		foreach ( $pages as $page ) {

			$html .= '<tr><td class="wsps-td">' . $row . '</td>';
			$title = PSNameSpaceUtils::titleForDisplay( $page['ns'], $page['pagetitle'] );
			$html .= '<td class="wsps-td"><a href="' . $wgScript . '/' . $title . '">' . $title . '</a></td>';
			if ( isset( $page['slots'] ) ) {
				$html .= '<td class="wsps-td">' . $page['slots'] . '</td>';
			} else {
				$html .= '<td class="wsps-td">main</td>';
			}
			if ( isset( $page['tags'] ) ) {
				$tags = explode( ',', $page['tags'] );
			} else {
				$tags = [];
			}
			$htmlTags = '';
			if ( !empty( $tags ) ) {
				if ( is_array( $tags ) ) {
					foreach ( $tags as $tag ) {
						if ( !empty( $tag ) ) {
							$htmlTags .= '<span class="uk-badge uk-text-nowrap">' . $tag . '</span>';
						}
					}
				} else {
					$htmlTags .= '<span class="uk-badge uk-text-nowrap">' . $tags . '</span>';
				}
			}
			$html   .= '<td class="wsps-td uk-text-center">' . $htmlTags . '</td>';
			$html   .= '</tr>';
			$row++;
		}
		$html .= '</table>';

		return $html;
	}

	/**
	 * @param array $data
	 * @param string $wgScript
	 *
	 * @return string
	 */
	function renderIndexPage( array $data, string $wgScript ) : string {
		global $wgScript;
		$formHeader = '<form style="display:inline-block;" method="post" action="' . $wgScript . '/Special:WSps?action=pedit">';
		$html = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_slots' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_user' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_date' )->text() . '</th>';
		$html .= '<th class="uk-text-center">' . wfMessage( 'wsps-special_table_header_tags' )->text() . '</th>';
		$html .= '<th class="uk-text-center">' . wfMessage( 'wsps-special_table_header_edit' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_sync' )->text() . '</th></tr>';
		$row  = 1;
		foreach ( $data as $page ) {
			$title = PSNameSpaceUtils::titleForDisplay( $page['ns'], $page['pagetitle'] );
			$html .= '<tr><td class="wsps-td">' . $row . '</td>';
			$html .= '<td class="wsps-td"><a href="' . $wgScript . '/' . $title . '">' . $title . '</a></td>';
			if ( isset( $page['slots'] ) ) {
				$html .= '<td class="wsps-td">' . $page['slots'] . '</td>';
			} else {
				$html .= '<td class="wsps-td">main</td>';
			}
			$html   .= '<td class="wsps-td">' . $page['username'] . '</td>';
			$html   .= '<td class="wsps-td">' . $page['changed'] . '</td>';

			if ( isset( $page['tags'] ) ) {
				$tags = explode( ',', $page['tags'] );
			} else {
				$tags = [];
			}
			$htmlTags = '';
			if ( !empty( $tags ) ) {
				if ( is_array( $tags ) ) {
					foreach ( $tags as $tag ) {
						if ( !empty( $tag ) ) {
							$htmlTags .= '<span class="uk-badge uk-text-nowrap">' . $tag . '</span>';
						}
					}
				} else {
					$htmlTags .= '<span class="uk-badge uk-text-nowrap">' . $tags . '</span>';
				}
			}
			$html   .= '<td class="wsps-td uk-text-center">' . $htmlTags . '</td>';
			$button = $formHeader . '<input type="hidden" name="wsps-action" value="wsps-edit">';
			$button .= '<input type="hidden" name="id" value="' . $page['pageid'] . '">';
			$button .= '<button style="border:none;" type="submit" class="uk-button uk-button-default"><span class="uk-icon-button" uk-icon="pencil" title="' . wfMessage(
					'wsps-special_table_header_edit'
				)->text() . '"></span></button></form> ';
			$html   .= '<td class="wsps-td uk-text-center">' . $button . '</td>';
			$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $page['pageid'] . '"></a>';
			$html   .= '<td class="wsps-td">' . $button . '</td>';
			$html   .= '</tr>';
			$row++;
		}
		$html .= '</table>';

		return $html;
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	public function renderMarkedFiles( array $data ) : string {
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

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	public function renderBackups( array $data ) : string {
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
	public function renderCustomQuery() : string {
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
	 * @param array $result
	 *
	 * @return array
	 */
	public function renderDoQueryBody( array $result ) : array {
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
			$pageId = PSCore::isTitleInIndex( $page );
			if ( $pageId !== false ) {
				$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $pageId . '"></a>';
				$active++;
			} else {
				$pageId = PSCore::getPageIdFromTitle( $page );
				if ( $pageId === false || $pageId === 0 ) {
					$button = '<span class="uk-badge uk-text-nowrap" style="color:white; background-color:#666;"><strong>N/A</strong></span>';
				} else {
					$button = '<a class="wsps-toggle-special" data-id="' . $pageId . '"></a>';
				}
			}
			$html .= '<td>' . $button . '</td>';
			$html .= '</tr>';
			$row++;
		}
		$html .= '</tbody></table>';

		return [
			'html'   => $html,
			'active' => $active
		];
	}

	/**
	 * @param string $assets
	 *
	 * @return string
	 */
	public function getStyle( string $assets ) : string {
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
	public function renderMenu( string $baseUrl, string $logo, string $version, int $active ) : string {
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
		$search  = [
			'%%baseUrl%%',
			'%%logo%%',
			'%%item1class%%',
			'%%item2class%%',
			'%%item3class%%',
			'%%wsps-special_menu_sync_custom_query%%',
			'%%wsps-special_menu_backup_files%%',
			'%%wsps-special_menu_share_files%%'
		];
		$replace = [
			$baseUrl,
			$logo,
			$item1class,
			$item2class,
			$item3class,
			wfMessage( 'wsps-special_menu_sync_custom_query' )->text(),
			wfMessage( 'wsps-special_menu_backup_files' )->text(),
			wfMessage( 'wsps-special_menu_share_files' )->text()
		];

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

	/**
	 * @param string $title
	 * @param string $subTitle
	 * @param string $body
	 * @param string $footer
	 *
	 * @return string
	 */
	public function renderCard( string $title, string $subTitle, string $body, string $footer = "" ) : string {
		$content = '<div class="uk-card uk-card-default">';
		$content .= '<div class="uk-card-header"><h3 class="uk-card-title uk-margin-remove-bottom">' . $title . '</h3>';
		if ( $subTitle !== "" ) {
			$content .= '<p class="uk-text-meta uk-margin-remove-top">' . $subTitle . '</p>';
		}
		$content .= '</div><div class="uk-card-body uk-padding-remove-top"><p class="uk-text-meta uk-margin-remove-top">' . $body . '</p></div>';
		if ( $footer !== "" ) {
			$content .= '<div class="uk-card-footer"><p>' . $footer . '</p></div>';
		}
		$content .= '</div>';

		return $content;
	}

}
