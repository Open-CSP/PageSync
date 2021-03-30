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

    function progress( $percentage, $value, $max, $extraInfo="" ) {
		ob_flush();
		echo '<script>document.getElementById("progressbar").value="' . $percentage . '";';
		echo 'document.getElementById("number").innerHTML="' . $value . ' / ' . $max . '";';
		echo 'document.getElementById("info").innerHTML="' . $extraInfo . '";</script>';
		ob_flush();
		flush();
	}

	function textUpdate( $txt="", $add=false, $slider=false  ) {
        ob_flush();
		echo '<script>';
		if($add) {
			echo 'var txt = document.getElementById("text-update").innerHTML;';
			echo 'document.getElementById("text-update").innerHTML = txt + "' . $txt . '";</script>';
		} elseif ($slider === false ) {
			echo 'document.getElementById("text-update").innerHTML="' . $txt . '";</script>';
		}
		if( $slider !== false ) {
			echo 'document.getElementById("info").innerHTML="' . $txt . '";</script>';
		}
		ob_flush();
		flush();
	}

	function statusUpdate( $txt="", $paragraph=true, $icon=false ) {
		ob_flush();
		if( $icon !== false ) {
			$txt = $txt . "&nbsp;&nbsp;&nbsp;<span uk-icon='".$icon."'></span>";
		}
		if($paragraph) {
			$txt = '<p>' . $txt . '</p>';
		}
		echo '<script>';
		echo 'var stat = document.getElementById("status").innerHTML;';
		echo 'document.getElementById("status").innerHTML= stat + "' . $txt . '";</script>';
		ob_flush();
		flush();
		usleep(500000);
	}

	function showElement($id) {
		ob_flush();
		echo '<script>var elem = document.getElementById("'.$id.'");';
		echo 'elem.style.visibility = "visible";</script>';
		ob_flush();
		flush();
	}

	function hideElement($id) {
		ob_flush();
		echo '<script>var elem = document.getElementById("'.$id.'");';
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

	function renderCard( $title, $subTitle, $content, $footer, $width='-1-1', $type="default" ) {
        global $wgScript;
        $url = rtrim( $wgScript, 'index.php' );
        $dir = $url . 'extensions/WSPageSync/assets/';
		$ret = '<div uk-grid class="uk-width-1-1 uk-margin-large-top"><div class="uk-margin-large-top uk-card uk-card-'.$type.' uk-width'.$width.'" >
    <div class="uk-card-header">
        <div class="uk-grid-small uk-flex-middle" uk-grid>
            <div class="uk-width-auto">
                <a href="'.$url.'index.php/Special:WSps"><img style="height:40px;" src="'. $dir.'images/wspagesync.png"></a>
            </div>
            <div class="uk-width-expand">
                <h3 class="uk-card-title uk-margin-remove-bottom">'.$title.'</h3>
                <p class="uk-text-meta uk-margin-remove-top">'.$subTitle.'</p>
            </div>
        </div>
    </div>
    <div class="uk-card-body">
        '.$content.'
    </div>
    <div class="uk-card-footer">
        '.$footer.'
    </div>
</div></div>';
		return $ret;
	}

	function renderStatusCard( $title,$content, $width='-1-1', $type="default" ) {
		$ret = '<div uk-grid class="uk-width-1-1"><div class="uk-card uk-card-'.$type.' uk-position-right uk-width'.$width.' uk-text-right uk-padding-small" >
        <h3 class="uk-card-title uk-text-right">'.$title.'</h3>
        <div id="status" class="uk-text-right">'.$content.'</div></div>';
		return $ret;
	}

	function renderMenu( $baseUrl, $logo, $version, $active ) {
//exportcustom
    	if( $active === 1 ) {
		    $item1 = '<li class="uk-active" ><a href="' . $baseUrl . 'index.php/Special:WSps?action=listmanaged">' . wfMessage( 'wsps-special_menu_list_managed_pages' )->text() . '</a></li>';
	    } else {
		    $item1 = '<li><a href="' . $baseUrl . 'index.php/Special:WSps?action=listmanaged">' . wfMessage( 'wsps-special_menu_list_managed_pages' )->text() . '</a></li>';
	    }
		if( $active === 2 ) {
			$item2 = '<li class="uk-active"><a href="' . $baseUrl . 'index.php/Special:WSps?action=importmanaged">' . wfMessage( 'wsps-special_menu_sync_all_managed_pages' )->text() . '</a></li>';
		} else {
			$item2 = '<li><a href="' . $baseUrl . 'index.php/Special:WSps?action=importmanaged">' . wfMessage( 'wsps-special_menu_sync_all_managed_pages' )->text() . '</a></li>';
		}
		if( $active === 3 ) {
			$item3 = '<li class="uk-active"><a href="' . $baseUrl . 'index.php/Special:WSps?action=exportcustom">' . wfMessage( 'wsps-special_menu_sync_custom_query' )->text() . '</a></li>';
		} else {
			$item3 = '<li><a href="' . $baseUrl . 'index.php/Special:WSps?action=exportcustom">' . wfMessage( 'wsps-special_menu_sync_custom_query' )->text() . '</a></li>';
		}
        if( $active === 4 ) {
            $item4 = '<li class="uk-active"><a href="' . $baseUrl . 'index.php/Special:WSps?action=delete">' . wfMessage( 'wsps-special_menu_delete_synced_files' )->text() . '</a></li>';
        } else {
            $item4 = '<li><a href="' . $baseUrl . 'index.php/Special:WSps?action=delete">' . wfMessage( 'wsps-special_menu_delete_synced_files' )->text() . '</a></li>';
        }

    	$ret = '<nav class="uk-navbar-container uk-margin" uk-navbar>
    <div class="uk-navbar-left">
	<div class="uk-navbar-item">
        <a class="uk-navbar-item uk-logo" href="'.$baseUrl.'index.php/Special:WSps"><img src="'.$logo.'" style="height:40px"></a>

        <ul class="uk-navbar-nav" style="list-style: none;">
            '.$item1.$item2.$item3.$item4.'
        </ul>
    </div>

    </div>
    
</nav>';
    		$ret .= '<div class="uk-container">' ;
    		$ret .=  wfMessage( 'wsps-special_version', $version )->text();
    	return $ret;
	}


}