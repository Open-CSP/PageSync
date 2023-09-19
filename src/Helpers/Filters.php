<?php

namespace PageSync\Helpers;

use PageSync\Core\PSCore;
use PageSync\Core\PSNameSpaceUtils;
use PageSync\Special\PSSpecialSMWQeury;
use PageSync\Special\WSpsSpecial;

class Filters {

	/**
	 * @param bool $inline
	 *
	 * @return string
	 */
	public function getFormHeader( bool $inline = false ) : string {
		global $wgScript;
		if ( $inline ) {
			return '<form style="display:inline-block;" method="post" action="' . $wgScript .
				   '/Special:WSps?action=clean">';
		} else {
			return '<form method="post" action="' . $wgScript . '/Special:WSps?action=clean">';
		}
	}

	/**
	 * @param string $userName
	 *
	 * @return array
	 */
	public function removePagesFromSMW( string $userName ): array {
		$pagesInvolved = [];
		$nr = 0;
		$ids = WSpsSpecial::getPost( 'ids' );
		if ( $ids === false ) {
			return $pagesInvolved;
		}
		$ids = json_decode( base64_decode( $ids ), true );
		foreach ( $ids as $singlePageID ) {
			$pagesInvolved[$nr]['page'] = PSCore::getPageTitle( $singlePageID, true );
			$pagesInvolved[$nr]['tags'] = '';
			$nr++;
			$result = PSCore::removeFileForExport( $singlePageID, $userName );
		}
		return $pagesInvolved;
	}

	/**
	 * @param string $userName
	 *
	 * @return array
	 */
	public function removePagesWithTags( string $userName ): array {
		$nrOfPages = 0;
		$idsToBeRemoved = [];
		$pagesInvolved = [];
		$removedTags = [];
		$tags = $this->getTagsFromPost();
		if ( !$tags ) {
			return $pagesInvolved;
		}
		$allPages = PSCore::getAllPageInfo();
		foreach( $allPages as $page ) {
			$changed = false;
			if ( isset( $page['tags'] ) ) {
				$pTags = explode(
					',',
					$page['tags']
				);
				foreach( $tags as $singleTag ) {
					if ( in_array( $singleTag, $pTags ) ) {
						$idsToBeRemoved[] = $page['pageid'];
						$changed = true;
						$removedTags[] = $singleTag;
					}
				}
			}
			// store page involved
			if ( $changed ) {
				$pagesInvolved[$nrOfPages]['page'] = PSNameSpaceUtils::titleForDisplay( $page['ns'], $page['pagetitle'] );
				$nrOfPages++;
			}
		}
		$idsToBeRemoved = array_unique( $idsToBeRemoved );
		foreach( $idsToBeRemoved as $pageId ) {
			$result = PSCore::removeFileForExport( $pageId, $userName );
		}
		$pagesInvolved['tags'] = array_unique( $removedTags );
		return $pagesInvolved;
	}

	/**
	 * @param string $userName
	 *
	 * @return array
	 */
	public function removeTags( string $userName ): array {
		$nrOfPages = 0;
		$pagesInvolved = [];
		$tags = $this->getTagsFromPost();
		if ( !$tags ) {
			return $pagesInvolved;
		}
		$allPages     = PSCore::getAllPageInfo();
		$allTagsRemoved = [];
		foreach ( $allPages as $k => $page ) {
			$removedTags = [];
			$changed = false;
			// Does the page have tags
			if ( isset( $page['tags'] ) ) {
				// Explode the tags
				$pTags = explode(
					',',
					$page['tags']
				);

				foreach( $tags as $singleTag ) {
					if ( in_array( $singleTag, $pTags ) ) {
						$key = array_search( $singleTag, $pTags );
						if ( $key !== false ) {
							unset ( $pTags[$key] );
						}
						$changed = true;
						$removedTags[] = $singleTag;
						$allTagsRemoved[] = $singleTag;
					}
				}
				// put the tags back
				$page['tags'] = implode( ',', $pTags );

				// update the page with the updated tags if there has been a change
				if ( $changed ) {
					$pagesInvolved[$nrOfPages]['page'] = PSNameSpaceUtils::titleForDisplay( $page['ns'], $page['pagetitle'] );
					$pagesInvolved[$nrOfPages]['tags'] = $removedTags;
					$nrOfPages++;
					PSCore::updateTags( $page['pageid'], $page['tags'],	$userName );
				}
			}
		}
		$pagesInvolved['tags'] = array_unique( $allTagsRemoved );

		return $pagesInvolved;
	}

