<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * WSps API module
 *
 * @file
 * @ingroup API
 */
class ApiWSps extends ApiBase {
	/**
	 * @return bool
	 * @throws ApiUsageException
	 * @throws Exception
	 */
	public function execute() : bool {
		$user   = $this->getUser();
		$params = $this->extractRequestParams();
		$action = $params['what'];

		// If the "what" param isn't present, we don't know what to do!
		if ( ! $action || $action === null ) {
			$this->dieWithError( 'missingparam' );
		}

		// Need to have sufficient user rights to proceed...
		$groups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserGroups( $user );

		if ( ! in_array(
			'sysop',
			$groups
		) ) {
			$this->dieWithError( 'badaccess-group0' );
		}

		$pageId = $params['pageId'];
		$userName = $user->getName();

		WSpsHooks::setConfig();
		if ( WSpsHooks::$config === false ) {
			$output['status']  = wfMessage( 'wsps-api-error-no-config-title' )->text();
			$output['message'] = wfMessage( 'wsps-api-error-no-config-body' )->text();
			$this->getResult()->addValue( null,
										  $this->getModuleName(),
										  array( 'result' => $output ) );

			return true;
		}

		switch ( $action ) {
			case "add" :
				$result = WSpsHooks::addFileForExport(
					$pageId,
					$userName
				);
				$output = $this->setOutput( $result );
				break;
			case "remove" :
				$result = WSpsHooks::removeFileForExport(
					$pageId,
					$userName
				);
				$output = $this->setOutput( $result );
				break;
			default :
				$this->dieWithError( 'No recognized action' );
		}

		// Top level
		$this->getResult()->addValue( null,
									  $this->getModuleName(),
									  [ 'result' => $output ] );

		return true;
	}

	/**
	 * @param array $result
	 *
	 * @return array
	 */
	private function setOutput( array $result ) : array {
		$output = [];
		if ( $result['status'] === true ) {
			$output['status'] = "ok";
			$output['page']   = $result['info'];
		} else {
			$output['status']  = "error";
			$output['message'] = $result['info'];
		}

		return $output;
	}

	/**
	 * @return string
	 */
	public function needsToken() : string {
		return "csrf";
	}

	/**
	 * @return bool
	 */
	public function isWriteMode() : bool {
		return true;
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() : array {
		return [
			'what'   => [
				ParamValidator::PARAM_TYPE     => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'pageId' => [
				ParamValidator::PARAM_TYPE     => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			],
			'user'   => [
				ParamValidator::PARAM_TYPE     => 'string',
				ParamValidator::PARAM_REQUIRED => true
			]

		];
	}

	/**
	 * @return string[]
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() : array {
		return [
			'action=pagesync&what=add&pageId=666'    => 'apihelp-wsps-example-1',
			'action=pagesync&what=remove&pageId=666' => 'apihelp-wsps-example-2'
		];
	}
}
