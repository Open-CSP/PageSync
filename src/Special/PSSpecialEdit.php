<?php

namespace PageSync\Special;

use PageSync\Core\PSCore;
use PageSync\Helpers\PSRender;

class PSSpecialEdit {

	public function editInformation() {
		$description = WSpsSpecial::getPost( 'description', false );
		$tags = WSpsSpecial::getPost( 'tags', false );
		$pageId = WSpsSpecial::getPost( 'id' );
		if ( $pageId === false ) {
			return false;
		}
		if ( $description === false ) {
			$description = '';
		}
		$pagePath = PSCore::getInfoFileFromPageID( $pageId );
		if ( $pagePath['status'] === false ) {
			return $pagePath['info'];
		}
		if ( $tags === false ) {
			$tags = [];
		}
		$result = PSCore::updateInfoFile( $pagePath['info'], $description, implode( ',', $tags ) );
		if ( $result['status'] === false ) {
			return $pagePath['info'];
		}
		return false;
	}

	/**
	 * @param PSRender $render
	 * @param array $pagePath
	 * @param int $pageId
	 *
	 * @return mixed
	 */
	public function edit( PSRender $render, array $pagePath, int $pageId ): string {
		$ret = '';
		$pageInfo = json_decode(
			file_get_contents( $pagePath['info'] ),
			true
		);

		$body   = $render->renderEditEntry( $pageInfo );
		$title  = PSCore::getPageTitle( $pageId );
		$footer = $render->renderEditEntry(
			$pageInfo,
			true
		);
		return $render->renderCard(
				wfMessage( 'wsps-special_table_header_edit' ),
				$title,
				$body,
				$footer
		);
	}
}
