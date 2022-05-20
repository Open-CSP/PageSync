<?php


class PSShare {

	/**
	 * @param string $url
	 *
	 * @return array
	 */
	public function downloadZipFile( string $url ) : array {
		$fName = basename( $url );
		$tmpPath = WSpsHooks::$config['tempFilePath'];
		if ( WSpsHooks::$config === false ) {
			WSpsHooks::setConfig();
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

		if ( !$page ) {
			$result = WSpsHooks::makeMessage(
				false,
				curl_error( $ch )
			);
		} else {
			$result = WSpsHooks::makeMessage(
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
	public function getFormHeader( $inline = false ): string {
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
	public function returnPagesWithAtLeastOneTag( array $tags ): array {
		$allPages = WSpsHooks::getAllPageInfo();
		$correctPages = [];
		foreach ( $allPages as $page ) {
			$tagCount = 0;
			if ( isset( $page['tags'] ) ) {
				$pTags = explode( ',', $page['tags'] );
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
	public function returnPagesWithAllTage( array $tags ): array {
		$allPages = WSpsHooks::getAllPageInfo();
		$correctPages = [];
		$nrOfTags = count( $tags );
		foreach ( $allPages as $k => $page ) {
			$tagCount = 0;
			if ( isset( $page['tags'] ) ) {
				$pTags = explode( ',', $page['tags'] );
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
	 * @param string $disclaimer
	 * @param string|null $project
	 * @param string|null $company
	 * @param string|null $name
	 * @param string|null $uName
	 *
	 * @return array
	 */
	public function createNFOFile(
		string $disclaimer,
		?string $project,
		?string $company,
		?string $name,
		?string $uName
	) : array {
		global $wgSitename;
		$ret               = [];
		$ret['sitename']   = $wgSitename;
		$ret['disclaimer'] = $disclaimer;
		$ret['project']    = $project === null ? '' : $project;
		$ret['company']    = $company === null ? '' : $company;
		$ret['name']       = $name === null ? '' : $name;
		$ret['uname']      = $name === null ? '' : $uName;
		$datetime          = new DateTime();
		$ret['date']       = $datetime->format( 'd-m-Y H:i:s' );

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
		if ( WSpsHooks::$config === false ) {
			WSpsHooks::setConfig();
		}
		$path            = WSpsHooks::$config['exportPath']; //filePath :: tempFilePath
		$tempPath		 = WSpsHooks::$config['exportPath'];
		$version         = str_replace(
			'.',
			'-',
			( WSpsHooks::$config['version'] )
		);
		$nfoContent['version'] = WSpsHooks::$config['version'];

		$addUploadedFile = [];
		$infoFilesList = [];
		$wikiFilesList = [];
		$t = 0;
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
			$wikiList = array_merge( $wikiList, array_values( $v ) );
		}
		$fList    = array_merge( $addUploadedFile, $infoFilesList, $wikiList );
		$datetime = DateTime::createFromFormat( 'U', strtotime( $nfoContent['date'] ) );
		$date     = $datetime->format( 'd-m-Y-H-i-s' );
		$nfoContent = json_encode( $nfoContent );
		$zip = new ZipArchive();
		if ( $zip->open(
				$tempPath . 'PageSync_' . $date . '_' . $version . '.zip',
				zipArchive::CREATE
			) !== true ) {
			return $this->makeAlert( "cannot create " . $tempPath . 'PageSync_' . $date );
		}

		if ( !$zip->setArchiveComment( base64_encode( $nfoContent ) ) ) {
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
	public function agreeSelectionShareFooter( string $action, $selection = [] ): string {
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
				$tags = base64_encode( $selection['tags'] );
				$type = base64_encode( $selection['type'] );
				$doShareForm = '<input type="hidden" name="wsps-action" value="wsps-share-doshare">';
				$doShareForm .= '<input type="hidden" name="wsps-type" value="' . $type . '">';
				$doShareForm .= '<input type="hidden" name="wsps-tags" value="' . $tags . '">';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_disclaimer' )->text() . '<sup>*</sup></label>';
				$doShareForm .= '<textarea required="required" class="uk-textarea uk-width-1-1" rows="5" name="disclaimer" >';
				$doShareForm .= wfMessage( 'wsps-special_share_default_disclaimer' )->text() . '</textarea>';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_project' )->text() . '</label>';
				$doShareForm .= '<input type="text"" class="uk-input uk-width-1-1" name="project" >';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_company' )->text() . '</label>';
				$doShareForm .= '<input type="text"" class="uk-input uk-width-1-1" name="company" >';
				$doShareForm .= '<label class="uk-form-label">' . wfMessage( 'wsps-special_share_name' )->text() . '</label>';
				$doShareForm .= '<input type="text"" class="uk-input uk-width-1-1" name="name" >';
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
	public function renderCreateSelectTagsForm( bool $returnSubmit = false ): string {
		global $IP;

		//$smw = ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' );
		$smw = false;
		if ( !$returnSubmit ) {
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
			$tags       = WSpsHooks::getAllTags();
			foreach ( $tags as $tag ) {
				if ( !empty( $tag ) ) {
					$selectTagsForm .= '<option selected="selected" value="' . $tag . '">' . $tag . '</option>';
				}
			}
			$selectTagsForm .= '</select>';
			$selectTagsForm .= '<p><input type="radio" id="ws-all" class="uk-radio" name="wsps-select-type" checked="checked" value="all">';
			$selectTagsForm .= ' <label for="ws-all" class="uk-form-label">';
			$selectTagsForm .= wfMessage( 'wsps-special_share_choose_options1' )->text() . '</label><br>';
			$selectTagsForm .= '<input type="radio" id="ws-one" class="uk-radio" name="wsps-select-type" value="one">';
			$selectTagsForm .= ' <label for="ws-one" class="uk-form-label">';
			$selectTagsForm .=  wfMessage( 'wsps-special_share_choose_options2' )->text() . '</label><br>';
			$selectTagsForm .= '<input type="radio" id="ws-all-pages" class="uk-radio" name="wsps-select-type" value="ignore">';
			$selectTagsForm .= ' <label for="ws-all-pages" class="uk-form-label">';
			$selectTagsForm .=  wfMessage( 'wsps-special_share_choose_options3' )->text() . '</label></p></fieldset></div>';
			$selectTagsForm .= '<script>' . file_get_contents( $IP . '/extensions/PageSync/assets/js/loadSelect2.js' ) . '</script>';;
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
	public function renderDownloadUrlForm( bool $returnSubmit = false ): string {
		if ( !$returnSubmit ) {
			$downloadForm = '<input type="hidden" name="wsps-action" value="wsps-share-downloadurl">';
			$downloadForm .= '<div class="uk-margin"><div class="uk-inline  uk-width-1-1"><a class="uk-form-icon uk-form-icon-flip" href="#" uk-icon="icon: link"></a>';
			$downloadForm .= '<input class="uk-input" name="url" type="url" placeholder="URL to ZIP File"></div></div>';
		} else {
			$downloadForm = '<input type="submit" class="uk-button uk-width-1-1 uk-button-primary" value="';
			//$downloadForm .= wfMessage( 'wsps-error_file_consistency_btn_backup' )->text();
			$downloadForm .= "Download and Preview Shared file";
			$downloadForm .= '">';
		}
		return $downloadForm;
	}

	/**
	 * @return string
	 */
	public function renderChooseAction(): string {
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