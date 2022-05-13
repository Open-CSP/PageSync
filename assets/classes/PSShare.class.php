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
	public function getFormHeader(): string {
		global $wgScript;
		return '<form method="post" action="' . $wgScript . '/Special:WSps?action=share">';
	}

	/**
	 * @param bool $returnSubmit
	 *
	 * @return string
	 */
	public function renderDownloadUrlForm( $returnSubmit = false ): string {
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