<?php
/**
 * Created by  : Wikibase Solution
 * Project     : csp
 * Filename    : WSpsConvertHandler.php
 * Description :
 * Date        : 19-11-2021
 * Time        : 09:27
 */

namespace PageSync\Handlers;

use PageSync\Core\PSConverter;
use PageSync\Core\PSCore;
use PageSync\Helpers\PSRender;

class WSpsConvertHandler {

	/**
	 * @param PSRender $render
	 *
	 * @return string
	 */
	public function convertForReal( PSRender $render ) : string {
		$result = PSConverter::convertFilesTov0999();

		return $render->renderCard(
			wfMessage( 'wsps-error_file_consistency_page_2_header' )->text(),
			wfMessage( 'wsps-error_file_consistency_page_2_subheader' )->text(),
			'<p>' . wfMessage(
				'wsps-error_file_consistency_result_total',
				$result['total']
			)->text() . '<br>' . wfMessage(
				'wsps-error_file_consistency_result_converted',
				$result['converted']
			)->text() . '</p>',
			''
		);
	}

	/**
	 * @param PSRender $render
	 *
	 * @return string
	 */
	public function preview( PSRender $render ) : string {
		global $wgScript;
		$markedFiles = PSConverter::checkFileConsistency(
			false,
			true
		);

		foreach ( $markedFiles as $k => $mFile ) {
			$result = PSCore::isTitleInIndex( $mFile );
			if ( !$result ) {
				$markedFiles[$k] .= ' *';
			}
		}

		$table       = $render->renderMarkedFiles(
			$markedFiles
		);
		$btn_backup  = '<form method="post" action="' . $wgScript . '/Special:WSps?action=backup">';
		$btn_backup  .= '<input type="hidden" name="wsps-action" value="wsps-backup">';
		$btn_backup  .= '<input type="submit" class="uk-button uk-button-primary uk-margin-small-bottom uk-text-small"';
		$btn_backup  .= ' value="' . wfMessage( 'wsps-error_file_consistency_btn_backup' )->text();
		$btn_backup  .= '"></form>';
		$btn_convert = '<form method="post" action="' . $wgScript . '/Special:WSps?action=convert">';
		$btn_convert .= '<input type="hidden" name="wsps-action" value="wsps-convert-real">';
		$btn_convert .= '<input type="submit" class="uk-button uk-button-primary uk-margin-small-bottom uk-text-small"';
		$btn_convert .= ' value="' . wfMessage( 'wsps-error_file_consistency_btn_convert_real' )->text();
		$btn_convert .= '"></form>';

		return $render->renderCard(
			wfMessage( 'wsps-error_file_consistency_page_2_header' )->text(),
			wfMessage( 'wsps-error_file_consistency_page_2_subheader' )->text(),
			$table,
			'<table><tr><td>' . $btn_backup . '</td><td>' . $btn_convert . '</td></tr></table>'
		);
	}

}
