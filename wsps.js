//alert('ola');
$(function() {
	$('.wsps-toggle').click(function (e) {
		e.stopPropagation();
		var button = $(this);
		var id = mw.config.get('wgArticleId');
		var user = mw.user.getName();
		if (button.hasClass("wsps-active")) {
			wspsPost(id, user, 'remove');
			button.removeClass("wsps-active ");
		} else {
			button.addClass("wsps-active ");
			wspsPost(id, user, 'add');
		}
	});
	$('.wsps-toggle-special').click(function (e) {
		e.stopPropagation();
		var button = $(this);
		var id = $(this).attr("data-id");
		var user = mw.user.getName();
		if (button.hasClass("wsps-active")) {
			wspsPost(id, user, 'remove');
			button.removeClass("wsps-active ");
		} else {
			button.addClass("wsps-active ");
			wspsPost(id, user, 'add');
		}
	});
});

function wspsPost( id, un, type ) {
	new mw.Api().postWithToken( 'csrf', {
		action: 'wsps',
		format: 'json',
		what: type,
		pageId: id,
		user : un
	} ).done( function( data ) {
		if( data.wsps.result.status === 'ok' ) {
			if( type === 'remove' ) {
				mw.notify('Page removed from sync');
			}
			if( type === 'add' ) {
				mw.notify('Page added for sync');
			}
			//console.log( "successfully save " + data.wsps.result.page.fname );
		} else {
			mw.notify( $( '<span style="color:red;">' + data.wsps.result.message + '</span>' ), { title: 'ERROR' } );
		}
	} );
}