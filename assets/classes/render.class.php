<?php
/**
 * Created by  : Designburo.nl
 * Project     : i
 * Filename    : render.class.php
 * Description :
 * Date        : 25/01/2019
 * Time        : 22:13
 */

class render {

	/**
	 * Output a progressbar
	 *
	 * @param  [int] $processed [Where are we]
	 * @param  [int] $max       [Total]
	 */

	function progress( $percentage, $value, $max, $extraInfo = "" ) {
		ob_flush();
		echo '<script>document.getElementById("progressbar").value="' . $percentage . '";';
		echo 'document.getElementById("number").innerHTML="' . $value . ' / ' . $max . '";';
		echo 'document.getElementById("info").innerHTML="' . $extraInfo . '";</script>';
		ob_flush();
		flush();
	}

	function textUpdate( $txt = "", $add = false, $slider = false ) {
		ob_flush();
		echo '<script>';
		if ( $add ) {
			echo 'var txt = document.getElementById("text-update").innerHTML;';
			echo 'document.getElementById("text-update").innerHTML = txt + "' . $txt . '";</script>';
		} elseif ( $slider === false ) {
			echo 'document.getElementById("text-update").innerHTML="' . $txt . '";</script>';
		}
		if ( $slider !== false ) {
			echo 'document.getElementById("info").innerHTML="' . $txt . '";</script>';
		}
		ob_flush();
		flush();
	}

	function statusUpdate( $txt = "", $paragraph = true, $icon = false ) {
		ob_flush();
		if ( $icon !== false ) {
			$txt = $txt . "&nbsp;&nbsp;&nbsp;<span uk-icon='" . $icon . "'></span>";
		}
		if ( $paragraph ) {
			$txt = '<p>' . $txt . '</p>';
		}
		echo '<script>';
		echo 'var stat = document.getElementById("status").innerHTML;';
		echo 'document.getElementById("status").innerHTML= stat + "' . $txt . '";</script>';
		ob_flush();
		flush();
		usleep( 500000 );
	}

	function showElement( $id ) {
		ob_flush();
		echo '<script>var elem = document.getElementById("' . $id . '");';
		echo 'elem.style.visibility = "visible";</script>';
		ob_flush();
		flush();
	}

	function hideElement( $id ) {
		ob_flush();
		echo '<script>var elem = document.getElementById("' . $id . '");';
		echo 'elem.style.visibility = "collapse";</script>';
		ob_flush();
		flush();
	}

	public function head( $title ) {
		$ret = '<html><head><title>' . $title . '</title><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		$ret .= '<link rel="stylesheet" href="WSDW/css/uikit.min.css" /><script src="WSDW/js/uikit.min.js"></script><script src="WSDW/js/uikit-icons.min.js"></script>';
		$ret .= '</head><body><div class="uk-container">';

		echo $ret;
	}

	function footer() {
		$ret = '</div></body></html>';
		echo $ret;
	}

	public function loadResources() {
		global $wgScript;
		$url = rtrim( $wgScript, 'index.php' );
		$dir = $url . 'extensions/WSPageSync/assets/';
		$ret = '<link rel="stylesheet" href="' . $dir . 'css/uikit.min.css" /><script src="' . $dir . 'js/uikit.min.js"></script><script src="' . $dir . 'js/uikit-icons.min.js"></script>';

		//$ret .= '<div class="uk-container">';
		return $ret;
	}

	function drawProgress( $max ) {
		$ret = '<div id="pbar" class="uk-child-width-expand@s uk-text-center " uk-grid >
    <div>
      <div class="uk-card uk-card-default uk-card-body" style="border:1px solid #ccc;">
        <div id="number" class="uk-card-badge uk-inverse uk-label-warning uk-text-center" style="width:150px;"></div>
        <h3 class="uk-card-title">Progress</h3>
        <progress id="progressbar" class="uk-progress" value="" max="' . $max . '" style="height:25px;" ></progress>
        <div id="info" class="uk-text-left uk-width-1-1"></div>
      </div>
    </div>
  </div>';

		return $ret;
	}

