// TODO(fancycode): Should load through AMD if possible.
/* global OC, OCA */

(function(OCA, OC, $) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	var Presentation = function(id, url) {
		this.id = id;
		this.url = url;
		this.elem = null;
		this.numPages = 0;
		this.curPage = 1;
		this.scale = 1;
		this.e = $({});
		this.e.byName = {
			LOAD: "load",
			PAGE_UPDATED: "page.updated",
			RENDERING_DONE: "rendering.done"
		};
	};
	Presentation.prototype.isLoaded = function() {
		throw 'isLoaded not implemented yet';
	};
	Presentation.prototype.exactPage = function(num) {
		if (this.curPage === num || num <= 0 || num >= this.numPages) {
			return;
		}
		this.curPage = num;
		this.e.trigger(this.e.byName.PAGE_UPDATED, this.curPage);
	};

	var PDFPresentation = function(id, url) {
		Presentation.call(this, id, url);
		this.isRendering = false;
		var evs = [this.e.byName.LOAD, this.e.byName.PAGE_UPDATED];
		this.e.on(evs.join(" "), _.bind(this.render, this));
	};
	PDFPresentation.prototype = Object.create(Presentation.prototype);
	PDFPresentation.prototype.isLoaded = function() {
		return !!this.doc;
	};
	PDFPresentation.prototype.load = function(cb) {
		if (this.isLoaded()) {
			// Immediately call callback
			cb();
			return;
		}
		try {
			PDFJS.getDocument(this.url).then(_.bind(function (doc) {
				this.doc = doc;
				this.numPages = this.doc.numPages;
				this.e.trigger(this.e.byName.LOAD, this.curPage);
				cb();
			}, this));
		} catch (e) {
			// TODO(leon): Handle this.
		}
	};
	PDFPresentation.prototype.render = function(page) {
		if (!this.isLoaded()) {
			console.log("Not loaded yet");
			return;
		}
		var renderingDoneEventName = this.e.byName.RENDERING_DONE;
		// Defer rendering if we're already rendering
		if (this.isRendering) {
			var rerenderJobEventName = renderingDoneEventName + ".rerenderJob";
			this.e
			.unbind(rerenderJobEventName)
			.one(rerenderJobEventName, _.bind(function() {
				this.render.apply(this, Array.prototype.slice.call(arguments));
			}, this));
			return;
		}

		console.log("Showing page", this.curPage);
		var setRenderingFunc = _.bind(function(r) {
			return _.bind(function() {
				this.isRendering = r;
				if (!r) {
					this.e.trigger(renderingDoneEventName);
				}
			}, this);
		}, this);
		this.doc.getPage(this.curPage).then(_.bind(function(page) {
			var viewport = page.getViewport(this.scale);
			this.elem.height = viewport.height;
			this.elem.width = viewport.width;
			setRenderingFunc(true)();
			page.render({
				canvasContext: this.elem.getContext('2d'),
				viewport: viewport,
			}).then(setRenderingFunc(false)).catch(setRenderingFunc(false));
		}, this));
	};

	OCA.SpreedMe.Presentations = (function() {
		var exports = {};
		var self = exports;
		var rootElem = document.getElementById("presentations");
		var EVENT_TYPE = exports.EVENT_TYPE = {
			PRESENTATION_ADDED: "added",
			PRESENTATION_REMOVED: "removed",
			PRESENTATION_SWITCH: "switch",
			PAGE: "page",
		};
		var EVENTS = {
			PAGE_NEXT: function(p) {
				exports.newEvent(EVENT_TYPE.PAGE, p.curPage + 1);
			},
			PAGE_PREVIOUS: function(p) {
				exports.newEvent(EVENT_TYPE.PAGE, p.curPage - 1);
			},
		};

		var sharedPresentations = {
			active: null,
			staging: null,
			byId: {},
			withActive: function(cb) {
				if (this.active) {
					cb(this.active);
				}
			},
			init: function(id, p) {
				this.byId[id] = p;
				var c = document.createElement("canvas");
				c.id = "presentation_" + id;
				p.elem = c;
				p.elem.addEventListener("click", function(e) {
					var half = (p.elem.offsetWidth / 2);
					if (e.offsetX > half) {
						EVENTS.PAGE_NEXT(p);
					} else {
						EVENTS.PAGE_PREVIOUS(p);
					}
				}, true);
				this.hide(p);
				rootElem.appendChild(c);
			},
			add: function(id, p) {
				if (!this.byId[id]) {
					// We don't have this presentation yet
					this.init(id, p);
				} else {
					// Reuse existing presentation
					p = this.byId[id];
				}
				// TODO(leon): Remove 'true' and add presentation selector instead
				if (true || !this.active) {
					this.show(p);
				}
			},
			remove: function(id) {
				if (!this.byId[id]) {
					console.log("Remove: Unknown ID", id);
					return;
				}
				var p = this.byId[id];
				if (p === this.active) {
					p.hide();
				}
				p.elem.parentNode.removeChild(p.elem);
				delete this.byId[id];
			},
			removeAll: function() {
				for (var id in this.byId) {
					if (this.byId.hasOwnProperty(id)) {
						this.remove(id);
					}
				}
			},
			show: function(p) {
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
			},
			showById: function(id) {
				if (!this.byId.hasOwnProperty(id)) {
					// TODO(leon): Handle error
					return;
				}
				this.show(this.byId[id]);
			},
			hide: function(p) {
				if (p === this.active) {
					// TODO(leon): We should simply show one of the next presentation
					this.active = null;
				}
				p.elem.classList.add("hidden");
			},
		};
		var isSanitizedToken = function(token) {
			return /^[a-z0-9]+$/i.test(token);
		};
		var makeDownloadUrl = function(token) {
			return OC.generateUrl("s/" + token + "/download");
		};

		document.addEventListener("keydown", function(e) {
			// Only do something if we have an active presentation
			if (!sharedPresentations.active) {
				return;
			}
			var p = sharedPresentations.active;
			switch (e.keyCode) {
			case 37: // Left arrow
				EVENTS.PAGE_PREVIOUS(p);
				break;
			case 39: // Right arrow
				EVENTS.PAGE_NEXT(p);
				break;
			}
		}, true);

		exports.newEvent = function(type, payload) {
			// Inform self
			self.handleEvent({type: type, payload: payload});
			// Then inform others
			OCA.SpreedMe.webrtc.sendDirectlyToAll('presentation', type, payload);
		};
		exports.handleEvent = function(data, from) {
			// TODO(leon): We might want to check if 'from' has permissions to emit the event
			switch (data.type) {
			case EVENT_TYPE.PRESENTATION_ADDED:
				self.add(data.payload.token);
				break;
			case EVENT_TYPE.PRESENTATION_REMOVED:
				self.remove(data.payload);
				break;
			case EVENT_TYPE.PRESENTATION_SWITCH:
				sharedPresentations.showById(data.payload);
				break;
			case EVENT_TYPE.PAGE:
				sharedPresentations.withActive(function(p) {
					p.exactPage(data.payload);
				});
				break;
			default:
				console.log("Unknown presentation event '%s':", data.type, data.payload);
			}
		};

		exports.add = function(token) {
			if (!isSanitizedToken(token)) {
				// TODO(leon): Handle error
				console.log("Invalid token received", token);
				return;
			}
			var url = makeDownloadUrl(token);
			var p = new PDFPresentation(token, url);
			sharedPresentations.add(token, p);
		};

		return exports;
	})();

})(OCA, OC, $);
