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

	public function agreeSelectionShareFooter( string $action ): string {
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
				$doShareForm = '<input type="hidden" name="wsps-action" value="wsps-share-doshare">';
				$doShareForm .= '<label class="uk-form-label">Disclaimer<sup>*</sup></label>';
				$doShareForm .= '<textarea required="required" class="uk-textarea uk-width-1-1" rows="5" name="disclaimer" ></textarea>';
				$doShareForm .= '<label class="uk-form-label">Project</label>';
				$doShareForm .= '<input type="text"" required="required" class="uk-input uk-width-1-1" name="project" >';
				$doShareForm .= '<label class="uk-form-label">Company</label>';
				$doShareForm .= '<input type="text"" required="required" class="uk-input uk-width-1-1" name="company" >';
				$doShareForm .= '<label class="uk-form-label">Your name</label>';
				$doShareForm .= '<input type="text"" required="required" class="uk-input uk-width-1-1" name="name" >';
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
		if ( !$returnSubmit ) {
			$selectTagsForm = '<input type="hidden" name="wsps-action" value="wsps-share-select-tags">';
			$selectTagsForm .= '<label class="uk-form-label">Choose pages based on tags</label>';
			$selectTagsForm .= '<select id="ps-tags" class="uk-with-1-1" name="tags[]" multiple="multiple" >';
			$tags       = WSpsHooks::getAllTags();
			foreach ( $tags as $tag ) {
				if ( !empty( $tag ) ) {
					$selectTagsForm .= '<option selected="selected" value="' . $tag . '">' . $tag . '</option>';
				}
			}
			$selectTagsForm .= '</select>';
			$selectTagsForm .= '<p><input type="radio" id="ws-all" class="uk-radio" name="wsps-select-type" checked="checked" value="all">';
			$selectTagsForm .= ' <label for="ws-all" class="uk-form-label">Pages must have all chosen tags</label><br>';
			$selectTagsForm .= '<input type="radio" id="ws-one" class="uk-radio" name="wsps-select-type" value="one">';
			$selectTagsForm .= ' <label for="ws-one" class="uk-form-label">Pages must have at least one chosen tag</label><br>';
			$selectTagsForm .= '<input type="radio" id="ws-all-pages" class="uk-radio" name="wsps-select-type" value="ignore">';
			$selectTagsForm .= ' <label for="ws-all-pages" class="uk-form-label">Ignore tags and select all synced pages</label></p>';
			$selectTagsForm .= '<script>' . file_get_contents( $IP . '/extensions/PageSync/assets/js/loadSelect2.js' ) . '</script>';;
		} else {
			$selectTagsForm = '<input type="submit" class="uk-button uk-width-1-1 uk-button-primary" value="';
			$selectTagsForm .= "Select and preview pages";
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