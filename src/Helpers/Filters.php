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

	/**
	 * @return string
	 */
	public function formSMWQuery(): string {
		return $this->getFormHeader() . '<input type="hidden" name="wsps-clean-smw" />
                <input type="submit" value="Make SMQ Qeury" class="uk-button uk-button-primary uk-width-1-1" />
            </form>';
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

	public function renderCreateSelectTagsForm( bool $returnSubmit = false ) : string {
		global $IP;
		if ( !$returnSubmit ) {
			$selectTagsForm = '<input type="hidden" name="wsps-action" value="wsps-share-select-tags">';
			$selectTagsForm .= '<fieldset class="uk-fieldset uk-margin">';
			$selectTagsForm .= '<legend class="uk-legend">';
			$selectTagsForm .= wfMessage( 'wsps-special_share_choose_tags' )->text() . '</legend>';
			$selectTagsForm .= '<select id="ps-tags" class="uk-with-1-1" name="tags[]" multiple="multiple" >';
			$tags           = PSCore::getAllTags();
			foreach ( $tags as $tag ) {
				if ( !empty( $tag ) ) {
					$selectTagsForm .= '<option value="' . $tag . '">' . $tag . '</option>';
				}
			}
			$selectTagsForm .= '</select>';
			$selectTagsForm .= '<p><input type="radio" id="ws-all" class="uk-radio" name="wsps-select-type" checked="checked" value="all">';
			$selectTagsForm .= ' <label for="ws-all" class="uk-form-label">';
			$selectTagsForm .= wfMessage( 'wsps-special_share_choose_options1' )->text() . '</label><br>';
			$selectTagsForm .= '<input type="radio" id="ws-one" class="uk-radio" name="wsps-select-type" value="one">';
			$selectTagsForm .= ' <label for="ws-one" class="uk-form-label">';
			$selectTagsForm .= wfMessage( 'wsps-special_share_choose_options2' )->text() . '</label><br>';
			$selectTagsForm .= '</p></fieldset>';
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
			$formHeader = $this->formSMWQuery();
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
}
