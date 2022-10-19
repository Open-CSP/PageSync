<?php

namespace PageSync\Helpers;

use DateTime;
use PageSync\Core\PSConfig;
use PageSync\Core\PSCore;
use PageSync\Core\PSMessageMaker;
use PageSync\Core\PSNameSpaceUtils;
use ZipArchive;

class PSShare {

	/**
	 * @param string $url
	 *
	 * @return array
	 */
	public function downloadZipFile( string $url ) : array {
		$fName   = basename( $url );
		$tmpPath = PSConfig::$config['tempFilePath'];
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}
		$zipResource = fopen(
			$tmpPath . $fName,
			"w"
		);
		$ch          = curl_init();
		curl_setopt(
			$ch,
			CURLOPT_URL,
			$url
		);
		curl_setopt(
			$ch,
			CURLOPT_FAILONERROR,
			true
		);
		curl_setopt(
			$ch,
			CURLOPT_HEADER,
			0
		);
		curl_setopt(
			$ch,
			CURLOPT_FOLLOWLOCATION,
			true
		);
		curl_setopt(
			$ch,
			CURLOPT_AUTOREFERER,
			true
		);
		curl_setopt(
			$ch,
			CURLOPT_TIMEOUT,
			10
		);
		curl_setopt(
			$ch,
			CURLOPT_SSL_VERIFYHOST,
			0
		);
		curl_setopt(
			$ch,
			CURLOPT_SSL_VERIFYPEER,
			0
		);
		curl_setopt(
			$ch,
			CURLOPT_FILE,
			$zipResource
		);

		$page = curl_exec( $ch );

		if ( ! $page ) {
			$result = PSMessageMaker::makeMessage(
				false,
				curl_error( $ch )
			);
		} else {
			$result = PSMessageMaker::makeMessage(
				true,
				$tmpPath . $fName
			);
		}
		curl_close( $ch );