	/**
	 * @param string $name
	 *
	 * @return false|string
	 */
	function getTemplate( string $name ) {
		global $IP;
		$file = $IP . '/extensions/WSPageSync/assets/templates/' . $name . '.html';
		if ( file_exists( $file ) ) {
			return file_get_contents( $file );
		} else {
			return "";
		}
	}

	function renderCard( $title, $subTitle, $content, $footer, $width = '-1-1', $type = "default" ) {
		global $wgScript, $IP;
		$url      = rtrim( $wgScript, 'index.php' );
		$dir      = $url . 'extensions/WSPageSync/assets/';
		$template = $this->getTemplate( 'renderCard' );
		$search   = array(
			'%%type%%',
			'%%width%%',
			'%%url%%',
			'%%dir%%',
			'%%title%%',
			'%%subTitle%%',
			'%%content%%',
			'%%footer%%'
		);
		$replace  = array(
			$type,
			$width,
			$url,
			$dir,
			$title,
			$subTitle,
			$content,
			$footer
		);

		return str_replace( $search, $replace, $template );
	}

	function renderStatusCard( $title, $content, $width = '-1-1', $type = "default" ) {
		$template = $this->getTemplate( 'renderStatusCard' );
		$search   = array(
			'%%type%%',
			'%%width%%',
			'%%title%%',
			'%%content%%'
		);
		$replace  = array(
			$type,
			$width,
			$title,
			$content
		);

		return str_replace( $search, $replace, $template );
	}

	function renderDoQueryForm( $query ){
		$form = '<form method="post">';
		$form .= '<input type="hidden" name="wsps-action" value="wsps-import-query">';
		$form .= '<input type="hidden" name="wsps-query" value="' . base64_encode( $query ) . '">';
		$form .= '<input type="submit" class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-bottom uk-text-large" value="' . wfMessage( 'wsps-special_custom_query_add_results' )->text() . '">';
		$form   .= '</form>';
		return $form;
	}

	/**
	 * @param $data
	 * @param string $wgScript
	 *
	 * @return string
	 */
	function renderIndexPage( $data, string $wgScript ): string {
		$html = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover"><tr>';
		$html .= '<th>#</th><th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_user' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_date' )->text() . '</th>';
		$html .= '<th>' . wfMessage( 'wsps-special_table_header_sync' )->text() . '</th></tr>';
		$row  = 1;
		foreach ( $data as $page ) {
			$html   .= '<tr><td class="wsps-td">' . $row . '</td>';
			$html   .= '<td class="wsps-td"><a href="' . $wgScript . '/' . $page['pagetitle'] . '">' . $page['pagetitle'] . '</a></td>';
			$html   .= '<td class="wsps-td">' . $page['username'] . '</td>';
			$html   .= '<td class="wsps-td">' . $page['changed'] . '</td>';
			$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $page['pageid'] . '"></a>';
			$html   .= '<td class="wsps-td">' . $button . '</td>';
			$html   .= '</tr>';
			$row ++;
		}
		$html .= '</table>';
		return $html;
	}


	/**
	 * @return string
	 */
	function renderCustomQuery(): string {
		$content = '<form method="POST" class="uk-form-horizontal uk-margin-large"><div class="uk-margin">';
		$content .= '<input type="hidden" name="wsps-action" value="doQuery">';
		$content .= '<label class="uk-form-label uk-text-medium" for="wsps-query">';
		$content .= wfMessage( 'wsps-special_custom_query_card_label' )->text();
		$content .= '</label>';
		$content .= '<div class="uk-form-controls">';
		$content .= '<input class="uk-input" name="wsps-query" type="text" placeholder="';
		$content .= wfMessage( 'wsps-special_custom_query_card_placeholder' )->text();
		$content .= '">';
		$content .= '</div>';
		$footer  = '<input type="submit" class="uk-button uk-button-default" value="';
		$footer  .= wfMessage( 'wsps-special_custom_query_card_submit' )->text();
		$footer  .= '"></form>';
		$card    = $this->renderCard(
			wfMessage( 'wsps-special_custom_query_card_header' )->text(),
			wfMessage( 'wsps-special_custom_query_card_subheader' )->text(),
			$content,
			$footer
		);
		return $card;
	}

