(function (window) {
	'use strict';

	var listeners = Object.create(null);

	function on(event, handler) {
		if (!listeners[event]) {
			listeners[event] = [];
		}
		listeners[event].push(handler);
		return function off() {
			var list = listeners[event] || [];
			var i = list.indexOf(handler);
			if (i >= 0) {
				list.splice(i, 1);
			}
		};
	}

	function once(event, handler) {
		var off = on(event, function (payload) {
			off();
			handler(payload);
		});
		return off;
	}

	function emit(event, payload) {
		var list = (listeners[event] || []).slice();
		for (var i = 0; i < list.length; i++) {
			try {
				list[i](payload);
			} catch (err) {
				if (window.console && console.error) {
					console.error('[WPEP]', event, err);
				}
			}
		}
	}

	window.WPEP = window.WPEP || {};
	window.WPEP.events = {
		on: on,
		once: once,
		emit: emit
	};
})(window);
