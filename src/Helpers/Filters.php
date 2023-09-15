<?php

namespace PageSync\Helpers;

class Filters {

	public function JavaScriptMainPageFilter() {
		$searchField = PHP_EOL . '<div class="uk-inline uk-float-right uk-margin-bottom">';
		$searchField .= '<a class="uk-form-icon uk-form-icon-flip" href="" uk-icon="icon: search"></a>';
		$searchField .= '<input class="uk-input" type="search" id="filterTableSearch" placeholder="Search synced files">';
		$searchField .= '</div>' . PHP_EOL;
		$js = "<script>document.getElementById( 'filterTableSearch' ).addEventListener( 'keyup', function() {
		   let search = document.getElementById( 'filterTableSearch' ).value.toUpperCase();
           filterTable( search, 'PSindexTable' );
		} );</script>";
		return $searchField . $js;
	}

	public function renderIndexOptions( PSRender $render ) : string {
		/*
		$search  = [
			'%%baseUrl%%',
			'%%logo%%',
			'%%item1class%%',
			'%%item2class%%',
			'%%item3class%%',
			'%%item4class%%',
			'%%wsps-special_menu_sync_custom_query%%',
			'%%wsps-special_menu_backup_files%%',
			'%%wsps-special_menu_share_files%%',
			'%%wsps-special_menu_clean-up%%'
		];
		$replace = [
			$baseUrl,
			$logo,
			$item1class,
			$item2class,
			$item3class,
			$item4class,
			wfMessage( 'wsps-special_menu_sync_custom_query' )->text(),
			wfMessage( 'wsps-special_menu_backup_files' )->text(),
			wfMessage( 'wsps-special_menu_share_files' )->text(),
			wfMessage( 'wsps-special_menu_clean-up' )->text()
		];

		$ret = str_replace(
			$search,
			$replace,
			$this->getTemplate( 'renderMenu' )
		);
		*/
		$ret = $render->getTemplate( 'renderCleanIndex' );

		return $ret;
	}



}