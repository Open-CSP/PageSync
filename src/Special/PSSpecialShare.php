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

use PageSync\Core\PSConfig;
use PageSync\Core\PSCore;
use PageSync\Helpers\PSGitHub;
use PageSync\Helpers\PSRender;
use PageSync\Helpers\PSShare;

class PSSpecialShare {


	/**
	 * @param string $userName
	 *
	 * @return string|void
	 */
	public function installShare( string $userName ) {
		$zipFile = WSpsSpecial::getPost( 'tmpfile' );
		$agreed = WSpsSpecial::getPost( 'agreed' );
		if ( $agreed === false ) {
			return WSpsSpecial::makeAlert( 'No agreement found to install Share file' );
		}
		if ( $zipFile === false ) {
			return WSpsSpecial::makeAlert( 'No Share file information found' );
		}
		$zipFile = $zipFile . '.zip';
		global $IP;
		$cmd = 'php ' . $IP . '/extensions/PageSync/maintenance/WSps.maintenance.php';
		$cmd .= ' --user="' . $userName . '"';
		$cmd .= ' --install-shared-file-from-temp="' . $zipFile . '"';
		$cmd .= ' --summary="Installed via PageSync Special page"';
		$cmd .= ' --special';
		//echo $cmd;

		$result = shell_exec( $cmd );
		//echo $result;
		$res = explode( '|', $result );
		if ( $res[0] === 'ok' ) {
			return WSpsSpecial::makeAlert( $res[1], 'success' );
		}
		if ( $res[0] === 'error' ) {
			return WSpsSpecial::makeAlert( $res[1] );
		}
	}

	/**
	 * @param PSShare $share
	 * @param PSRender $render
	 *
	 * @return string
	 */
	public function showDownloadShareInformation( PSShare $share, PSRender $render ): string {
		$fileUrl = WSpsSpecial::getPost( 'gitfile' );
		if ( $fileUrl === false ) {
			return WSpsSpecial::makeAlert( 'Missing Share Url' );
		}
		$gitHub = new PSGitHub();
		$fileUrl = $gitHub->getRepoUrl() . $fileUrl;

		// First remove any ZIP file in the temp folder
		$store = $share->getExternalZipAndStoreIntemp( $fileUrl );
		$tempPath = PSConfig::$config['tempFilePath'];
		if ( $store !== true ) {
			return WSpsSpecial::makeAlert( $store );
		}
		$fileInfo = [];
		$fileInfo['info'] = $share->getShareFileInfo( $tempPath . basename( $fileUrl ) );
		if ( $fileInfo['info'] === null || !isset( $fileInfo['info']['project'] ) ) {
			return WSpsSpecial::makeAlert( 'Not a PageSync Share file' );
		}

		// First remove any ZIP file in the temp folder
		$store = $share->getExternalZipAndStoreIntemp( $fileUrl );
		if ( $store !== true ) {
			return WSpsSpecial::makeAlert( $store );
		}
		$fileInfo = [];
		$fileInfo['info'] = $share->getShareFileInfo( $tempPath . basename( $fileUrl ) );
		if ( $fileInfo['info'] === null || !isset( $fileInfo['info']['project'] ) ) {
			return WSpsSpecial::makeAlert( 'Not a PageSync Share file' );
		}
		$fileInfo['sharefile'] = str_replace( '.zip', '', basename( $fileUrl ) );
		$fileInfo['file'] = $tempPath . basename( $fileUrl );
		$fileInfo['list'] = $share->getShareFileContent( $tempPath . basename( $fileUrl ) );
		$body = $share->renderShareFileInformation( $fileInfo );
		$footer = $share->renderShareFileInformation( $fileInfo, true );
		return $render->renderCard( 'Install a Shared File', '', $body, $footer );
	}

	/**
	 * @param PSShare $share
	 *
	 * @return string
	 */
	public function deleteShare( PSShare $share ): string {
		$resultDeleteBackup = false;
		$backupFile         = WSpsSpecial::getPost( 'ws-share-file' );
		if ( $backupFile !== false ) {
			$resultDeleteBackup = $share->deleteBackupFile( $backupFile );
		}
		if ( $resultDeleteBackup === true ) {
			$backActionResult = wfMessage(
				'wsps-special_share_delete_file_success',
				$backupFile
			)->text();
		} else {
			$backActionResult = wfMessage(
				'wsps-special_share_delete_file_error',
				$backupFile
			)->text();
		}
		return $backActionResult;
	}

	/**
	 * @param PSShare $share
	 * @param PSRender $render
	 *
	 * @return string
	 */
	public function createShare( PSShare $share, PSRender $render ): string {
		$body = $share->getFormHeader() . $share->renderCreateSelectTagsForm();
		$footer = $share->renderCreateSelectTagsForm( true ) . '</form>';
		return $render->renderCard( wfMessage( 'wsps-content_share' ), "", $body, $footer );
	}

	/**
	 * @param PSShare $share
	 * @param PSRender $render
	 *
	 * @return string
	 */
	public function showInstallShare( PSShare $share, PSRender $render ): string {
		$gitHub = new PSGitHub();
		$body = $share->getFormHeader();
		$body .= $gitHub->renderListofGitHubFiles();
		$footer = $share->renderDownloadUrlForm( true ) . '</form>';
		return $render->renderCard( wfMessage( 'wsps-content_share' ), "", $body, $footer );
	}

	/**
	 * @param PSShare $share
	 * @param PSRender $render
	 *
	 * @return false|string
	 */
	public function selecTags( PSShare $share, PSRender $render ) {
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
	 * @param string $requirements
	 *
	 * @return array
	 */
	public function createRequirements( string $requirements ): array {
		$req = [];
		$t = 0;
		if ( strpos( $requirements, ';' ) ) {
			// We have multiple requirements
			$require = explode( ';', $requirements );
			foreach ( $require as $single ) {
				if ( strpos( $single, ':' ) ) {
					// We also have a version
					$versioned = explode( ':', $single );
					$req[$t]['name'] = $versioned[0];
					$req[$t]['version'] = $versioned[1];
				} else {
					$req[$t]['name'] = $single;
				}
				$t++;
			}
		} elseif ( strpos( $requirements, ':' ) ) {
			// We have a single requirement, but with a version
			$versioned = explode( ':', $requirements );
			$req[$t]['name'] = $versioned[0];
			$req[$t]['version'] = $versioned[1];
		} else {
			$req[$t]['name'] = $requirements;
		}
		return $req;
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
		$requirements = WSpsSpecial::getPost( 'requirements' );
		if ( $requirements !== false ) {
			$requirements = $this->createRequirements( $requirements );
		}
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
		$nfoContent = $share->createNFOFile( $disclaimer, $project, $company, $name, $uname, $requirements );
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