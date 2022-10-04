<?php
/**
 * Created by  : Wikibase Solutions
 * Project     : PageSync
 * Filename    : PSMessageMaker.php
 * Description :
 * Date        : 4-10-2022
 * Time        : 14:29
 */

namespace PageSync\Core;

class PSMessageMaker {

	/**
	 * Helper function to create standardized response
	 *
	 * @param bool|string $type
	 * @param mixed $result
	 *
	 * @return array
	 */
	public static function makeMessage( $type, $result ) : array {
		$data           = [];
		$data['status'] = $type;
		$data['info']   = $result;

		return $data;
	}
}