<?php

namespace PageSync\Helpers;

use function wfMessage;

/**
 * Created by  : Wikibase Solutions
 * Project     : PageSync
 * Filename    : PSGitHub.class.php
 * Description :
 * Date        : 30-9-2022
 * Time        : 21:54
 */
class PSGitHub {

	private const PAGESYNC_SHARED_FILES_REPO = 'https://api.github.com/repos/Open-CSP/PageSync-SharedFiles/contents/';

	public string $error = '';

	/**
	 * @param string $url
	 *
	 * @return bool|string
	 */
	private function get( string $url ) {
		$ch = curl_init();
		curl_setopt(
			$ch,
			CURLOPT_URL,
			$url
		);
		curl_setopt(
			$ch,
			CURLOPT_RETURNTRANSFER,
			true
		);
		curl_setopt(
			$ch,
			CURLOPT_FOLLOWLOCATION,
			true
		);
		curl_setopt(
			$ch,
			CURLOPT_USERAGENT,
			"php/curl"
		);
		$output = curl_exec( $ch );
		$err    = curl_errno( $ch );
		$errMsg = curl_error( $ch );
		curl_close( $ch );
		if ( $err === 0 ) {
			return $output;
		}
		$this->error = $errMsg;

		return false;
	}

	private function splitInfo( $info ) {
		$ret          = [];
		$data         = explode(
			"\n",
			$info
		);
		$ret['title'] = $data[0];
		unset( $data[0] );
		$ret['info'] = implode(
			"\n",
			$data
		);

		return $ret;
	}

	/**
	 * @return array|string
	 */
	public function getFileList() {
		$content = $this->get( self::PAGESYNC_SHARED_FILES_REPO );
		if ( !$content ) {
			return $this->error;
		}
		$content = json_decode(
			$content,
			true
		);

		$lst = [];
		$t   = 0;
		foreach ( $content as $folder ) {
			$folderContent = $this->get( self::PAGESYNC_SHARED_FILES_REPO . $folder['path'] . '/' );
			$folderContent = json_decode(
				$folderContent,
				true
			);
			foreach ( $folderContent as $file ) {
				$parts = pathinfo( $file['name'] );
				if ( $parts['extension'] === 'info' ) {
					$tmpInfo          = $this->splitInfo( file_get_contents( $file['download_url'] ) );
					$lst[$t]['info']  = $tmpInfo['info'];
					$lst[$t]['title'] = $tmpInfo['title'];
					$lst[$t]['name']  = $parts['filename'];
					$lst[$t]['zip']   = str_replace(
						'.info',
						'.zip',
						$file['download_url']
					);
				}
			}
		}

		return $lst;
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	public function renderListofGitHubFiles( array $data ) : string {
		$html = '<input type="hidden" name="wsps-action" value="wsps-share-downloadurl">';
		$html .= '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr>';
		$html .= '<th></th><th>' . wfMessage( 'wsps-special_share_list_name' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_share_list_info' )->text() . '</th></tr>';
		foreach ( $data as $listing ) {
			$html .= '<tr><td class="wsps-td"><input required="required" type="radio" class="uk-radio" name="gitfile" ';
			$html .= 'value = "' . $listing['name'] . '"></td>';
			$html .= '<td class="wsps-td">' . $listing['title'] . '<br><span class="uk-text-meta">' . $listing['name'];
			$html .= '</span></td>';
			$html .= '<td class="wsps-td">' . $listing['info'] . '</td></tr>';
		}
		$html .= '</table>';

		return $html;
	}
}