<?php

/**
 * WSps API module
 *
 * @file
 * @ingroup API
 */
class ApiWSps extends ApiBase {
	/**
	 * Main entry point.
	 */
	public function execute() {
		$user   = $this->getUser();
		$params = $this->extractRequestParams();
		$action = $params['what'];

		// If the "what" param isn't present, we don't know what to do!
		if ( ! $action || $action === null ) {
			$this->dieUsageMsg( 'missingparam' );
		}

		// Need to have sufficient user rights to proceed...
		$groups = $user->getGroups();

		if ( ! in_array( 'sysop', $groups ) ) {
			$this->dieUsageMsg( 'badaccess-group0' );
		}

		$pageId   = $params['pageId'];
		$userName = $params['user'];

		WSpsHooks::setConfig();
		if ( WSpsHooks::$config === false ) {
			$output['status']  = wfMessage( 'wsps-api-error-no-config-title' )->text();
			$output['message'] = wfMessage( 'wsps-api-error-no-config-body' )->text();
			$this->getResult()->addValue( null, $this->getModuleName(),
				array( 'result' => $output )
			);

			return true;
		}

		switch ( $action ) {
			case "add" :
				//$output = WSpsHooks::getPageTitle( $pageId );

				$result = WSpsHooks::addFileForExport( $pageId, $userName );
				$output = array();
				if ( $result['status'] === true ) {
					$output['status'] = "ok";
					$output['page']   = $result['info'];
				} else {
					$output['status']  = "error";
					$output['message'] = $result['info'];
				}

				break;
			case "remove" :
				$result = WSpsHooks::removeFileForExport( $pageId, $userName );
				$output = array();
				if ( $result['status'] === true ) {
					$output['status'] = "ok";
					$output['page']   = $result['info'];
				} else {
					$output['status']  = "error";
					$output['message'] = $result['info'];
				}
				break;
			default :
				$this->dieUsageMsg( 'No recognized action' );
		}


		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			array( 'result' => $output )
		);

		return true;
	}

	public function needsToken() {
		return "csrf";
	}

	public function isWriteMode() {
		return true;
	}


	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return array(
			'what'   => array(
				ApiBase::PARAM_TYPE     => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'pageId' => array(
				ApiBase::PARAM_TYPE     => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
			'user'   => array(
				ApiBase::PARAM_TYPE     => 'string',
				ApiBase::PARAM_REQUIRED => true
			)

		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=wspagesync&what=add&pageId=666'    => 'apihelp-wsps-example-1',
			'action=wspagesync&what=remove&pageId=666' => 'apihelp-wsps-example-2'
		);
	}
}
