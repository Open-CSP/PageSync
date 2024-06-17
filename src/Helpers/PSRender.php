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

use MediaWiki\MediaWikiServices;
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
		$resources .= '<script src="' . $dir . 'js/FilterTable.js"></script>';
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
	 * @param bool $nsQuery
	 *
	 * @return string
	 */
	public function renderDoQueryForm( string $query, bool $addTagsOption = false, bool $nsQuery = false ): string {
		global $IP;
		$action = "wsps-import-query";
		$id = '';
		if ( $nsQuery ) {
			$action .= '-ns';
			$id = ' id = "PSNSindexTable"';
		}
		$form = '<form' . $id . ' method="post" class="uk-form-horizontal">';
		$form .= '<input type="hidden" name="wsps-action" value="' . $action . '">';
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
		if ( !$nsQuery ) {
			$form .= '</form>';
		}

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
		$html = '<table style="width:100%;" id="PSindexTable" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr>';
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
	 * @param array $nameSpaces
	 *
	 * @return string
	 */
	public function renderNameSpaceQuery( array $nameSpaces ) : string {
		$body = '<form method="POST" class="uk-form-horizontal">';
		$body .= '<input type="hidden" name="wsps-action" value="doQueryNS">';
		$body .= '<label class="uk-form-label uk-text-medium" for="wsps-ns-query">';
		$body .= wfMessage( 'wsps-special_custom_ns_query_card_label' )->text();
		$body .= '</label>';
		$body .= '<div class="uk-form-controls">';
		$body .= '<select class="uk-select" name="wsps-ns-query">';
		foreach ( $nameSpaces as $id => $nameSpace ) {
			$body .= '<option value="' . $id . '">' . $nameSpace . '</option>';
		}
		$body .= '</select></div>';
		$body .= '<label class="uk-form-label uk-text-medium" for="wsps-ns-start">';
		$body .= wfMessage( 'wsps-special_custom_ns_query_card_label_start' )->text();
		$body .= '</label>';
		$body .= '<div class="uk-form-controls">';
		$body .= '<input type="text" class="uk-input" name="wsps-ns-start">';
		$body .= '</div>';
		$footer = '<input type="submit" class="uk-width-1-2 uk-align-center uk-margin-remove-bottom uk-button uk-button-primary" value="';
		$footer .= wfMessage( 'wsps-special_custom_query_card_submit' )->text();
		$footer .= '"></form>';
		return $this->renderCard2(
			wfMessage( 'wsps-special_custom_ns_query_card_header' )->text(),
			wfMessage( 'wsps-special_custom_ns_query_card_subheader' )->text(),
			$body,
			$footer,
			true
		);
	}

	/**
	 * @return string
	 */
	public function renderCustomQuery() : string {
		$body = '<form method="POST" class="uk-form-horizontal">';
		$body .= '<input type="hidden" name="wsps-action" value="doQuery">';
		$body .= '<label class="uk-form-label uk-text-medium" for="wsps-query">';
		$body .= wfMessage( 'wsps-special_custom_query_card_label' )->text();
		$body .= '</label>';
		$body .= '<div class="uk-form-controls">';
		$body .= '<input class="uk-input" name="wsps-query" type="text" placeholder="';
		$body .= wfMessage( 'wsps-special_custom_query_card_placeholder' )->text();
		$body .= '">';
		$body .= '</div>';
		$footer = '<input type="submit" class="uk-width-1-2 uk-align-center uk-margin-remove-bottom uk-button uk-button-primary" value="';
		$footer .= wfMessage( 'wsps-special_custom_query_card_submit' )->text() . '">';
		$footer .= '</form>';
		return $this->renderCard2(
			wfMessage( 'wsps-special_custom_query_card_header' )->text(),
			wfMessage( 'wsps-special_custom_query_card_subheader' )->text(),
			$body,
			$footer,
			true
		);
	}

	/**
	 * @param array $result
	 *
	 * @return array
	 */
	public function renderDoNSQueryBody( array $result ) : array {
		global $wgScript;
		$html   = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover">';
		$html   .= '<thead><tr><th class="uk-table-shrink">#</th>';
		$html   .= '<th class="uk-table-shrink">' . wfMessage( 'wsps-special_custom_ns_query_table_pageid' ) . '</th>';
		$html   .= '<th class="uk-table-expand">' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
		$html   .= '<th class="uk-table-shrink">' . wfMessage( 'wsps-special_table_header_sync' )->text() . '</th>';
		$html   .= '</tr></thead><tbody>';
		$row    = 1;
		$active = 0;
		foreach ( $result as $k => $page ) {
			if ( !isset( $page['pageid'] ) || !isset( $page['title'] ) ) {
				unset( $result[$k] );
				continue;
			}
			$formInput = '<input type="hidden" name="psids[]" value="' . $page['pageid'] . '">';
			$html   .= '<tr><td>' . $row . $formInput . '</td>';

			$html   .= '<td>' . $page['pageid'] . '</td>';
			$html   .= '<td><a target="_blank" href="' . $wgScript . '?title=' . $page['title'] . '">' . $page['title'] . '</a></td>';
			$pageId = PSCore::isTitleInIndex( $page['title'] );
			if ( $pageId !== false ) {
				$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $pageId . '"></a>';
				$active++;
			} else {
				$pageId = $page['pageid'];
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
		$html .= '</tbody></table></form>';

		return [
			'html'   => $html,
			'active' => $active,
			'total' => $row - 1
		];
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
		$item4class = '';
		if ( $active === 1 ) {
			$item1class = 'uk-active';
		}
		if ( $active === 2 ) {
			$item2class = 'uk-active';
		}
		if ( $active === 3 ) {
			$item3class = 'uk-active';
		}
		if ( $active === 4 ) {
			$item4class = 'uk-active';
		}
		$search  = [
			'%%baseUrl%%',
			'%%logo%%',
			'%%item1class%%',
			'%%item2class%%',
			'%%item3class%%',
			'%%item4class%%',
			'%%wsps-special_menu_sync_custom_query%%',
			'%%wsps-special_menu_backup_files%%',
			'%%wsps-special_menu_share_files%%',
			'%%wsps-special_menu_clean-up%%'
		];
		$replace = [
			$baseUrl,
			$logo,
			$item1class,
			$item2class,
			$item3class,
			$item4class,
			wfMessage( 'wsps-special_menu_sync_custom_query' )->text(),
			wfMessage( 'wsps-special_menu_backup_files' )->text(),
			wfMessage( 'wsps-special_menu_share_files' )->text(),
			wfMessage( 'wsps-special_menu_clean-up' )->text()
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
	 * @param bool $small
	 *
	 * @return string
	 */
	public function renderCard2(
		string $title,
		string $subTitle,
		string $body,
		string $footer = "",
		bool $small = false
	) : string {
		if ( $small ) {
			$size = ' uk-card-small';
		} else {
			$size = '';
		}
		$content = '<div><div class="uk-card uk-card-default uk-box-shadow-bottom' . $size . '">';
		$content .= '<div class="uk-card-header uk-background-muted"><h3 class="uk-card-title uk-margin-remove-bottom">' . $title . '</h3>';
		if ( $subTitle !== "" ) {
			$content .= '<p class="uk-text-meta uk-margin-remove-top">' . $subTitle . '</p>';
		}
		$content .= '</div><div class="uk-card-body uk-height-small uk-"><p class="uk-text-meta">' . $body . '</p></div>';
		if ( $footer !== "" ) {
			$content .= '<div class="uk-card-footer uk-background-muted"><p>' . $footer . '</p></div>';
		}
		$content .= '</div></div>';

		return $content;
	}

	/**
	 * @param string $title
	 * @param string $subTitle
	 * @param string $body
	 * @param string $footer
	 * @param bool $small
	 *
	 * @return string
	 */
	public function renderCard(
		string $title,
		string $subTitle,
		string $body,
		string $footer = "",
		bool $small = false
	) : string {
		if ( $small ) {
			$size = ' uk-card-small';
		} else {
			$size = '';
		}
		$content = '<div><div class="uk-card uk-card-default' . $size . '">';
		$content .= '<div class="uk-card-header"><h3 class="uk-card-title uk-margin-remove-bottom">' . $title . '</h3>';
		if ( $subTitle !== "" ) {
			$content .= '<p class="uk-text-meta uk-margin-remove-top">' . $subTitle . '</p>';
		}
		$content .= '</div><div class="uk-card-body"><p class="uk-text-meta">' . $body . '</p></div>';
		if ( $footer !== "" ) {
			$content .= '<div class="uk-card-footer"><p>' . $footer . '</p></div>';
		}
		$content .= '</div></div>';

		return $content;
	}

}