	/**
	 * @param array $titles
	 *
	 * @return array
	 */
	public function getIDArray( array $titles ): array {
		$data = [];
		foreach ( $titles as $page ) {
			if ( PSCore::isTitleInIndex( $page ) ) {
				$data[] = PSCore::getPageIdFromTitle( $page );
			}
		}
		return $data;
	}

	/**
	 * @param array $titles
	 *
	 * @return array
	 */
	public function getListFromTitle( array $titles ): array {
		$data = [];
		foreach ( $titles as $page ) {
			if ( PSCore::isTitleInIndex( $page ) ) {
				$id = PSCore::getPageIdFromTitle( $page );
				$infoFile = PSCore::getInfoFileFromPageID( $id );
				if ( $infoFile['status'] === true ) {
					$data[] = json_decode( file_get_contents( $infoFile['info'] ),
						true );
				}
			}
		}
		return $data;
	}

	/**
	 * @return string
	 */
	public function javaScriptMainPageFilter(): string {
		$searchField = PHP_EOL . '<div class="uk-inline uk-float-right uk-margin-bottom">';
		$searchField .= '<a class="uk-form-icon uk-form-icon-flip" href="" uk-icon="icon: search"></a>';
		$searchField .= '<input class="uk-input" type="search" id="filterTableSearch" placeholder="';
		$searchField .= wfMessage( 'wsps-special_search_index' ) . '">';
		$searchField .= '</div>' . PHP_EOL;
		$js = "<script>document.getElementById( 'filterTableSearch' ).addEventListener( 'keyup', function() {
		   let search = document.getElementById( 'filterTableSearch' ).value.toUpperCase();
           filterTable( search, 'PSindexTable' );
		} );</script>";
		return $searchField . $js;
	}

	/**
	 * @return false|mixed
	 */
	public function getTagsFromPost() {
		return WSpsSpecial::getPost( "tags", false );
	}

	/**
	 * @return array|false|string
	 */
	public function getTagsList() {
		$tags = $this->getTagsFromPost();
		$type = WSpsSpecial::getPost( "wsps-select-type", true );
		$share = new PSShare();
		if ( $tags === false ) {
			return false;
		}
		switch ( $type ) {
			case "all":
				$pages = $share->returnPagesWithAllTage( $tags );
				break;
			case "one":
				$pages = $share->returnPagesWithAtLeastOneTag( $tags );
				break;
			default:
				return WSpsSpecial::makeAlert( 'No type select recognized' );
				break;
		}
		if ( empty( $pages ) ) {
			return false;
		}
		return $pages;
	}

	/**
	 * @param PSRender $render
	 * @param array $ids
	 *
	 * @return string
	 */
	public function renderSMWOptions( PSRender $render, array $ids ): string {
		$search  = [
			'%%form-header%%',
			'%%wsps-special_clean_page_h3%%',
			'%%wsps-special_clean_smw_intro%%',
			'%%wsps-special_clean_smw_page_submit%%',
			'%%ids%%'
		];
		$replace = [
			$this->getFormHeader(),
			wfMessage( 'wsps-special_clean_page_h3' ),
			wfMessage( 'wsps-special_clean_smw_intro' ),
			wfMessage( 'wsps-special_clean_smw_page_submit' ),
			base64_encode( json_encode( $ids ) )
		];
		return str_replace(
			$search,
			$replace,
			$render->getTemplate( 'renderCleanSMWOptions' )
		);
	}

	/**
	 * @param PSRender $render
	 * @param array $tags
	 *
	 * @return string
	 */
	public function renderActionOptions( PSRender $render, array $tags ):string {
		$search  = [
			'%%form-header%%',
			'%%form-delete-tags%%',
			'%%wsps-special_clean_tags_h3%%',
			'%%wsps-special_clean_tags_tabs_tag%%',
			'%%wsps-special_clean_tags_tabs_page%%',
			'%%wsps-special_clean_tags_tag_intro%%',
			'%%wsps-special_clean_tags_tag_submit%%',
			'%%wsps-special_clean_tags_page_intro%%',
			'%%wsps-special_clean_tags_page_submit%%'
		];
		$replace = [
			$this->getFormHeader(),
			$this->renderCreateSelectTagsForm( false, $tags, false, true ),
			wfMessage( 'wsps-special_clean_tags_h3' ),
			wfMessage( 'wsps-special_clean_tags_tabs_tag' ),
			wfMessage( 'wsps-special_clean_tags_tabs_page' ),
			wfMessage( 'wsps-special_clean_tags_tag_intro' ),
			wfMessage( 'wsps-special_clean_tags_tag_submit' ),
			wfMessage( 'wsps-special_clean_tags_page_intro' ),
			wfMessage( 'wsps-special_clean_tags_page_submit' )
		];

		return str_replace(
			$search,
			$replace,
			$render->getTemplate( 'renderCleanOptions' )
		);
	}

	/**
	 * @param bool $returnSubmit
	 * @param mixed $tags
	 * @param bool $options
	 * @param bool $multiple
	 *
	 * @return string
	 */
	public function renderCreateSelectTagsForm(
		bool $returnSubmit = false,
		$tags = false,
		bool $options = true,
		bool $multiple = true
	) : string {
		global $IP;
		if ( $multiple ) {
			$multiple = ' multiple="multiple"';
		} else {
			$multiple = '';
		}
		if ( ! $returnSubmit ) {
			$selectTagsForm = '<fieldset class="uk-fieldset uk-margin">';
			$selectTagsForm .= '<legend class="uk-legend">';
			$selectTagsForm .= wfMessage( 'wsps-special_clean_tags_to_use' ). '</legend>';
			$selectTagsForm .= '<select id="ps-tags" class="uk-with-1-1" name="tags[]"' . $multiple . ' >';
			if ( $tags === false ) {
				$tags = PSCore::getAllTags();
			}
			foreach ( $tags as $tag ) {
				if ( ! empty( $tag ) ) {
					$selectTagsForm .= '<option value="' . $tag . '">' . $tag . '</option>';
				}
			}
			$selectTagsForm .= '</select>';
			if ( $options ) {
				$selectTagsForm .= '<p><input type="radio" id="ws-all" class="uk-radio" name="wsps-select-type" checked="checked" value="all">';
				$selectTagsForm .= ' <label for="ws-all" class="uk-form-label">';
				$selectTagsForm .= wfMessage( 'wsps-special_share_choose_options1' )->text() . '</label><br>';
				$selectTagsForm .= '<input type="radio" id="ws-one" class="uk-radio" name="wsps-select-type" value="one">';
				$selectTagsForm .= ' <label for="ws-one" class="uk-form-label">';
				$selectTagsForm .= wfMessage( 'wsps-special_share_choose_options2' )->text() . '</label><br>';
				$selectTagsForm .= '</p>';
			}
			$selectTagsForm .= '</fieldset>';
			$selectTagsForm .= '<script>' . file_get_contents(
					$IP . '/extensions/PageSync/assets/js/loadSelect2.js'
				) . '</script>';
		} else {
			$selectTagsForm = '<input type="submit" class="uk-button uk-width-1-1 uk-button-primary" value="';
			$selectTagsForm .= wfMessage( 'wsps-special_share_submit_and_preview' )->text();
			$selectTagsForm .= '">';
		}

		return $selectTagsForm;
	}

	/**
	 * @param array $result
	 * @param bool $includeTags
	 *
	 * @return string
	 */
	public function renderListOfAffectedPages( array $result, bool $includeTags = true ) : string {
		global $wgScript;
		$html = '';
		if ( empty( $result ) ) {
			$html .= '<div class="uk-alert-warning" uk-alert>' . PHP_EOL;
			$html .= '<p>' . wfMessage( 'wsps-special_clean_tags_no_change' ) . '</p></div>';
			return $html;
		}
		if ( isset( $result['tags'] ) ) {
			$html .= '<div class="uk-alert-success" uk-alert>' . PHP_EOL;
			$html .= '<p>' . wfMessage( 'special_clean_tags_changes' ) . '<br><ul>';
			foreach ( $result['tags'] as $tag ) {
				$html .= '<li>' . '<span class="uk-badge uk-text-nowrap">' . $tag . '</span>' . '</li>' . PHP_EOL;
			}
			unset( $result['tags'] );
			$html .= '</ul></p>';
		}
		$nrOfPagesInvolved = count( $result );
		if ( $nrOfPagesInvolved > 1 ) {
			$html .= '<p>' . wfMessage( 'wsps-special_clean_tags_pages_affected_plural', $nrOfPagesInvolved );
			$html .= '</p>';
		} else {
			$html .= '<p>' . $nrOfPagesInvolved . ' ' . wfMessage( 'wsps-special_clean_tags_pages_affected' );
			}$html .= '</p>';
		$html .= '</div>' . PHP_EOL;
		if ( !$includeTags ) {
			$html .= '<div class="uk-section uk-section-default"><div class="uk-container">';
			$html .= '<h3>' . wfMessage( 'wsps-special_clean_tags_pages_removed' ) . '</h3>';
		}
		$html .= '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
		if ( $includeTags ) {
			$html .= '<th class="uk-text-center">' . wfMessage( 'wsps-special_table_header_tags_removed' )->text() . '</th>';
		}
		$row = 1;
		foreach ( $result as $page ) {
			if ( !isset( $page['page'] ) ) {
				continue;
			}
			$html .= '<tr><td class="wsps-td">' . $row . '</td>';
			$html .= '<td class="wsps-td"><a href="' . $wgScript . '/' . $page['page'] . '">' . $page['page'] . '</a></td>';
			if ( $includeTags ) {
				$htmlTags = '';
				$tags = $page['tags'];
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
				$html .= '<td class="wsps-td uk-text-center">' . $htmlTags . '</td>';
			}
			$html .= '</tr>';
			$row++;
		}
		$html .= '</table>';
		if ( !$includeTags ) {
			$html .= '</div></div>';
		}

		return $html;
	}

	/**
	 * @param PSRender $render
	 *
	 * @return string
	 */
	public function renderIndexOptions( PSRender $render ) : string {
		$smwInstalled = '';
		$specialSMW = new PSSpecialSMWQeury();
		if ( !$specialSMW->isExtensionInstalled( 'SemanticMediaWiki' ) ) {
			$smwInstalled = WSpsSpecial::makeAlert( wfMessage( 'wsps-special_custom_query_we_need_smw' )->text() );
			$formHeader = '';
		} else {
			$smwInstalled = $this->renderSMWQeuryForm();
			$formHeader = $this->getFormHeader();
		}
		$search  = [
			'%%form-header%%',
			'%%wsps-special_clean_smw_header%%',
			'%%wsps-special_clean_smw_subheader%%',
			'%%wsps-special_clean_smw_paragraph%%',
			'%%wsps-special_clean_smw_submit%%',
			'%%wsps-special_clean_tag_header%%',
			'%%wsps-special_clean_tag_subheader%%',
			'%%wsps-special_clean_tag_paragraph%%',
			'%%wsps-special_clean_tag_submit%%',
			'%%form-header2%%',
			'%%tags%%',
			'%%smw-installed%%'
		];
		$replace = [
			$formHeader,
			wfMessage( 'wsps-special_clean_smw_header' ),
			wfMessage( 'wsps-special_clean_smw_subheader' ),
			wfMessage( 'wsps-special_clean_smw_paragraph' ),
			wfMessage( 'wsps-special_clean_smw_submit' ),
			wfMessage( 'wsps-special_clean_tag_header' ),
			wfMessage( 'wsps-special_clean_tag_subheader' ),
			wfMessage( 'wsps-special_clean_tag_paragraph' ),
			wfMessage( 'wsps-special_clean_tag_submit' ),
			$this->getFormHeader(),
			$this->renderCreateSelectTagsForm(),
			$smwInstalled
		];

		return str_replace(
			$search,
			$replace,
			$render->getTemplate( 'renderCleanIndex' )
		);
	}

	private function renderSMWQeuryForm() {
		$content = '<label class="uk-form-label uk-text-medium" for="wsps-query">';
		$content .= wfMessage( 'wsps-special_custom_query_card_label' )->text();
		$content .= '</label>';
		$content .= '<div class="uk-form-controls">';
		$content .= '<input class="uk-input" name="wsps-query" type="text" placeholder="';
		$content .= wfMessage( 'wsps-special_custom_query_card_placeholder' )->text();
		$content .= '">';
		$content .= '</div>';
		return $content;
	}
}
