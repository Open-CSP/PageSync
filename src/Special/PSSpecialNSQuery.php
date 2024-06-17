<?php

namespace PageSync\Special;

use PageSync\Core\PSCore;
use WebRequest;

class PSSpecialNSQuery {

	/**
	 * @param WebRequest $request
	 * @param int $ns
	 * @param string|false $startsWith
	 *
	 * @return false|mixed
	 */
	public function getPagesFromNS( WebRequest $request, int $ns, $startsWith = false ) {
		$query = [
			'action' => 'query',
			'format' => 'json',
			'list' => 'allpages',
			'apnamespace' => $ns,
			'aplimit' => 1000
		];
		if ( $startsWith ) {
			$query['apprefix'] = $startsWith;
		}
		$requester = new PSSpecialRequests();
		$result = $requester->makeRequest( $request, $query );
		if ( !isset( $result['query']['allpages'] ) ) {
			return false;
		}
		return $result['query']['allpages'];
	}

	/**
	 * @param WebRequest $request
	 * @param $usr
	 *
	 * @return string|void
	 */
	public function importQuery( WebRequest $request, $usr ) {
		$query = WSpsSpecial::getPost( 'wsps-query' );
		$tags = WSpsSpecial::getPost( 'tags', false );
		if ( $tags !== false && is_array( $tags ) ) {
			$ntags = implode( ',', $tags );
		} else {
			$ntags = "";
		}
		$pageIDS = WSpsSpecial::getPost( 'psids' );

		//return;

		if ( empty( $pageIDS ) ) {
			$this->error = WSpsSpecial::makeAlert( wfMessage( 'wsps-special_custom_ns_query_no_pages_found' )->text() );
		} else {
			//$nameSpace   = base64_decode( $query );
			$nr = count( $pageIDS );
			$count = 1;
			foreach ( $pageIDS as $pageID ) {
				if ( PSCore::isPageIDInIndex( $pageID ) === false ) {
					$result = PSCore::addFileForExport(
						(int)$pageID,
						$usr,
						$ntags
					);

					$count++;
				}
			}
			$content = '<h2>' . wfMessage( 'wsps-special_status_card_done' )->text() . '</h2>';
			$content .= '<p>Added ' . ( $count - 1 ) . '/' . $nr . ' pages.</p>';
			return $content;
		}
	}

}