$(function() {


	/**
	 * on Special backup page delete button has been pressed
	 */
	$('.wsps-delete-backup').click( function (e) {
		e.stopPropagation();
		var lnk = $( this );
		if ( confirm( mw.msg( 'wsps-javascript_delete_backup_text' ) ) ) {
			// delete it!
			var url = window.location.href;
			var backupFile = lnk.attr('data-id');
			var form = $('<form action="' + url + '"method="post">' +
				'<input type="hidden" name="wsps-action" value="delete-backup">' +
				'<input type="hidden" name="ws-backup-file" value="' + backupFile + '"></form>' );
				$('body').append(form);
				form.submit();
		}
	});

	/**
	 * on Special backup page restore button has been pressed
	 */
	$('.wsps-restore-backup').click( function (e) {
		e.stopPropagation();
		var lnk = $( this );
		if ( confirm( mw.msg( 'wsps-javascript_restore_backup_text' ) ) ) {
			// restore it!
			var url = window.location.href;
			var backupFile = lnk.attr('data-id');
			var form = $('<form action="' + url + '"method="post">' +
				'<input type="hidden" name="wsps-action" value="restore-backup">' +
				'<input type="hidden" name="ws-backup-file" value="' + backupFile + '"></form>' );
			$('body').append(form);
			form.submit();
		}
	});


	/**
	 * When sysop clicks slider on top of a page
	 */
	$('.wsps-toggle').click(function (e) {
		e.stopPropagation();
		var button = $(this);
		if( button.hasClass("wsps-error" ) ) {
			$( '<span style="color:red;">' + mw.msg( 'wsps-error_special-page' ) + '</span>' ), { title: 'ERROR' }
			return;
		}
		if( button.hasClass("wsps-notice" ) ) {
			window.location.href = mw.config.get("wgArticlePath").replace('$1', '') + 'Special:WSPageSync';
			return;
		}
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
	/**
	 * When sysop clicks slider on the Special Page
	 */
	$('.wsps-toggle-special').click(function(e) {
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

/**
 * @param {int} id (page id)
 * @param {string} un (username)
 * @param {string} type (what action to perform)
 */
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
				mw.notify( mw.msg( 'wsps-page-removed' ) );
			}
			if( type === 'add' ) {
				mw.notify( mw.msg( 'wsps-page-added' ) );
			}
		} else {
			mw.notify( $( '<span style="color:red;">' + data.wsps.result.message + '</span>' ), { title: 'ERROR' } );
		}
	} );
}