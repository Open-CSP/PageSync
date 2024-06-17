/**
 *
 * @param searchText string
 * @param tableId string
 */
function filterTable( searchText, tableId ) {
	searchText = searchText.toUpperCase();
	let table = document.getElementById( tableId );
	let tr = table.getElementsByTagName( "tr" );

	// Loop through all table rows, and hide those who don't match the search query
	for ( let i = 0; i < tr.length; i++) {
		let td = tr[i].getElementsByTagName( "td" )[1];
		if ( td ) {
			let txtValue = td.textContent || td.innerText;
			if ( txtValue.toUpperCase().indexOf( searchText ) > -1) {
				tr[i].style.display = "";
			} else {
				tr[i].style.display = "none";
			}
		}
	}
}

/**
 *
 * @param searchText string
 * @param tableId string
 */
function PSfilterNSTable( searchText, tableId ) {
	searchText = searchText.toUpperCase();
	let table = document.getElementById( tableId );
	let tr = table.getElementsByTagName( "tr" );

	// Loop through all table rows, and hide those who don't match the search query
	for ( let i = 0; i < tr.length; i++) {
		let tdTitle = tr[i].getElementsByTagName( "td" )[2];
		let tdInput = tr[i].getElementsByTagName( "td" )[0];
		if ( tdTitle ) {
			let txtValue = tdTitle.textContent || tdTitle.innerText;
			let inputObj = $(tdInput).find("input");
			if ( txtValue.toUpperCase().indexOf( searchText ) > -1) {
				tr[i].style.display = "";
				$(inputObj).prop( "disabled", false );
			} else {
				tr[i].style.display = "none";
				$(inputObj).prop( "disabled", true );
			}
		}
	}
}