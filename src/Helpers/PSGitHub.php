<?php

namespace PageSync\Helpers;

use MediaWiki\MediaWikiServices;
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
	 * @return string
	 */
	public function renderListofGitHubFiles( PSShare $share ) : string {
		$smw = new PSSpecialSMWQeury();
		$fileRepo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
		$wikiFiles = $fileRepo->findFilesByPrefix( 'PageSync_', 100 );
		$sub = wfMessage( 'wsps-special_share_list_wikifile_subject' )->text();
		$cat = wfMessage( 'wsps-special_share_list_wikifile_category' )->text();
		if ( !empty( $wikiFiles ) ) {
			$filesInWiki = [];
			$t = 0;
			foreach ( $wikiFiles as $file ) {

				$fName = $file->getName();
				$fInfo = $share->getShareFileInfo( $file->getLocalRefPath() );

				if ( $fInfo === null ) {
					continue;
				}

				if ( isset( $fInfo['version'] ) && ( version_compare( $fInfo['version'], '2.1.0' ) < 0 ) ) {
					$filesInWiki[$sub][$cat][$t]['Requirements'] = false;
					$filesInWiki[$sub][$cat][$t]['Description'] = wfMessage(
						'wsps-special_share_file_incompatible',
						$fInfo['version'] );
				} else {
					$filesInWiki[$sub][$cat][$t]['Description'] = $fInfo['disclaimer'];
					foreach ( $fInfo['requirements'] as $requirement ) {
						$name = $requirement['name'];
						if ( isset( $requirement['version'] ) ) {
							$version = $requirement['version'];
						} else {
							$version = '-';
						}
						$filesInWiki[$sub][$cat][$t]['Requirements'][$name] = $version;
					}
				}
				$filesInWiki[$sub][$cat][$t]['Title'] = $fInfo['project'];
				$filesInWiki[$sub][$cat][$t]['PSShareFile'] = $file->getLocalRefPath();
				$filesInWiki[$sub][$cat][$t]['PSShareFileLink'] = '<a href="' . $file->getTitle()->getFullURL() . '">' . $fName . '</a>';
				//$filesInWiki[$sub][$cat][$t]['total'] = $fInfo;
				$t++;
			}
		}
		$data = $this->getFileList();
		$data = array_merge( $filesInWiki, $data );
		$html = '<input type="hidden" name="wsps-action" value="wsps-share-downloadurl">';
		foreach ( $data as $category => $subject ) {
			$html .= '<h4 class="uk-heading-bullet uk-margin-remove-top">'. $category .'</h4>';
			foreach ( $subject as $subjectName=>$subjectLst ) {
				$html      .= '<h5 class="uk-heading-line uk-margin-remove-top"><span>' . $subjectName . '</span></h5>';
				$html      .= '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover uk-table-justify"><tr>';
				$html      .= '<th></th><th>' . wfMessage( 'wsps-special_share_list_name' )->text() . '</th>';
				$html      .= '<th>' . wfMessage( 'wsps-special_share_list_info' )->text() . '</th>';
				$html      .= '<th class="uk-table-expand">' . wfMessage( 'wsps-special_share_requirements' )->text() . '</th>';
				$html      .= '<th class="uk-table-expand">' . wfMessage( 'wsps-special_share_requirements_installed' )->text() . '</th></tr>';
				foreach ( $subjectLst as $details ) {
					if ( $category !== $sub ) {
						$shareFile = $category . '/' . $subjectName . '/' . $details['PSShareFile'];
					} else {
						$shareFile = 'WIKI:' . $details['PSShareFile'];
					}
					if ( $details['Requirements'] === false ) {
						$html .= '<tr><td></td>';
					} else {
						$html .= '<tr><td class="wsps-td"><input required="required" type="radio" class="uk-radio" name="gitfile" ';
						$html .= 'value = "' . $shareFile . '"></td>';
					}
					$html .= '<td class="wsps-td">' . $details['Title'] . '<br><span class="uk-text-meta">';
					if ( $category !== $sub ) {
						$html .= $details['PSShareFile'];
					} else {
						$html .= $details['PSShareFileLink'];
					}
					$html .= '</span></td>';
					$html .= '<td class="wsps-td">' . $details['Description'] . '</td>';
					$html .= '<td class="wsps-td"><ul class="uk-list uk-list-divider">';
					if ( $details['Requirements'] !== false ) {
						foreach ( $details['Requirements'] as $kName => $vVersion ) {
							$html .= '<li class="uk-text-small">' . $kName . ' - ' . $vVersion;
							$html .= '</li>';
						}
						$html .= '</ul></td>';
						$html .= '<td class="wsps-td"><ul class="uk-list uk-list-divider">';

						foreach ( $details['Requirements'] as $kName => $vVersion ) {
							$html .= '<li class="uk-text-small">';
							if ( $smw->isExtensionInstalled( $kName ) ) {
								$html .= 'v' . $smw->getExtensionVersion( $kName );
							} else {
								$html .= ' <span uk-icon="ban" class="uk-text-danger"></span>';
							}
							$html .= '</li>';
						}
					}
					$html .= '</ul></td></tr>';
				}
				$html .= '</table>';
			}
		}
		return $html;
	}
}