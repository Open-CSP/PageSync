<?php

namespace PageSync\API;

use ApiBase;
use ApiUsageException;
use MediaWiki\MediaWikiServices;
use PageSync\Core\PSConfig;
use PageSync\Core\PSCore;
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
		if ( !$action || $action === null ) {
			$this->dieWithError( 'missingparam' );
		}

		// Need to have sufficient user rights to proceed...
		$groups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserGroups( $user );

		if ( !in_array(
			'sysop',
			$groups
		) ) {
			$this->dieWithError( 'badaccess-group0' );
		}

		$pageId = $params['pageId'];
		if ( isset( $params['tags'] ) ) {
			$tags = $params['tags'];
		} else {
			$tags = false;
		}
		$userName = $user->getName();
		PSCore::setConfig();
		if ( PSConfig::$config === false ) {
			$output['status']  = wfMessage( 'wsps-api-error-no-config-title' )->text();
			$output['message'] = wfMessage( 'wsps-api-error-no-config-body' )->text();
			$this->getResult()->addValue( null,
										  $this->getModuleName(),
										  array( 'result' => $output ) );

			return true;
		}

		switch ( $action ) {
			case "add" :
				$result = PSCore::addFileForExport(
					$pageId,
					$userName
				);
				$output = $this->setOutput( $result );
				break;
			case "remove" :
				$result = PSCore::removeFileForExport(
					$pageId,
					$userName
				);
				$output = $this->setOutput( $result );
				break;
			case "updatetags" :
				if ( $tags !== false ) {
					$result = PSCore::updateTags(
						$pageId,
						$tags,
						$userName
					);
					$output = $this->setOutput( $result );
				}
				break;
			case "gettags" :
				$result = PSCore::getTagsFromPage(
					$pageId
				);
				$allTags = PSCore::getAllTags();
				$ret = [ 'pagetags' => $result, 'alltags' => $allTags ];
				if ( empty( $result ) ) {
					$output = $this->setOutput( [ 'status' => false, 'info' => $ret ], true );
				} else {
					$output = $this->setOutput( [ 'status' => true, 'info' => $ret ], true );
				}
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
	private function setOutput( array $result, $tags = false ) : array {
		$output = [];
		if ( $result['status'] === true ) {
			$output['status'] = "ok";
			if ( !$tags ) {
				$output['page'] = $result['info'];
			} else {
				$output['tags'] = $result['info'];
			}
		} else {
			if ( !$tags ) {
				$output['message'] = $result['info'];
			} else {
				$output['tags'] = $result['info'];
			}
			$output['status'] = "error";
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
			],
			'tags'   => [
				ParamValidator::PARAM_TYPE     => 'string',
				ParamValidator::PARAM_REQUIRED => false
			]

		];
	}
// $tags = self::getTagsFromPage( $articleId );
	/**
	 * @return string[]
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() : array {
		return [
			'action=pagesync&what=add&pageId=666'       => 'apihelp-wsps-example-1',
			'action=pagesync&what=remove&pageId=666'    => 'apihelp-wsps-example-2',
			'action=pagesync&what=gettags&pageId=666'    => 'apihelp-wsps-example-3',
			'action=pagesync&what=updatetags&pageId=666' => 'apihelp-wsps-example-4'
		];
	}
}
