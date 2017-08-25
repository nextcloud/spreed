// TODO(fancycode): Should load through AMD if possible.
/* global OC, OCA */

(function(OCA, OC, $) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	var Presentation = function(id, url) {
		this.id = id;
		this.url = url;
		this.data = null;
		this.elem = null;
		this.numPages = 0;
		this.curPage = 1;
		this.scale = 1;
		this.e = $({});
	};
	Presentation.prototype.isLoaded = function() {
		throw new Exception('isLoaded not implemented yet');
	};
	Presentation.prototype.nextPage = function() {
		this.exactPage(this.curPage + 1);
	};
	Presentation.prototype.previousPage = function() {
		this.exactPage(this.curPage - 1);
	};
	Presentation.prototype.exactPage = function(num) {
		if (this.curPage === num || num <= 0 || num >= this.numPages) {
			return;
		}
		this.curPage = num;
		this.e.trigger("pageUpdated", this.curPage);
	};

	var PDFPresentation = function(id, url) {
		Presentation.call(this, id, url);
		this.e.on("load pageUpdated", _.bind(this.render, this));
	};
	PDFPresentation.prototype = Object.create(Presentation.prototype);
	PDFPresentation.prototype.isLoaded = function() {
		return !!this.doc;
	};
	PDFPresentation.prototype.load = function() {
		PDFJS.getDocument(this.url).then(_.bind(function (doc) {
			this.doc = doc;
			this.numPages = this.doc.numPages;
			this.e.trigger("load");
		}, this));
	};
	PDFPresentation.prototype.render = function() {
		if (!this.isLoaded()) {
			// TODO(leon): Maybe defer rendering
			console.log("Not loaded yet");
			return;
		}
		console.log("Showing page", this.curPage);
		this.doc.getPage(this.curPage).then(_.bind(function(page) {
			var viewport = page.getViewport(this.scale);
			this.elem.height = viewport.height;
			this.elem.width = viewport.width;
			page.render({
				canvasContext: this.elem.getContext('2d'),
				viewport: viewport,
			});
		}, this));
	};

	OCA.SpreedMe.Presentations = (function() {
		var exports = {};
		var self = exports;
		var rootElem = document.getElementById("presentations");

		var sharedPresentations = {
			active: null,
			byId: {},
			withActive: function(cb) {
				if (this.active) {
					cb(this.active);
				}
			},
			init: function(id, p) {
				this.byId[id] = p;
				var c = document.createElement("canvas");
				c.id = id;
				p.elem = c;
				p.elem.addEventListener("click", function(e) {
					var half = (p.elem.offsetWidth / 2);
					if (e.offsetX > half) {
						exports.newEvent("next_page");
					} else {
						exports.newEvent("previous_page");
					}
				}, true);
				rootElem.appendChild(c);
			},
			add: function(id, p) {
				if (!this.byId[id]) {
					// We don't have this presentation yet
					this.init(id, p);
				}
				if (!this.active) {
					this.show(p);
				}
			},
			remove: function(id) {
				if (!this.byId[id]) {
					console.log("Remove: Unknown ID", id);
					return;
				}
				var p = this.byId[id];
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
				this.active = p;
			},
		};
		var isSanitizedToken = function(token) {
			return /^[a-z0-9]+$/i.test(token);
		};
		var makeDownloadUrl = function(token) {
			return OC.generateUrl("s/" + token + "/download");
		};

		exports.newEvent = function(type, payload) {
			// Inform self
			self.handleEvent({type: type, payload: payload});
			// Then inform others
			OCA.SpreedMe.webrtc.sendDirectlyToAll('presentation', type, payload);
		};
		exports.handleEvent = function(data, from) {
			switch (data.type) {
			case 'added':
				self.add(data.payload.token);
				break;
			case 'removed':
				self.remove(data.payload);
				break;
			case 'next_page':
				sharedPresentations.withActive(function(p) {
					p.nextPage();
				});
				break;
			case 'previous_page':
				sharedPresentations.withActive(function(p) {
					p.previousPage();
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
			p.load();
		};

		return exports;
	})();

})(OCA, OC, $);
