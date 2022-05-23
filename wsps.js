$(function () {
	let windowManager,
		tagsDialog;

	/**
	 * on Special backup page download button has been pressed
	 */
	$('.wsps-download-backup').click(function (e) {
		e.stopPropagation()
		var lnk = $(this)

		// download it!
		var url = window.location.href
		var backupFile = lnk.attr('data-id')
		var form = $('<form action="' + url + '" method="post">' +
			'<input type="hidden" name="wsps-action" value="download-backup">' +
			'<input type="hidden" name="ws-backup-file" value="' + backupFile + '"></form>')
		$('body').append(form)
		form.submit()

	})

	/**
	 * on Special Share page download button has been pressed
	 */
	$('.wsps-download-share').click(function (e) {
		e.stopPropagation()
		var lnk = $(this)

		// download it!
		var url = window.location.href
		var backupFile = lnk.attr('data-id')
		var form = $('<form action="' + url + '" method="post">' +
			'<input type="hidden" name="wsps-action" value="download-share">' +
			'<input type="hidden" name="ws-share-file" value="' + backupFile + '"></form>')
		$('body').append(form)
		form.submit()

	})

	/**
	 * on Special Share page delete button has been pressed
	 */
	$('.wsps-delete-share').click(function (e) {
		e.stopPropagation()
		var lnk = $(this)
		if (confirm(mw.msg('wsps-javascript_delete_share_text'))) {
			// delete it!
			var url = window.location.href
			var backupFile = lnk.attr('data-id')
			var form = $('<form action="' + url + '" method="post">' +
				'<input type="hidden" name="wsps-action" value="delete-share">' +
				'<input type="hidden" name="ws-share-file" value="' + backupFile + '"></form>')
			$('body').append(form)
			form.submit()
		}
	})

	/**
	 * on Special backup page delete button has been pressed
	 */
	$('.wsps-delete-backup').click(function (e) {
		e.stopPropagation()
		var lnk = $(this)
		if (confirm(mw.msg('wsps-javascript_delete_backup_text'))) {
			// delete it!
			var url = window.location.href
			var backupFile = lnk.attr('data-id')
			var form = $('<form action="' + url + '" method="post">' +
				'<input type="hidden" name="wsps-action" value="delete-backup">' +
				'<input type="hidden" name="ws-backup-file" value="' + backupFile + '"></form>')
			$('body').append(form)
			form.submit()
		}
	})

	/**
	 * on Special backup page restore button has been pressed
	 */
	$('.wsps-restore-backup').click(function (e) {
		e.stopPropagation()
		var lnk = $(this)
		if (confirm(mw.msg('wsps-javascript_restore_backup_text'))) {
			// restore it!
			var url = window.location.href
			var backupFile = lnk.attr('data-id')
			var form = $('<form action="' + url + '" method="post">' +
				'<input type="hidden" name="wsps-action" value="restore-backup">' +
				'<input type="hidden" name="ws-backup-file" value="' + backupFile + '"></form>')
			$('body').append(form)
			form.submit()
		}
	})

	/**
	 * When sysop clicks slider on top of a page
	 */
	$('.wsps-toggle').click(function (e) {
		e.stopPropagation()
		var button = $(this)
		if (button.hasClass('wsps-error')) {
			mw.notify($('<span style="color:red;">' + mw.msg('wsps-error_special-page') + '</span>'), { title: mw.msg('wsps-api-error-no-config-title') })
			return
		}
		if (button.hasClass('wsps-notice')) {
			window.location.href = mw.config.get('wgArticlePath').replace('$1', '') + 'Special:WSPageSync'
			return
		}
		var id = mw.config.get('wgArticleId')
		var user = getUserName()
		if (button.hasClass('wsps-active')) {
			wspsPost(id, user, 'remove')
			button.removeClass('wsps-active ')
			$('#ca-wspst').addClass('wspst-hide');
		} else {
			button.addClass('wsps-active ')
			$('#ca-wspst').removeClass('wspst-hide');
			wspsPost(id, user, 'add')
		}
	})

	/**
	 * When sysop clicks slider on top of a page
	 */
	$('.wspst-toggle').click(function (e) {
		e.stopPropagation()
		var button = $(this)
		var id = mw.config.get('wgArticleId')
		var user = getUserName()

		// open modal and get the tags
		wspsTags( id, user, 'gettags' ).done( function( data ) {
			console.log( data );
			if ( data.wsps.result.status === 'ok' ) {
				const { pagetags } = data.wsps.result.tags;
				const { alltags } = data.wsps.result.tags;
				createDialogForTags( pagetags, alltags );
			} else {
				const pagetags = [];
				const { alltags } = data.wsps.result.tags;
				createDialogForTags( pagetags, alltags );
			}
		});
	});


	/**
	 * When sysop clicks slider on the Special Page
	 */
	$('.wsps-toggle-special').click(function (e) {
		e.stopPropagation()
		var button = $(this)
		var id = $(this).attr('data-id')
		var user = getUserName()
		if (button.hasClass('wsps-active')) {
			wspsPost(id, user, 'remove')
			button.removeClass('wsps-active ')
		} else {
			button.addClass('wsps-active ')
			wspsPost(id, user, 'add')
		}
	});

	if ($('.wspst-toggle').length > 0) {
		function MyDialog( config ) {
			MyDialog.super.call( this, config );
		}

		OO.inheritClass( MyDialog, OO.ui.Dialog );
		MyDialog.static.name = 'wspst-dialog';
		MyDialog.static.title = 'WS PageSync tags dialog';

		MyDialog.prototype.initialize = function() {
			MyDialog.super.prototype.initialize.call( this );
			this.content = new OO.ui.PanelLayout( {
				padded: true,
				expanded: false
			});
			this.$body.append(this.content.$element);
		};

		MyDialog.prototype.getBodyHeight = function() {
			return 400;
		};

		tagsDialog = new MyDialog({
			size: 'medium'
		});

		windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );

		windowManager.addWindows([tagsDialog]);
	}


	function createDialogForTags ( tags, allTags ) {
		$(tagsDialog.content.$element[0]).html('');
		console.log( tags, allTags );
		var options = [];
		$.each(allTags, function( k, v ){
			if (v) options.push({ data: v, label: v, icon: 'tag' });
		});
		console.log( options );
		console.log( tags );
		let comboBox = new OO.ui.MenuTagMultiselectWidget({
			inputPosition: 'outline',
			options: options,
			allowArbitrary: true,
		});
		comboBox.setValue(tags);
		let submitButton = new OO.ui.ButtonInputWidget({
			label: 'submit',
			flags: [
				'primary',
				'progressive'
			]
		});

		let cancelButton = new OO.ui.ButtonInputWidget({
			label: 'cancel',
			flags: [
				'secondary'
			]
		});

		$(cancelButton.$element).click(function() {
			windowManager.closeWindow(tagsDialog);
		});

		let labelWidget = new OO.ui.LabelWidget({
			label: mw.msg('wsps-page-tags-label')
		});
		$(submitButton.$element).click(function() {
			let newTags = [];
			for (let i = 0; i < comboBox.items.length; i++) {
				newTags.push(comboBox.items[i].data);
			}

			wspsTags(mw.config.get('wgArticleId'), getUserName(), 'updatetags', newTags.join(',')).done(function(data) {
				if ( data.wsps.result.status === 'ok') {
					mw.notify(mw.msg('wsps-page-tags-added'), { 'title': mw.msg('wsps'), 'type': 'success' });
					windowManager.closeWindow(tagsDialog);
				} else {
					console.error(data.wsps.result.message);
					mw.notify(mw.msg('wsps-page-tags-error-adding'), { 'title': mw.msg('wsps'), 'type': 'error' });
				}
			});
		});
		$(tagsDialog.content.$element[0]).append(labelWidget.$element);
		$(tagsDialog.content.$element[0]).append(comboBox.$element);
		$(tagsDialog.content.$element[0]).append(cancelButton.$element);
		$(tagsDialog.content.$element[0]).append(submitButton.$element);
		windowManager.openWindow(tagsDialog);
	}
})