		return $result;
	}

	/**
	 * @return string
	 */
	public function getFormHeader( $inline = false ) : string {
		global $wgScript;
		if ( $inline ) {
			return '<form style="display:inline-block;" method="post" action="' . $wgScript . '/Special:WSps?action=share">';
		} else {
			return '<form method="post" action="' . $wgScript . '/Special:WSps?action=share">';
		}
	}

	/**
	 * @param array $tags
	 *
	 * @return array
	 */
	public function returnPagesWithAtLeastOneTag( array $tags ) : array {
		$allPages     = PSCore::getAllPageInfo();
		$correctPages = [];
		foreach ( $allPages as $page ) {
			$tagCount = 0;
			if ( isset( $page['tags'] ) ) {
				$pTags = explode(
					',',
					$page['tags']
				);
				foreach ( $pTags as $sTag ) {
					if ( in_array(
						$sTag,
						$tags
					) ) {
						$tagCount++;
					}
				}
				if ( $tagCount === 0 ) {
					continue;
				}
				$correctPages[] = $page;
			}
		}

		return $correctPages;
	}

	/**
	 * @param array $tags
	 *
	 * @return array
	 */
	public function returnPagesWithAllTage( array $tags ) : array {
		$allPages     = PSCore::getAllPageInfo();
		$correctPages = [];
		$nrOfTags     = count( $tags );
		foreach ( $allPages as $k => $page ) {
			$tagCount = 0;
			if ( isset( $page['tags'] ) ) {
				$pTags = explode(
					',',
					$page['tags']
				);
				foreach ( $pTags as $sTag ) {
					if ( in_array(
						$sTag,
						$tags
					) ) {
						$tagCount++;
					}
				}
				if ( $nrOfTags !== $tagCount ) {
					continue;
				}
				$correctPages[] = $page;
			}
		}

		return $correctPages;
	}

	/**
	 * Delete a share file
	 *
	 * @param string $shareFile
	 *
	 * @return bool
	 */
	public function deleteBackupFile( string $shareFile ) : bool {
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}
		$path = PSConfig::$config['filePath'];
		if ( file_exists( $path . $shareFile ) ) {
			unlink( $path . $shareFile );

			return true;
		}

		return false;
	}


	/**
	 * @param string $name
	 *
	 * @return array|false|string|string[]
	 */
	private function returnTitleFromFileName( string $name ) {
		if ( strpos(
			$name,
			'.info'
		) ) {
			$withoutExtension = str_replace(
				'.info',
				'',
				$name
			);

			return $withoutExtension;
		} else {
			return false;
		}
	}

	/**
	 * @param string $file
	 *
	 * @return array|null
	 */
	public function getShareFileContent( string $file ) : ?array {
		$data = [];
		$zip  = new ZipArchive();
		if ( $zip->open( $file ) === true ) {
			$data['count'] = $zip->numFiles;
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = $this->returnTitleFromFileName( $zip->getNameIndex( $i ) );
				if ( $name !== false ) {
					$content          = json_decode(
						$zip->getFromIndex( $i ),
						true
					);
					$nsId             = PSNameSpaceUtils::getNSFromTitleString( $content['pagetitle'] );
					$data['list'][$i] = PSNameSpaceUtils::titleForDisplay(
						$nsId,
						$content['pagetitle']
					);
					if ( isset( $content['description'] ) ) {
						$data['description'][$i] = $content['description'];
					} else {
						$data['description'][$i] = '';
					}
				}
			}
			$zip->close();
		} else {
			return null;
		}

		return $data;
	}

	/**
	 * @param string $file
	 *
	 * @return array|null
	 */
	public function getShareFileInfo( string $file ) : ?array {
		$data = [];
		$zip  = new ZipArchive();
		if ( $zip->open( $file ) === true ) {
			$json  = $zip->getArchiveComment();
			$count = $zip->numFiles;
			if ( $json === null ) {
				$zip->close();

				return null;
			}
			$json = json_decode(
				base64_decode( $json ),
				true
			);
			if ( $json === null ) {
				$zip->close();

				return null;
			}
			$json['nroffiles'] = $count;
			$data              = $json;
			$zip->close();
		} else {
			die( 'could not open : ' . $file );
		}

		return $data;
	}

	/**
	 * Get a list of all backup files
	 *
	 * @return array
	 */
	public function getShareList() : array {
		$data = [];
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}
		$path      = PSConfig::$config['filePath'];
		$shareList = glob( $path . "PageSync_*.zip" );
		if ( empty( $shareList ) ) {
			return $data;
		}
		$t = 0;
		foreach ( $shareList as $shareFile ) {
			$data[$t]['file'] = basename( $shareFile );
			$nfo              = $this->getShareFileInfo( $shareFile );
			if ( $nfo === null ) {
				$data[$t]['file'] = "error";
			} else {
				$data[$t]['info'] = $nfo;
			}
			$t++;
		}

		return $data;
	}

	/**
	 * @param string|false $disclaimer
	 * @param string|false $project
	 * @param string|false $company
	 * @param string|false $name
	 * @param string|false $uName
	 * @param string|false $requirements
	 *
	 * @return array
	 */
	public function createNFOFile(
		$disclaimer,
		$project,
		$company,
		$name,
		$uName,
		$requirements
	) : array {
		global $wgSitename;
		$ret                 = [];
		$ret['sitename']     = $wgSitename;
		$ret['disclaimer']   = $disclaimer === false ? '' : $disclaimer;
		$ret['project']      = $project === false ? '' : $project;
		$ret['company']      = $company === false ? '' : $company;
		$ret['name']         = $name === false ? '' : $name;
		$ret['uname']        = $name === false ? '' : $uName;
		$datetime            = new DateTime();
		$ret['date']         = $datetime->format( 'd-m-Y H:i:s' );
		$ret['requirements'] = $requirements;

		return $ret;
	}

	public function makeAlert( string $text, string $type = "danger" ) : string {
		$ret = '<div class="uk-alert-' . $type . ' uk-margin-large-top" uk-alert>';
		$ret .= '<a class="uk-alert-close" uk-close></a>';
		$ret .= '<p>' . $text . '</p></div>';

		return $ret;
	}

	/**
	 * @param array $pages
	 * @param array $nfoContent
	 *
	 * @return bool|string
	 */
	public function createShareFile( array $pages, array $nfoContent ) {
		if ( PSConfig::$config === false ) {
			PSCore::setConfig();
		}
		$path                  = PSConfig::$config['exportPath']; //filePath :: tempFilePath
		$tempPath              = PSConfig::$config['filePath'];
		$version               = str_replace(
			'.',
			'-',
			( PSConfig::$config['version'] )
		);
		$nfoContent['version'] = PSConfig::$config['version'];

		$addUploadedFile = [];
		$infoFilesList   = [];
		$wikiFilesList   = [];
		$t               = 0;
		foreach ( $pages as $fileToCheck ) {
			if ( isset( $fileToCheck['isFile'] ) && $fileToCheck['isFile'] === true ) {
				$addUploadedFile[$t] = $path . $fileToCheck['filestoredname'];
			}
			$infoFilesList[$t] = $path . $fileToCheck['filename'] . '.info';
			$wikiFilesList[$t] = glob( $path . $fileToCheck['filename'] . "*.wiki" );
			$t++;
		}

		$wikiList = [];
		foreach ( $wikiFilesList as $v ) {
			$wikiList = array_merge(
				$wikiList,
				array_values( $v )
			);
		}
		$fList      = array_merge(
			$addUploadedFile,
			$infoFilesList,
			$wikiList
		);
		$datetime   = DateTime::createFromFormat(
			'U',
			strtotime( $nfoContent['date'] )
		);
		$date       = $datetime->format( 'd-m-Y-H-i-s' );
		$nfoContent = json_encode( $nfoContent );
		$zip        = new ZipArchive();
		if ( $zip->open(
				$tempPath . 'PageSync_' . $date . '_' . $version . '.zip',
				zipArchive::CREATE
			) !== true ) {
			return $this->makeAlert( "cannot create " . $tempPath . 'PageSync_' . $date );
		}

		if ( ! $zip->setArchiveComment( base64_encode( $nfoContent ) ) ) {
			return $this->makeAlert( "cannot create Zip comment." );
		}

		foreach ( $fList as $wikiFile ) {
			$zip->addFile(
				$wikiFile,
				basename( $wikiFile )
			);
		}
		$zip->close();

		return true;
	}

	/**
	 * @param string $action
	 * @param mixed $selection
	 *
	 * @return string
	 */
	public function agreeSelectionShareFooter( string $action, $selection = [] ) : string {
		global $wgScript;
		switch ( $action ) {
			case "agreebtn":
				$doShareForm = '<input type="submit" style="display:inline-block;" class="uk-button uk-width-1-2 uk-button-primary" ';
				$doShareForm .= 'value="CREATE SHARE FILE" >';
				break;
			case "cancelbtn":
				$doShareForm = $this->getFormHeader( false );
				$doShareForm .= '<input type="hidden" name="wsps-action" value="wsps-share-docancel">';
				$doShareForm .= '<input type="submit" style="display:inline-block;" class="uk-button uk-width-1-2 uk-button-primary" value="';
				$doShareForm .= "CANCEL";
				$doShareForm .= '"></form>';
				break;
			case "body":
			default:
				$tags        = base64_encode( $selection['tags'] );
				$type        = base64_encode( $selection['type'] );
				$doShareForm = '<input type="hidden" name="wsps-action" value="wsps-share-doshare">';
				$doShareForm .= '<input type="hidden" name="wsps-type" value="' . $type . '">';
				$doShareForm .= '<input type="hidden" name="wsps-tags" value="' . $tags . '">';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_disclaimer' )->text(
					) . '<sup>*</sup></label>';
				$doShareForm .= '<textarea required="required" class="uk-textarea uk-width-1-1" rows="5" name="disclaimer" >';
				$doShareForm .= wfMessage( 'wsps-special_share_default_disclaimer' )->text() . '</textarea>';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_project' )->text(
					) . '</label>';
				$doShareForm .= '<input type="text"" class="uk-input uk-width-1-1" name="project" >';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_company' )->text(
					) . '</label>';
				$doShareForm .= '<input type="text"" class="uk-input uk-width-1-1" name="company" >';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_name' )->text(
					) . '</label>';
				$doShareForm .= '<input type="text"" class="uk-input uk-width-1-1" name="name" >';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_requirements' )->text(
					) . '</label>';
				$doShareForm .= '<input type="text"" class="uk-input uk-width-1-1" name="requirements" ><br>';
				$doShareForm .= '<span class="uk-text-meta">' . wfMessage(
						'wsps-special_share_requirements_sub'
					)->text() . '</span>';
				break;
		}

		return $doShareForm;
		//return '<div class="uk-align-center">' . $btn_create . $btn_install . '</div>';
	}

	/**
	 * @param bool $returnSubmit
	 *
	 * @return string
	 */
	public function renderCreateSelectTagsForm( bool $returnSubmit = false ) : string {
		global $IP;

		//$smw = ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' );
		$smw = false;
		if ( ! $returnSubmit ) {
			$selectTagsForm = '<input type="hidden" name="wsps-action" value="wsps-share-select-tags">';
			$selectTagsForm .= '<div class="uk-grid-small" uk-grid>';
			if ( $smw ) {
				$selectTagsForm .= '<div class="uk-width-1-2">';
				$selectTagsForm .= '<fieldset class="uk-fieldset uk-margin">';
				$selectTagsForm .= '<legend class="uk-legend">' . wfMessage(
						'wsps-special_custom_query_card_subheader'
					)->text() . '</legend>';
				$selectTagsForm .= '<label class="uk-form-label uk-text-medium" for="wsps-query">';
				$selectTagsForm .= wfMessage( 'wsps-special_custom_query_card_label' )->text();
				$selectTagsForm .= '</label>';
				$selectTagsForm .= '<div class="uk-form-controls">';
				$selectTagsForm .= '<input class="uk-input" name="wsps-query" type="text" placeholder="';
				$selectTagsForm .= wfMessage( 'wsps-special_custom_query_card_placeholder' )->text();
				$selectTagsForm .= '">';
				$selectTagsForm .= '</div></fieldset></div><div class="uk-width-1-2">';
			} else {
				$selectTagsForm .= '<div class="uk-width-1-1">';
			}
			$selectTagsForm .= '<fieldset class="uk-fieldse uk-margin">';
			if ( $smw ) {
				$selectTagsForm .= '<legend class="uk-legend">Or choose pages based on tags</legend>';
			} else {
				$selectTagsForm .= '<legend class="uk-legend">';
				$selectTagsForm .= wfMessage( 'wsps-special_share_choose_tags' )->text() . '</legend>';
			}
			$selectTagsForm .= '<select id="ps-tags" class="uk-with-1-1" name="tags[]" multiple="multiple" >';
			$tags           = PSCore::getAllTags();
			foreach ( $tags as $tag ) {
				if ( ! empty( $tag ) ) {
					$selectTagsForm .= '<option selected="selected" value="' . $tag . '">' . $tag . '</option>';
				}
			}
			$selectTagsForm .= '</select>';
			$selectTagsForm .= '<p><input type="radio" id="ws-all" class="uk-radio" name="wsps-select-type" checked="checked" value="all">';
			$selectTagsForm .= ' <label for="ws-all" class="uk-form-label">';
			$selectTagsForm .= wfMessage( 'wsps-special_share_choose_options1' )->text() . '</label><br>';
			$selectTagsForm .= '<input type="radio" id="ws-one" class="uk-radio" name="wsps-select-type" value="one">';
			$selectTagsForm .= ' <label for="ws-one" class="uk-form-label">';
			$selectTagsForm .= wfMessage( 'wsps-special_share_choose_options2' )->text() . '</label><br>';
			$selectTagsForm .= '<input type="radio" id="ws-all-pages" class="uk-radio" name="wsps-select-type" value="ignore">';
			$selectTagsForm .= ' <label for="ws-all-pages" class="uk-form-label">';
			$selectTagsForm .= wfMessage( 'wsps-special_share_choose_options3' )->text(
				) . '</label></p></fieldset></div>';
			$selectTagsForm .= '<script>' . file_get_contents(
					$IP . '/extensions/PageSync/assets/js/loadSelect2.js'
				) . '</script>';;
		} else {
			$selectTagsForm = '<input type="submit" class="uk-button uk-width-1-1 uk-button-primary" value="';
			$selectTagsForm .= wfMessage( 'wsps-special_share_submit_and_preview' )->text();
			$selectTagsForm .= '">';
		}

		return $selectTagsForm;
	}

	/**
	 * @param bool $returnSubmit
	 *
	 * @return string
	 */
	public function renderDownloadUrlForm( bool $returnSubmit = false ) : string {
		if ( ! $returnSubmit ) {
			$downloadForm = '<input type="hidden" name="wsps-action" value="wsps-share-downloadurl">';
			$downloadForm .= '<div class="uk-margin"><div class="uk-inline  uk-width-1-1"><a class="uk-form-icon uk-form-icon-flip" href="#" uk-icon="icon: link"></a>';
			$downloadForm .= '<input class="uk-input" name="url" type="url" placeholder="URL to ZIP File"></div></div>';
		} else {
			$downloadForm = '<input type="submit" class="uk-button uk-width-1-1 uk-button-primary" value="';
			//$downloadForm .= wfMessage( 'wsps-error_file_consistency_btn_backup' )->text();
			$downloadForm .= "Preview Shared file";
			$downloadForm .= '">';
		}

		return $downloadForm;
	}

	public function isZipfile( $data ) {
		$fileHeader = "\x50\x4b\x03\x04";
		if ( strpos(
				 $data,
				 $fileHeader
			 ) === false ) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $path
	 *
	 * @return array
	 */
	public function getFileInfoList( string $path ) : array {
		$fList = glob( $path . "*.info" );
		$data  = [];
		if ( $fList !== false && ! empty ( $fList ) ) {
			foreach ( $fList as $infoFile ) {
				if ( file_exists( $infoFile ) ) {
					$data[] = json_decode(
						file_get_contents( $infoFile ),
						true
					);
				}
			}
		}
		if ( !empty( $data ) ) {
			array_multisort(
				array_map(
					'strtotime',
					array_column(
						$data,
						'changed'
					)
				),
				SORT_DESC,
				$data
			);
		}

		return $data;
	}

	/**
	 * @param string $fileUrl
	 *
	 * @return bool|string
	 */
	public function getExternalZipAndStoreIntemp( string $fileUrl ) {
		$tempPath = PSConfig::$config['tempFilePath'];
		// First remove any ZIP file in the temp folder
		array_map(
			'unlink',
			glob( $tempPath . "*.zip" )
		);
		$zipFile = @file_get_contents( $fileUrl );
		if ( $zipFile === false || $this->isZipfile( $zipFile ) === false ) {
			return 'Could not load Share url. Not a valid ZIP file or it can not be downloaded.';
		}
		if ( !file_put_contents(
			$tempPath . basename( $fileUrl ),
			$zipFile
		) ) {
			return 'Could not save Share File to Temp folder';
		}

		return true;
	}

	/**
	 * @param string $zipFile
	 *
	 * @return false|string
	 */
	public function extractTempZip( string $zipFile ) {
		$zipFileAndPath = PSConfig::$config['tempFilePath'] . $zipFile;
		if ( !file_exists( $zipFileAndPath ) ) {
			return false;
		}
		$zipTempPath = PSConfig::$config['tempFilePath'] . basename(
				$zipFile,
				'.zip'
			);
		$zip         = new ZipArchive();
		if ( file_exists( $zipTempPath ) ) {
			$back = new WSpsHooksBackup();
			$back->removeRecursively(
				$zipTempPath,
				$zipTempPath
			);
			rmdir( $zipTempPath );
			if ( !mkdir( $zipTempPath ) ) {
				return false;
			}
		}
		if ( $zip->open( $zipFileAndPath ) === true ) {
			$zip->extractTo( $zipTempPath );
			$zip->close();

			return $zipTempPath . '/';
		} else {
			return false;
		}
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	private function replaceUnderScoreWithSpace( string $name ) : string {
		return str_replace(
			'_',
			' ',
			$name
		);
	}

	/**
	 * @param array $file
	 *
	 * @return string
	 */
	public function renderShareFileInformationConsole( array $file ) : string {
		$txt = '*** SHARE FILE INFORMATION ***' . "\n";
		$txt .= "\n";
		$txt .= "\n*** ";
		$txt .= 'File : ' . basename( $file['file'] );
		$txt .= "\n*** ";
		$txt .= $file['info']['nroffiles'] . ' file(s)';
		$txt .= "\n*** ";
		$txt .= wfMessage( 'wsps-special_table_header_project' )->text() . ': ';
		$txt .= $file['info']['project'];
		$txt .= "\n*** ";

		$txt .= wfMessage( 'wsps-special_table_header_website' )->text() . ': ';
		$txt .= $file['info']['sitename'];
		$txt .= "\n*** ";

		$txt .= wfMessage( 'wsps-special_table_header_company' )->text() . ': ';
		$txt .= $file['info']['company'];
		$txt .= "\n*** ";

		$txt .= wfMessage( 'wsps-special_table_header_name' )->text() . ': ';
		$txt .= $file['info']['name'] . ' (' . $file['info']['uname'] . ')';
		$txt .= "\n*** ";

		$txt .= wfMessage( 'wsps-special_table_header_date' )->text() . ': ';
		$txt .= $file['info']['date'];
		$txt .= "\n*** ";

		$txt .= wfMessage( 'wsps-special_table_header_version' )->text() . ': ';
		$txt .= $file['info']['version'];
		$txt .= "\n*** ";

		if ( isset( $file['info']['requirements'] ) ) {
			$txt .= wfMessage( 'wsps-special_share_requirements' )->text() . ': ';
			$txt .= $this->requirementsToConsole( $file['info']['requirements'] );
			$txt .= "\n*** ";
		}

		$txt .= wfMessage( 'wsps-special_table_header_description' )->text() . ': ';
		$txt .= $file['info']['disclaimer'];
		$txt .= "\n***************************\n";
		$txt .= 'File Contents';
		$txt .= "\n";

		$t = 1;
		foreach ( $file['list']['list'] as $k => $entry ) {
			$txt .= str_pad(
						$t,
						4,
						'0',
						STR_PAD_LEFT
					) . '. ';
			$txt .= $entry . " (";
			$txt .= $file['list']['description'][$k] . ")\n";
			$t++;
		}

		return $txt . "\n\n";
	}

	/**
	 * @param array|false $requirements
	 *
	 * @return string
	 */
	public function requirementsToHTML( $requirements ) : string {
		if ( $requirements === false ) {
			return "";
		}
		$ret = '<ul>';
		foreach ( $requirements as $requirement ) {
			$line = $requirement['name'];
			if ( isset( $requirement['version'] ) ) {
				$line .= ' -v' . $requirement['version'];
			}
			$ret .= '<li>' . $line . '</li>';
		}
		$ret .= '</ul>';

		return $ret;
	}

	/**
	 * @param array|false $requirements
	 *
	 * @return string
	 */
	public function requirementsToConsole( $requirements ) : string {
		if ( $requirements === false ) {
			return "";
		}
		$ret = "";
		foreach ( $requirements as $requirement ) {
			$line = $requirement['name'];
			if ( isset( $requirement['version'] ) ) {
				$line .= ' -v' . $requirement['version'];
			}
			$ret .= '* ' . $line . PHP_EOL;
		}
		return $ret;
	}

	/**
	 * @param array $file
	 *
	 * @return string
	 */
	public function renderShareFileInformation( array $file, $footer = false ) : string {
		if ( $footer ) {
			$html = $this->getFormHeader();
			$html .= '<input type="hidden" name="wsps-action" value="wsps-do-download-install">';
			$html .= '<input type="hidden" name="tmpfile" value="' . $file['sharefile'] . '">';
			$html .= '<div class="uk-form-controls">';
			if ( $file['info']['version'][0] === '1' ) {
				$html .= '<span class="uk-text-danger uk-text-emphasis">' . wfMessage(
						'wsps-special_share_older'
					)->text() . '</span>';
			} else {
				$html .= '<label><input class="uk-checkbox" type="checkbox" name="agreed" required="required"> I agree with the description</label>';
				$html .= '<input type="submit" class="uk-button uk-inline uk-button-primary uk-margin-large-left uk-width-1-4" value="Install files">';
			}
			$html .= '</div></form>';

			return $html;
		}
		$html = '<div class="uk-grid-divider uk-child-width-expand@s" uk-grid>';
		$html .= '<div><h2 class="uk-heading-bullet uk-heading-small">File Information</h2>';
		$html .= '<table class="uk-table uk-table-small">';

		$html .= '<tr></tr><td class="uk-table-shrink uk-text-bold">';
		$html .= 'File';
		$html .= '</td><td class="uk-table-expand uk-text-primary">' . basename( $file['file'] ) . '</td></tr>';

		$html .= '<tr></tr><td class="uk-table-shrink uk-text-bold">';
		$html .= 'Contains';
		$html .= '</td><td class="uk-table-expand uk-text-primary">' . $file['info']['nroffiles'] . ' file(s)</td></tr>';

		$html .= '<tr></tr><td class="uk-table-shrink uk-text-bold">';
		$html .= wfMessage( 'wsps-special_table_header_project' )->text();
		$html .= '</td><td class="uk-table-expand uk-text-primary">' . $file['info']['project'] . '</td></tr>';

		$html .= '<tr><td class="uk-table-shrink uk-text-bold">';
		$html .= wfMessage( 'wsps-special_table_header_website' )->text();
		$html .= '</td><td class="uk-table-expand uk-text-primary">' . $file['info']['sitename'] . '</td></tr>';

		$html .= '<tr><td class="uk-table-shrink uk-text-bold">';
		$html .= wfMessage( 'wsps-special_table_header_company' )->text();
		$html .= '</td><td class="uk-table-expand uk-text-primary">' . $file['info']['company'] . '</td></tr>';

		$html .= '<tr><td class="uk-table-shrink uk-text-bold">';
		$html .= wfMessage( 'wsps-special_table_header_name' )->text();
		$html .= '</td><td class="uk-table-expand uk-text-primary">' . $file['info']['name'] . ' (';
		$html .= $file['info']['uname'] . ')</td></tr>';

		$html .= '<tr><td class="uk-table-shrink uk-text-bold">';
		$html .= wfMessage( 'wsps-special_table_header_date' )->text();
		$html .= '</td><td class="uk-table-expand uk-text-primary">' . $file['info']['date'] . '</td></tr>';

		$html .= '<tr><td class="uk-table-shrink uk-text-bold">';
		$html .= wfMessage( 'wsps-special_table_header_version' )->text();
		$html .= '</td><td class="uk-table-expand uk-text-primary">' . $file['info']['version'] . '</td></tr>';

		if ( isset( $file['info']['requirements'] ) ) {
			$html         .= '<tr><td class="uk-table-shrink uk-text-bold">';
			$html         .= wfMessage( 'wsps-special_share_requirements' )->text();
			$requirements = $this->requirementsToHTML( $file['info']['requirements'] );
			$html         .= '</td><td class="uk-table-expand uk-text-primary">' . $requirements . '</td></tr>';
		}
		$html .= '<tr><td class="uk-table-shrink uk-text-bold">';
		$html .= wfMessage( 'wsps-special_table_header_description' )->text();
		$html .= '</td><td class="uk-table-expand uk-text-primary uk-text-italic">' . $file['info']['disclaimer'];
		$html .= '</td></tr>';

		$html .= '</table></div>';

		$html .= '<div><h2 class="uk-heading-bullet uk-heading-small">File Contents</h2>';
		$html .= '<table style="width:100%;" class="uk-table uk-table-striped uk-table-hover uk-table-small"><thead><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_page_title' )->text();
		$html .= '</th><th>' . wfMessage( 'wsps-special_table_header_description' )->text();
		$html .= '</th></tr>';
		$t    = 1;
		foreach ( $file['list']['list'] as $k => $entry ) {
			$html .= '<tr><td class="uk-table-shrink uk-text-bold">' . $t . '</td>';
			$html .= '<td class="wsps-td uk-text-primary">' . $entry . '</td>';
			$html .= '<td class="wsps-td uk-text-muted uk-text-italic">' . $file['list']['description'][$k] . '</td>';
			$html .= '</tr>';
			$t++;
		}
		$html .= '</table></div></div>';

		return $html;
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	public function renderShareList( array $data ) : string {
		$html = '';
		$html .= '<table style="width:100%;" class="uk-table uk-table-striped uk-table-hover"><thead><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_share' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_project' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_company' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_name' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_website' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_date' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_version' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_delete' )->text() . '</th></tr></thead>';
		$row  = 1;
		if ( empty( $data ) ) { // content_no_backups
			$html .= '</table>';
			$html .= wfMessage( 'wsps-content_no_shares' )->text();
		} else {
			foreach ( $data as $share ) {
				$fName  = basename( $share['file'] );
				$html   .= '<tr><td class="wsps-td">' . $row . '</td>';
				$html   .= '<td class="wsps-td"><span uk-icon="icon: album"></span> ' . $fName . '</td>';
				$html   .= '<td class="wsps-td">' . $share['info']['project'] . '</td>';
				$html   .= '<td class="wsps-td">' . $share['info']['company'] . '</td>';
				$html   .= '<td class="wsps-td">' . $share['info']['name'] . ' (<span uk-icon="icon: user"></span> ' . $share['info']['uname'] . ')</td>';
				$html   .= '<td class="wsps-td">' . $share['info']['sitename'] . '</td>';
				$html   .= '<td class="wsps-td"><span uk-icon="icon: calendar"></span> ' . $share['info']['date'] . '</td>';
				$html   .= '<td class="wsps-td">' . $share['info']['version'] . '</td>';
				$button = '<a class="uk-icon-button wsps-download-share" uk-icon="download" data-id="' . $fName . '" title="' . wfMessage(
						'wsps-special_backup_download'
					)->text() . '"></a> ';
				$button .= '<a class="uk-icon-button wsps-delete-share" uk-icon="ban" data-id="' . $fName . '" title="' . wfMessage(
						'wsps-special_backup_delete'
					)->text() . '"></a> ';
				$html   .= '<td class="wsps-td">' . $button . '</td>';
				$html   .= '</tr>';
				$html   .= '<tr><td class="wsps-td"></td><td class="wsps-td" colspan="8"><span class="uk-text-meta"><span uk-icon="icon: info"></span> ' . $share['info']['disclaimer'] . '</span></td></tr>';
				$row++;
			}
			$html .= '</table>';
		}

		return $html;
	}

	/**
	 * @return string
	 */
	public function renderChooseAction() : string {
		global $wgScript;
		$btn_create = '<form style="display:inline-block;" method="post" action="' . $wgScript . '/Special:WSps?action=share">';
		$btn_create .= '<input type="hidden" name="wsps-action" value="wsps-share-create">';
		$btn_create .= '<input type="submit" style="display:inline-block;" class="uk-button uk-width-1-1 uk-button-primary" value="';
		//$btn_create .= wfMessage( 'wsps-error_file_consistency_btn_backup' )->text();
		$btn_create .= "Create a Share ZIP file";
		$btn_create .= '"></form>';

		$btn_install = ' <form style="display:inline-block;" method="post" action="' . $wgScript . '/Special:WSps?action=share">';
		$btn_install .= '<input type="hidden" name="wsps-action" value="wsps-share-install">';
		$btn_install .= '<input type="submit" style="display:inline-block;" class="uk-button uk-width-1-1 uk-button-primary" value="';
		//$btn_install .= wfMessage( 'wsps-error_file_consistency_btn_backup' )->text();
		$btn_install .= "Install a shared ZIP file";
		$btn_install .= '"></form>';

		return '<div class="uk-align-center">' . $btn_create . $btn_install . '</div>';
	}

}