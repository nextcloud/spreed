(function(window) {
	'use strict';

	// TODO(leon): Make answerRequest only call affected handler

	var intReqId = "__int_pm_id";
	var PostMessageAPI = function(config) {
		// Bind polyfill
		if (!Function.prototype.bind) {
			Function.prototype.bind = function(that) {
				var context = this;
				var s = Array.prototype.slice;
				var sc = s.call(arguments, 1);
				return function() {
					return context.apply(that, sc.concat(s.call(arguments)));
				}
			};
		}

		var IN_IFRAME = (function() {
			try {
				return window.self !== window.top;
			} catch (e) {
				return true;
			}
		})();

		this.parent = IN_IFRAME ? config.parent : null;
		this.iframe = config.iframe;
		this.popup = config.popup;
		this.opener = config.opener;
		this.allowedPartners = config.allowedPartners;
		this.partnerOrigin = null;
		this.listeners = [];
		this.namedEvents = {
			CHILD_READY: "ready",
		};
		this.eventListener = this.gotEvent.bind(this); // To stay in context
		this.postQueue = [];
		this.receivedInitialEvent = false;

		this.init();
	};
	PostMessageAPI.prototype.log = function(message) {
		var args = Array.prototype.slice.call(arguments);
		args.unshift("PostMessageAPI:");
		console.log.apply(console, args);
	};
	PostMessageAPI.prototype.init = function() {
		// Bind listener to notify child about queued events
		if (!this.isChild()) {
			var childReadyListener = function() {
				this.unbind(childReadyListener);
				var obj;
				while (obj = this.postQueue.shift()) {
					this.log("Posting queued event", obj);
					this.post(obj);
				}
			}.bind(this);
			this.bind(childReadyListener);
		}
	};
	PostMessageAPI.prototype.post = function(obj) {
		if (!this.isChildReady()) {
			this.postQueue.push(obj);
			this.log("Deferring post as child is not ready yet", obj);
			return;
		}
		this.log("Posting from", document.location, "to", (this.partnerOrigin || this.allowedPartners[0]), obj);
		var pw = this.getPartnerWindow();
		if (pw) {
			pw.postMessage(obj, this.partnerOrigin || this.allowedPartners[0]);
		}
	};
	PostMessageAPI.prototype.requestResponse = function(data, cb) {
		data[intReqId] = data.type + ":" + (new Date()).getTime();

		var listener = function(event) {
			if (event.data[intReqId] === data[intReqId]) {
				this.unbind(listener);
				delete event.data[intReqId];
				cb(event.data);
			}
		}.bind(this);
		this.bind(listener);
		this.post(data);
	};
	PostMessageAPI.prototype.answerRequest = function(request, data) {
		// Create clone
		request = request.data;
		data[intReqId] = request[intReqId];
		this.post(data);
	};
	PostMessageAPI.prototype.getPartnerWindow = function() {
		var pw = null;
		if (this.parent) {
			pw = this.parent;
		} else if (this.iframe) {
			pw = this.iframe.contentWindow;
		} else if (this.popup) {
			pw = this.popup;
		} else if (this.opener) {
			pw = this.opener;
		}
		if (pw === null) {
			this.log("Found no partner window");
		}
		return pw;
	};
	PostMessageAPI.prototype.gotEvent = function(event) {
		if (!this.validateEvent(event)) {
			this.log("Received untrusted event", event);
			return;
		}
		this.receivedInitialEvent = true;
		if (!this.partnerOrigin) {
			this.partnerOrigin = event.origin;
		}
		this.log("Received event", event);
		for (var i = 0, l = this.listeners.length; i < l; i++) {
			var listener = this.listeners[i];
			if (listener) {
				listener(event);
			}
		}
	};
	PostMessageAPI.prototype.validateEvent = function(event) {
		var valid = true;
		if (this.partnerOrigin && event.origin !== this.partnerOrigin) {
			valid = false;
		} else if (!Array.isArray(this.allowedPartners) || this.allowedPartners.indexOf(event.origin) === -1) {
			valid = false;
		} else if (event.source !== this.getPartnerWindow()) {
			valid = false;
		}
		return valid;
	};
	PostMessageAPI.prototype.bind = function(fnct) {
		var firstListener = !this.listeners[0];
		this.listeners.push(fnct);
		if (firstListener) {
			window.addEventListener("message", this.eventListener, false);
		}
	};
	PostMessageAPI.prototype.unbind = function(fnct) {
		for (var i = 0, l = this.listeners.length; i < l; i++) {
			var listener = this.listeners[i];
			if (listener === fnct) {
				this.listeners.splice(i, 1);
			}
		}
	};
	PostMessageAPI.prototype.unbindAll = function() {
		window.removeEventListener("message", this.eventListener, false);
		this.listeners = [];
	};
	PostMessageAPI.prototype.isChild = function() {
		return !(this.iframe || this.popup);
	};
	PostMessageAPI.prototype.isChildReady = function() {
		if (this.isChild()) {
			// Simply return true if we're the child
			return true;
		}
		return this.receivedInitialEvent;
	};
	PostMessageAPI.prototype.childReady = function() {
		if (this.isChild()) {
			this.post({type: this.namedEvents.CHILD_READY});
		}
	};

	if (typeof define === "function" && define.amd) {
		define(function() {
			return PostMessageAPI;
		});
	} else {
		window.PostMessageAPI = PostMessageAPI;
	}

})(window);
