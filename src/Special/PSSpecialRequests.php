<?php
/**
 * Created by  : Wikibase Solutions B.V.
 * Project     : MWWSForm
 * Filename    : PSSpecialRequests.php
 * Description :
 * Date        : 12-6-2024
 * Time        : 14:54
 */

namespace PageSync\Special;

use ApiMain;
use DerivativeRequest;
use WebRequest;

class PSSpecialRequests {

	/**
	 * @param WebRequest $request
	 * @param array $action
	 *
	 * @return array|mixed|null
	 */
	public function makeRequest( WebRequest $request, array $action ) {
		$api = new ApiMain(
			new DerivativeRequest(
				$request,
				// Fallback upon $wgRequest if you can't access context
				$action,
				true
			),
			false
		);
		$api->execute();
		return $api->getResult()->getResultData();
	}
}