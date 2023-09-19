<?php

namespace PageSync\Helpers;

use PageSync\Core\PSCore;
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


	public function getListFromTitle( $titles ) {
		$data = [];
		foreach( $titles as $page ) {
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
		$searchField .= '<input class="uk-input" type="search" id="filterTableSearch" placeholder="Search synced files">';
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
	 * @param array $tags
	 *
	 * @return string
	 */
	public function renderActionOptions( PSRender $render, array $tags ):string {
		$search  = [
			'%%form-header%%',
			'%%form-delete-tags%%'
		];
		$replace = [
			$this->getFormHeader(),
			$this->renderCreateSelectTagsForm( false, $tags, false, true )
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
			$selectTagsForm .= wfMessage( 'wsps-special_share_choose_tags' )->text() . '</legend>';
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
			'%%form-header2%%',
			'%%tags%%',
			'%%smw-installed%%'
		];
		$replace = [
			$formHeader,
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
