function setSelect2() {
	var selectTwee = mw.config.get('wgScript');
	selectTwee = selectTwee.replace( 'index.php', '' );
	selectTwee = selectTwee + 'extensions/PageSync/assets/js/select2/select2.min.js',
		$.getScript( selectTwee ).done(function () {
			$("#ps-tags").select2({ "tags": true });
		})
}

/**
 * Holds further JavaScript execution intull jQuery is loaded
 * @param method string Name of the method to call once jQuery is ready
 * @param both bool if true it will also wait until MW is loaded.
 */
function wachtff (method, both = false) {
	//console.log('wacht ff op jQuery..: ' + method.name );
	if (window.jQuery) {
		if (both === false) {
			//console.log( 'ok JQuery active.. lets go!' );
			method()
		} else {
			// console.log('wacht ff op jQuery.ui..');
			if (window.mw) {
				var scriptPath = mw.config.get('wgScript')
				if (scriptPath !== null && scriptPath !== false) {
					method()
				} else {
					setTimeout(function () {
						wachtff(method, true)
					}, 250)
				}
			} else {
				setTimeout(function () {
					wachtff(method, true)
				}, 250)
			}
		}
	} else {
		setTimeout(function () {
			wachtff(method)
		}, 50)
	}
}

document.addEventListener("DOMContentLoaded", function() {
	wachtff(setSelect2);
});