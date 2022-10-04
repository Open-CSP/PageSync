<?php
/**
 * Created by  : Designburo.nl
 * Project     : MWWSForm
 * Filename    : PSSpecialShare.php
 * Description :
 * Date        : 4-10-2022
 * Time        : 19:53
 */

namespace PageSync\Special;

use PageSync\Core\PSCore;
use PageSync\Helpers\PSRender;
use PageSync\Helpers\PSShare;

class PSSpecialShare {


	public function selecTags( PSShare $share, PSRender $render ){
		$tags = WSpsSpecial::getPost( "tags", false );
		$type = WSpsSpecial::getPost( "wsps-select-type", true );
		$query = WSpsSpecial::getPost( 'wsps-query' );
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
			return 'No tags selected';
		}
		switch ( $type ) {
			case "ignore":
				$pages = PSCore::getAllPageInfo();
				break;
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
		return $render->renderCard( $title, "Agree or cancel", $body, $footer );
	}

	/**
	 * @param string $usr
	 * @param PSShare $share
	 * @param PSRender $render
	 *
	 * @return bool|string
	 */
	public function doShare( string $usr, PSShare $share, PSRender $render ) {
		$project = WSpsSpecial::getPost( 'project' );
		$company = WSpsSpecial::getPost( 'company' );
		$name = WSpsSpecial::getPost( 'name' );
		$disclaimer = WSpsSpecial::getPost( 'disclaimer' );
		$uname = $usr;
		$tagType = WSpsSpecial::getPost( 'wsps-type' );
		$tags = WSpsSpecial::getPost( 'wsps-tags' );
		if ( $tags === false || $tagType === false || $disclaimer === false ) {
			return WSpsSpecial::makeAlert( 'Missing elements' );
		}
		$tags = explode( ',', base64_decode( $tags ) );
		switch ( base64_decode( $tagType ) ) {
			case "ignore":
				$pages = PSCore::getAllPageInfo();
				break;
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
		$nfoContent = $share->createNFOFile( $disclaimer, $project, $company, $name, $uname );
		if ( $res = $share->createShareFile( $pages, $nfoContent ) !== true ) {
			return $res;
		} else {
			$ret = '<h3>Following files have been added</h3>';
			$ret .= $render->renderListOfPages( $pages );
			return $ret;
		}
		return false;
	}
}