<?php

namespace PageSync\Helpers;

use PageSync\Special\PSSpecialSMWQeury;

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
	private const PAGESYNC_SHARED_FILES_INDEX = 'https://raw.githubusercontent.com/Open-CSP/PageSync-SharedFiles/main/index.json';
	private const PAGESYNC_SHARED_FILES_URL = 'https://raw.githubusercontent.com/Open-CSP/PageSync-SharedFiles/main/';

	public string $error = '';
	private array $index = [];

	/**
	 * @return string
	 */
	public function getRepoUrl(): string {
		return self::PAGESYNC_SHARED_FILES_URL;
	}

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

	/**
	 * @return array
	 */
	public function getIndex(): array {
		return $this->index;
	}

	/**
	 * @return array
	 */
	public function getCategoriesAndSubjects(): array {
		$cats = [];
		foreach ( $this->index['PageSync Share File Index'] as $entry ) {
			$k = $entry['Category'];
			$v = $entry['Subject'];
			$cats[$k][] = $v;
		}
		return $cats;
	}

	/**
	 * @param string $category
	 * @param string $subject
	 *
	 * @return array $lst
	 */
	private function getFilesInfo( string $category, string $subject ): array {
		$lst = [];
		$t = 0;
		foreach ( $this->index['PageSync Share File Index'] as $entry ) {

			if ( $entry['Subject'] === $subject && $entry['Category'] === $category ) {
				$lst[$t] = $entry;
				$t++;
			}
		}
		return $lst;
	}

	/**
	 * @return array|string
	 */
	public function getFileList() {
		$content = $this->get( self::PAGESYNC_SHARED_FILES_INDEX );
		if ( !$content ) {
			return $this->error;
		}
		$this->index = json_decode( $content, true );
		$this->index = $this->index['PageSync Share File Index'];
		return $this->index;
	}

	/**
	 *
	 * @return string
	 */
	public function renderListofGitHubFiles() : string {
		$smw = new PSSpecialSMWQeury();
		$data = $this->getFileList();
		$html = '<input type="hidden" name="wsps-action" value="wsps-share-downloadurl">';
		foreach ( $data as $category=>$subject ) {
			$html .= '<h4 class="uk-heading-bullet uk-margin-remove-top">'. $category .'</h4>';
			foreach ( $subject as $subjectName=>$subjectLst ) {
				$html      .= '<h5 class="uk-heading-line uk-margin-remove-top"><span>' . $subjectName . '</span></h5>';
				$html      .= '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover uk-table-justify"><tr>';
				$html      .= '<th></th><th>' . wfMessage( 'wsps-special_share_list_name' )->text() . '</th>';
				$html      .= '<th>' . wfMessage( 'wsps-special_share_list_info' )->text() . '</th>';
				$html      .= '<th class="uk-table-expand">' . wfMessage( 'wsps-special_share_requirements' )->text() . '</th>';
				$html      .= '<th class="uk-table-expand">' . wfMessage( 'wsps-special_share_requirements_installed' )->text() . '</th></tr>';
				foreach ( $subjectLst as $details ) {
					$shareFile = $category . '/' . $subjectName . '/' . $details['PSShareFile'];
					$html .= '<tr><td class="wsps-td"><input required="required" type="radio" class="uk-radio" name="gitfile" ';
					$html .= 'value = "' . $shareFile . '"></td>';
					$html .= '<td class="wsps-td">' . $details['Title'] . '<br><span class="uk-text-meta">' . $details['PSShareFile'];
					$html .= '</span></td>';
					$html .= '<td class="wsps-td">' . $details['Description'] . '</td>';
					$html .= '<td class="wsps-td"><ul class="uk-list uk-list-divider">';
					foreach ( $details['Requirements'] as $kName => $vVersion ) {
						$html .= '<li class="uk-text-small">' . $kName . ' - ' . $vVersion;
						$html .= '</li>';
					}
					$html .= '</ul></td>';
					$html .= '<td class="wsps-td"><ul class="uk-list uk-list-divider">';
					foreach ( $details['Requirements'] as $kName => $vVersion ) {
						$html .= '<li class="uk-text-small">';
						if ( $smw->isExtensionInstalled( $kName ) ) {
							$html .=  'v' . $smw->getExtensionVersion( $kName );
						} else {
							$html .= ' <span uk-icon="ban" class="uk-text-danger"></span>';
						}
						$html .= '</li>';
					}
					$html .= '</ul></td></tr>';
				}
				$html .= '</table>';
			}
		}
		return $html;
	}
}