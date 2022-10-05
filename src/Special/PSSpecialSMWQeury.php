<?php

namespace PageSync\Special;

use ApiMain;
use DerivativeRequest;
use PageSync\Core\PSCore;
use WebRequest;

class PSSpecialSMWQeury {

	public $error = "";

	/**
	 * @param WebRequest $request
	 * @param mixed $query
	 * @param bool $returnUnFiltered
	 *
	 * @return array|false|mixed
	 */
	public function doAsk( WebRequest $request, $query = false, bool $returnUnFiltered = false ) {
		if ( $query === false ) {
			$query = '[[Class::Managed item]] [[Status of managed item::Live]] |link=none |sep=<br> |limit=9999';
		}
		$api = new ApiMain(
			new DerivativeRequest(
				$request,
				// Fallback upon $wgRequest if you can't access context
				[
					'action' => 'ask',
					'query'  => $query
				],
				true // treat this as a POST
			),
			false // not write.
		);
		$api->execute();
		$data = $api->getResult()->getResultData();
		if ( !isset( $data['query']['results'] ) ) {
			return false;
		}

		$data = $data['query']['results'];

		if ( !$returnUnFiltered ) {
			$listOfPages = [];
			foreach ( $data as $page ) {
				$listOfPages[] = $page['fulltext'];
			}
			sort( $listOfPages );

			return $listOfPages;
		} else {
			return $data;
		}
	}

	public function importQuery( WebRequest $request, $usr ) {
		$query = WSpsSpecial::getPost( 'wsps-query' );
		$tags = WSpsSpecial::getPost( 'tags', false );
		if ( $tags !== false && is_array( $tags ) ) {
			$ntags = implode( ',', $tags );
		} else {
			$ntags = "";
		}

		if ( $query === false ) {
			$this->error = WSpsSpecial::makeAlert( wfMessage( 'wsps-special_managed_query_not_found' )->text() );
		} else {
			$query       = base64_decode( $query );
			$listOfPages = $this->doAsk( $request, $query );
			$nr          = count( $listOfPages );
			$count       = 1;
			foreach ( $listOfPages as $page ) {
				if ( PSCore::isTitleInIndex( $page ) === false ) {
					$pageId = PSCore::getPageIdFromTitle( $page );
					if ( is_int( $pageId ) ) {
						$result = PSCore::addFileForExport(
							$pageId,
							$usr,
							$ntags
						);
					}
					$count++;
				}
			}
			$content = '<h2>' . wfMessage( 'wsps-special_status_card_done' )->text() . '</h2>';
			$content .= '<p>Added ' . ( $count - 1 ) . '/' . $nr . ' pages.</p>';
			return $content;
		}
	}
}
