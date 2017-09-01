// TODO(fancycode): Should load through AMD if possible.
/* global OCA */

// require(['underscore', 'jquery', 'postmessage', presentation/consts', 'presentation/type-pdf'])
// Even though we list 'OCA' here, it's a totally reduced version of it which only includes stuff we need to display presentations
// This will hopefully go away once we support AMD
(function(OCA, _, $) {
	'use strict';

	// Import list
	// This will hopefully go away once we support AMD
	var PDFPresentation = OCA.SpreedMe.Presentation.PDFPresentation;
	var consts = OCA.SpreedMe.Presentation.consts;

	var PresentationManager = function(rootElem) {
		this.rootElem = rootElem;
		this.EVENTS = {
			PAGE_NEXT: _.bind(function(p) {
				var next = p.curPage + 1;
				if (p.isController && p.numPages >= next) {
					this.postMessage.post({type: consts.EVENT_TYPES.PAGE, payload: next});
				}
			}, this),
			PAGE_PREVIOUS: _.bind(function(p) {
				var next = p.curPage - 1;
				if (p.isController && 0 < next) {
					this.postMessage.post({type: consts.EVENT_TYPES.PAGE, payload: next});
				}
			}, this),
		};

		this.EVENT_HANDLERS = {
			add: _.bind(function(token, url, from) {
				var deferred = $.Deferred();
				var p = new PDFPresentation(/* id */token, token, url);
				// TODO(leon): from === null means the event is from ourself, see other comment
				// This should change, see other comment: "Replace null by own Peer object"
				p.allowControl(from === null);
				this.add(token, p);
				deferred.resolve(p); // TODO(leon): Make the this.add call return a promise instead
				return deferred.promise();
			}, this),
			current: _.bind(function() {
				var receivedCurrent = false;
				return _.bind(function(token, url, page, from) {
					if (receivedCurrent) {
						// Ignore duplicate current event
						console.log("Ignoring 'current' event as we have already processed it");
						return;
					}
					receivedCurrent = true;
					_.bind(this.EVENT_HANDLERS.add, this)(token, url, from).then(function(p) {
						// TODO(leon): This is bad. We should use 'exactPage' instead
						// See other comment »this.curPage might already be set to something != 1«
						p.curPage = page;
					}); // TODO(leon): Might want to catch as well
				}, this);
			}, this)(),
			remove: _.bind(function(id) {
				this.removeById(id);
			}, this),
			switchTo: _.bind(function(id) {
				this.showById(id);
			}, this),
			page: _.bind(function(page) {
				this.withActive(function(p) {
					p.exactPage(page);
				});
			}, this),
		};

		this.active = null;
		this.staging = null;
		this.byId = {};

		// Bind some event handlers
		(_.bind(function() {
			var evt = 'keydown.' + consts.EVENT_NAMESPACE;
			$(document).off(evt).on(evt, _.bind(function(e) {
				switch (e.keyCode) {
				case 37: // Left arrow
					this.withActive(_.bind(function(p) {
						this.EVENTS.PAGE_PREVIOUS(p);
					}, this));
					break;
				case 39: // Right arrow
					this.withActive(_.bind(function(p) {
						this.EVENTS.PAGE_NEXT(p);
					}, this));
					break;
				}
			}, this));
		}, this))();

		this.postMessage = new PostMessageAPI({
			allowedPartners: [document.location.origin],
			parent: window.parent,
		});

		this.postMessage.bind(_.bind(function(event) {
			// Handle events, pass any unknown directly to the presentation manager
			var data = event.data;
			// TODO(leon): This is not a very clever way to pass 'from'..
			var from = event.data.from;
			if (from) {
				delete event.data.from;
			}
			switch (data.type) {
			case consts.EVENT_TYPES.POSTMESSAGE_REQ_CURRENT:
				var state = this.getCurrentState();
				if (state) {
					this.postMessage.answerRequest(event, state);
				}
				break;
			default:
				this.handleEvent(data, from);
			}
		}, this));
		// We're ready, say hello to parent
		this.postMessage.childReady();
	};
	PresentationManager.prototype.withActive = function(cb) {
		if (this.active) {
			cb(this.active);
		}
	};
	PresentationManager.prototype.init = function(id, p) {
		this.byId[id] = p;
		var c = document.createElement("canvas");
		c.id = "presentation_" + id;
		p.elem = c;
		p.elem.addEventListener("click", _.bind(function(e) {
			var half = (p.elem.offsetWidth / 2);
			if (e.offsetX > half) {
				this.EVENTS.PAGE_NEXT(p);
			} else {
				this.EVENTS.PAGE_PREVIOUS(p);
			}
		}, this), true);
		this.hide(p);
		this.rootElem.appendChild(c);
	};
	PresentationManager.prototype.add = function(id, p) {
		if (!this.byId.hasOwnProperty(id)) {
			// We don't have this presentation yet
			this.init(id, p);
		} else {
			// Reuse existing presentation
			p = this.byId[id];
		}
		// TODO(leon): Remove 'true' and add presentation selector instead
		if (true || !this.active) {
			console.log("Showing presentation", p);
			this.show(p);
		}
	};
	PresentationManager.prototype.removeById = function(id) {
		if (!this.byId.hasOwnProperty(id)) {
			console.log("Remove: Unknown ID", id);
			return;
		}
		var p = this.byId[id];
		if (p === this.active) {
			p.hide();
		}
		p.elem.parentNode.removeChild(p.elem);
		delete this.byId[id];
	};
	PresentationManager.prototype.removeAll = function() {
		for (var id in this.byId) {
			if (this.byId.hasOwnProperty(id)) {
				this.removeById(id);
			}
		}
	};
	PresentationManager.prototype.show = function(p) {
		if (p === this.active) {
			// Presentation is already active, do nothing
			return;
		}
		this.staging = p;
		p.load(_.bind(function() {
			// Check if we still want to show this presentation, migth have changed since
			if (this.staging !== p) {
				return;
			}
			this.staging = null;
			if (this.active) {
				this.hide(this.active);
			}
			this.active = p;
			this.active.elem.classList.remove("hidden");
		}, this));
	};
	PresentationManager.prototype.showById = function(id) {
		if (!this.byId.hasOwnProperty(id)) {
			// TODO(leon): Handle error
			return;
		}
		this.show(this.byId[id]);
	};
	PresentationManager.prototype.hide = function(p) {
		if (p === this.active) {
			// TODO(leon): We should simply show one of the next presentation
			this.active = null;
		}
		p.elem.classList.add("hidden");
	};
	PresentationManager.prototype.handleEvent = function(data, from) {
		var callHandler = _.bind(function() {
			var args = Array.prototype.slice.call(arguments);
			var fn = args.shift();
			fn.apply(this, args);
		}, this);

		switch (data.type) {
		case consts.EVENT_TYPES.PRESENTATION_CURRENT:
			callHandler(this.EVENT_HANDLERS.current, data.payload.token, data.payload.url, data.payload.page, from);
			break;
		case consts.EVENT_TYPES.PRESENTATION_ADDED:
			callHandler(this.EVENT_HANDLERS.add, data.payload.token, data.payload.url, from);
			break;
		case consts.EVENT_TYPES.PRESENTATION_REMOVED:
			callHandler(this.EVENT_HANDLERS.remove, data.payload, from);
			break;
		case consts.EVENT_TYPES.PRESENTATION_SWITCH:
			callHandler(this.EVENT_HANDLERS.switchTo, data.payload, from);
			break;
		case consts.EVENT_TYPES.PAGE:
			callHandler(this.EVENT_HANDLERS.page, data.payload, from);
			break;
		default:
			console.log("Unknown presentation event '%s':", data.type, data.payload);
		}
	};
	PresentationManager.prototype.getCurrentState = function() {
		var payload = null;
		this.withActive(function(p) {
			payload = {
				token: p.token,
				page: p.curPage,
			};
		});
		return payload;
	};

	var rootElem = document.getElementById("presentations");
	var pm = new PresentationManager(rootElem);

})(OCA, _, $);
