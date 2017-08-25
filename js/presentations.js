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
		// TODO(leon): Rename to "pages"
		this.numSlides = 0;
		this.curSlide = 1;
		this.scale = 1;
		this.e = $({});
	};
	Presentation.prototype.isLoaded = function() {
		return this.data !== null;
	};
	// Presentation.prototype.load = function(cb) {
	// 	if (this.isLoaded()) {
	// 		// Already loaded, call callback
	// 		cb(this.data);
	// 		return;
	// 	}
	// 	// Need to be old-fashioned for blobs
	// 	var xhr = new XMLHttpRequest();
	// 	var that = this;
	// 	xhr.onload = function() {
	// 		if (this.status !== 200) {
	// 			// TODO(leon): Handle error
	// 			return;
	// 		}
	// 		that.data = window.URL.createObjectURL(this.response);
	// 		cb(that.data);
	// 		that.e.trigger("load", that.data);
	// 	};
	// 	xhr.open('GET', this.url);
	// 	xhr.responseType = 'blob';
	// 	xhr.send();
	// };
	Presentation.prototype.nextSlide = function() {
		this.exactSlide(this.curSlide + 1);
	};
	Presentation.prototype.previousSlide = function() {
		this.exactSlide(this.curSlide - 1);
	};
	Presentation.prototype.exactSlide = function(num) {
		if (this.curSlide === num || num <= 0 || num >= this.numSlides) {
			return;
		}
		this.curSlide = num;
		this.e.trigger("slideUpdated", this.curSlide);
	};

	var PDFPresentation = function(id, url) {
		Presentation.call(this, id, url);
		this.e.on("load slideUpdated", _.bind(this.render, this));
	};
	PDFPresentation.prototype = Object.create(Presentation.prototype);
	PDFPresentation.prototype.load = function() {
		PDFJS.getDocument(this.url).then(_.bind(function (doc) {
			this.doc = doc;
			this.numSlides = this.doc.numPages;
			this.e.trigger("load");
		}, this));
	};
	PDFPresentation.prototype.render = function() {
		console.log("Showing page", this.curSlide);
		this.doc.getPage(this.curSlide).then(_.bind(function(page) {
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
					p.nextSlide();
				});
				break;
			case 'previous_page':
				sharedPresentations.withActive(function(p) {
					p.previousSlide();
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