	/**
	 * @param $result
	 *
	 * @return array
	 */
	function renderDoQueryBody( $result ): array {
		$html   = '<table style="width:100%;" class="uk-table uk-table-small uk-table-striped uk-table-hover">';
		$html   .= '<thead><tr><th>#</th>';
		$html   .= '<th>' . wfMessage( 'wsps-special_table_header_page' )->text() . '</th>';
		$html   .= '<th class="uk-table-shrink">' . wfMessage( 'wsps-special_table_header_sync' )->text() . '</th>';
		$html   .= '</tr></thead><tbody>';
		$row    = 1;
		$active = 0;
		foreach ( $result as $page ) {
			$html   .= '<tr><td>' . $row . '</td>';
			$html   .= '<td><a href="/' . $page . '">' . $page . '</a></td>';
			$pageId = WSpsHooks::isTitleInIndex( $page );
			if ( $pageId !== false ) {
				$button = '<a class="wsps-toggle-special wsps-active" data-id="' . $pageId . '"></a>';
				$active ++;
			} else {
				$pageId = WSpsHooks::getPageIdFromTitle( $page );
				if ( $pageId === false || $pageId === 0 ) {
					$button = '<span class="uk-badge" style="color:white; background-color:#666;"><strong>N/A</strong></span>';
				} else {
					$button = '<a class="wsps-toggle-special" data-id="' . $pageId . '"></a>';
				}
			}
			$html .= '<td>' . $button . '</td>';
			$html .= '</tr>';
			$row ++;
		}
		$html   .= '</tbody></table>';
		return array(
			'html' => $html,
			'active' => $active
		);
	}

	/**
	 * @param $assets
	 *
	 * @return string
	 */
	function getStyle( string $assets ):string {
		$style = '<style>';
		$style .= '.wsps-td {
	        font-size:10px;
	        padding:5px;
	    }';
		$style .= '.wsps-toggle-special {
            width : 22px;
            height: 12px;
            display:inline-block;
            vertical-align:middle;
            background-image:url(' . $assets . 'off.png);
            background-size:cover;
        }';
		$style .= '.wsps-active {
        background-image:url(' . $assets . 'on.png);   
        }';
		$style .= '</style>';
		return $style;
	}

	function renderMenu( $baseUrl, $logo, $version, $active ) {
//exportcustom

		if ( $active === 3 ) {
			$item3 = '<li class="uk-active"><a href="' . $baseUrl . 'index.php/Special:WSps?action=exportcustom">' . wfMessage( 'wsps-special_menu_sync_custom_query' )->text() . '</a></li>';
		} else {
			$item3 = '<li><a href="' . $baseUrl . 'index.php/Special:WSps?action=exportcustom">' . wfMessage( 'wsps-special_menu_sync_custom_query' )->text() . '</a></li>';
		}
		if ( $active === 4 ) {
			$item4 = '<li class="uk-active"><a href="' . $baseUrl . 'index.php/Special:WSps?action=delete">' . wfMessage( 'wsps-special_menu_delete_synced_files' )->text() . '</a></li>';
		} else {
			$item4 = '<li><a href="' . $baseUrl . 'index.php/Special:WSps?action=delete">' . wfMessage( 'wsps-special_menu_delete_synced_files' )->text() . '</a></li>';
		}

		$ret = '<nav class="uk-navbar-container uk-margin" uk-navbar>
    <div class="uk-navbar-left">
	<div class="uk-navbar-item">
        <a class="uk-navbar-item uk-logo" href="' . $baseUrl . 'index.php/Special:WSps"><img src="' . $logo . '" style="height:40px"></a>

        <ul class="uk-navbar-nav" style="list-style: none;">
            ' . $item3 . $item4 . '
        </ul>
    </div>

    </div>
    
</nav>';
		$ret .= '<div class="uk-container">';
		$ret .= wfMessage( 'wsps-special_version', $version )->text();

		return $ret;
	}


}