function getUserName () {
	return mw.config.get('wgUserName')
}

/**
 * @param {int} id (page id)
 * @param {string} un (username)
 * @param {string} type (what action to perform)
 */
function wspsPost (id, un, type) {
	new mw.Api().postWithToken('csrf', {
		action: 'wsps',
		format: 'json',
		what: type,
		pageId: id,
		user: un
	}).done(function (data) {
		if (data.wsps.result.status === 'ok') {
			if (type === 'remove') {
				mw.notify(mw.msg('wsps-page-removed'), { 'title': mw.msg('wsps'), 'type': 'success' })
			}
			if (type === 'add') {
				mw.notify(mw.msg('wsps-page-added'), { 'title': mw.msg('wsps'), 'type': 'success' })
			}
		} else {
			mw.notify($('<span style="color:red;">' + data.wsps.result.message + '</span>'), { title: mw.msg('wsps-api-error-no-config-title') })
		}
	})
}

/**
 *
 * @param id {int}
 * @param un {string}
 * @param type {string}
 * @param tags {string}
 * @returns {Object}
 */
function wspsTags (id, un, type, tags = '') {
	return new mw.Api().postWithToken('csrf', {
		action: 'wsps',
		format: 'json',
		what: type,
		pageId: id,
		user: un,
		tags: tags
	});
}

