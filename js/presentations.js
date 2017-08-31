// TODO(fancycode): Should load through AMD if possible.
/* global OC, OCA */

(function(OCA, OC, $) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	var exports = {
		EVENT_NAMESPACE: 'presentations',
		DATACHANNEL_NAMESPACE: 'presentations',
	};

	var Presentation = function(id, token, url) {
		this.id = id;
		this.token = token;
		this.url = url;
		this.elem = null;
		this.numPages = 0;
		this.curPage = 1;
		this.scale = 1;
		this.isController = false;
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
	Presentation.prototype.allowControl = function(allow) {
		this.isController = allow;
	};
	Presentation.prototype.exactPage = function(num) {
		if (this.curPage === num || num <= 0 || num >= this.numPages) {
			return;
		}
		this.curPage = num;
		this.e.trigger(this.e.byName.PAGE_UPDATED, this.curPage);
	};

	var PDFPresentation = function(id, token, url) {
		Presentation.call(this, id, token, url);
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
				// this.curPage might already be set to something != 1
				// See other comment »We should use 'exactPage' instead«
				// TODO(leon): Handle this somehow instead of doing the following branch
				if (this.curPage > this.numPages) {
					// TODO(leon): This feels _so_ wrong
					this.curPage = 1;
				}
				this.e.trigger(this.e.byName.LOAD, this.curPage);
				cb();
			}, this));
		} catch (e) {
			// TODO(leon): Handle this.
		}
	};
	PDFPresentation.prototype.render = function(e, page) {
		if (!this.isLoaded()) {
			console.log("Not loaded yet");
			return;
		}
		var renderingDoneEventName = this.e.byName.RENDERING_DONE;
		// Defer rendering if we're already rendering
		if (this.isRendering) {
			console.log("Deferring rendering job for page", page);
			var rerenderJobEventName = renderingDoneEventName + ".rerenderJob";
			var args = Array.prototype.slice.call(arguments);
			this.e
			.unbind(rerenderJobEventName)
			.one(rerenderJobEventName, _.bind(function() {
				console.log("Running deferred rendering job for page", page);
				this.render.apply(this, args);
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

	var PresentationManager = function(rootElem) {
		this.rootElem = rootElem;
		this.SUPPORTED_DOCUMENT_TYPES = {
			// rendered by pdfcanvas directive
			"application/pdf": "pdf",
			// rendered by odfcanvas directive
			// TODO(fancycode): check which formats really work, allow all odf for now
			//"application/vnd.oasis.opendocument.text": "odf",
			//"application/vnd.oasis.opendocument.spreadsheet": "odf",
			//"application/vnd.oasis.opendocument.presentation": "odf",
			//"application/vnd.oasis.opendocument.graphics": "odf",
			//"application/vnd.oasis.opendocument.chart": "odf",
			//"application/vnd.oasis.opendocument.formula": "odf",
			//"application/vnd.oasis.opendocument.image": "odf",
			//"application/vnd.oasis.opendocument.text-master": "odf"
		};
		this.EVENT_TYPES = {
			PRESENTATION_CURRENT: "current", // Issued to inform new participants about current presentation / page
			PRESENTATION_ADDED: "added", // Indicates that a new presentation was added
			PRESENTATION_REMOVED: "removed", // Indicates that a presentation was removed
			PRESENTATION_SWITCH: "switch", // Indicates that we switched presentations
			PAGE: "page", // Indicates that the page changed
		};
		this.EVENTS = {
			PAGE_NEXT: _.bind(function(p) {
				if (p.isController) {
					this.newEvent(this.EVENT_TYPES.PAGE, p.curPage + 1);
				}
			}, this),
			PAGE_PREVIOUS: _.bind(function(p) {
				if (p.isController) {
					this.newEvent(this.EVENT_TYPES.PAGE, p.curPage - 1);
				}
			}, this),
		};

		// Some helper functions for event handlers
		var isSanitizedToken = function(token) {
			return /^[a-z0-9]+$/i.test(token);
		};
		var makeDownloadUrl = function(token) {
			return OC.generateUrl("s/" + token + "/download");
		};
		var receivedCurrent = false;
		this.EVENT_HANDLERS = {
			add: _.bind(function(token, from) {
				if (!isSanitizedToken(token)) {
					// This will never happen unless someone tries to manually inject bogus tokens
					console.log("Invalid token received", token);
					return;
				}
				var deferred = $.Deferred();
				var url = makeDownloadUrl(token);
				var p = new PDFPresentation(/* id */token, token, url);
				// TODO(leon): from === null means the event is from ourself
				// This should change, see other comment: "Replace null by own Peer object"
				p.allowControl(from === null);
				this.add(token, p);
				deferred.resolve(p); // TODO(leon): Make the this.add call return a promise instead
				return deferred.promise();
			}, this),
			current: _.bind(function(token, page, from) {
				if (receivedCurrent) {
					// Ignore duplicate current event
					console.log("Ignoring 'current' event as we have already processed it");
					return;
				}
				receivedCurrent = true;
				_.bind(this.EVENT_HANDLERS.add, this)(token, from).then(function(p) {
					// TODO(leon): This is bad. We should use 'exactPage' instead
					// See other comment »this.curPage might already be set to something != 1«
					p.curPage = page;
				}); // TODO(leon): Might want to catch as well
			}, this),
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
		case this.EVENT_TYPES.PRESENTATION_CURRENT:
			callHandler(this.EVENT_HANDLERS.current, data.payload.token, data.payload.page, from);
			break;
		case this.EVENT_TYPES.PRESENTATION_ADDED:
			callHandler(this.EVENT_HANDLERS.add, data.payload.token, from);
			break;
		case this.EVENT_TYPES.PRESENTATION_REMOVED:
			callHandler(this.EVENT_HANDLERS.remove, data.payload, from);
			break;
		case this.EVENT_TYPES.PRESENTATION_SWITCH:
			callHandler(this.EVENT_HANDLERS.switchTo, data.payload, from);
			break;
		case this.EVENT_TYPES.PAGE:
			callHandler(this.EVENT_HANDLERS.page, data.payload, from);
			break;
		default:
			console.log("Unknown presentation event '%s':", data.type, data.payload);
		}
	};
	PresentationManager.prototype.newEvent = function(type, payload) {
		// Inform self
		this.handleEvent({type: type, payload: payload}, null); // TODO(leon): Replace null by own Peer object
		// Then inform others
		OCA.SpreedMe.webrtc.sendDirectlyToAll(exports.DATACHANNEL_NAMESPACE, type, payload);
	};
	PresentationManager.prototype.chooseFromPicker = function() {
		var shareSelectedFiles = _.bind(function(file) {
			// TODO(leon): There might be an existing API endpoint which we can use instead
			// This would make things simpler
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'share',
				type: 'POST',
				data: {
					path: file,
				},
				beforeSend: function (req) {
					req.setRequestHeader('Accept', 'application/json');
				},
				success: _.bind(function(res) {
					var token = res.ocs.data.token;
					this.newEvent(
						this.EVENT_TYPES.PRESENTATION_ADDED,
						{token: token}
					);
				}, this),
			});
		}, this);
		var title = t('spreed', 'Please select the file(s) you want to share');
		var allowedFileTypes = [];
		for (var type in this.SUPPORTED_DOCUMENT_TYPES) {
			allowedFileTypes.push(type);
		}
		var config = {
			title: title,
			allowMultiSelect: false, // TODO(leon): Add support for this, ensure order somehow
			filterByMIME: allowedFileTypes,
		};
		OC.dialogs.filepicker(config.title, function(file) {
			console.log("Selected file", file);
			shareSelectedFiles(file);
		}, config.allowMultiSelect, config.filterByMIME);
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

	var instance = null;
	// Our presentation singleton
	exports.instance = function() {
		if (!instance) {
			throw 'no presentation instance found';
		}
		return instance;
	};
	exports.init = function(rootElem, signaling) {
		if (instance) {
			return instance;
		}
		var pm = instance = new PresentationManager(rootElem);

		var keepPosted = function(peers) {
			var type = pm.EVENT_TYPES.PRESENTATION_CURRENT;
			var payload = pm.getCurrentState();
			if (!payload) {
				return;
			}
			peers.forEach(function(peer, i) {
				peer.sendDirectly(exports.DATACHANNEL_NAMESPACE, type, payload);
			});
		};

		signaling.on('usersJoined', function(users) {
			users.forEach(function(user, i) {
				var peers = OCA.SpreedMe.webrtc.getPeers(user.sessionId);
				keepPosted(peers);
			});
		});

		// Bind some event handlers
		(function() {
			var evt = 'keydown.' + exports.EVENT_NAMESPACE;
			$(document).off(evt).on(evt, function(e) {
				var p = pm.active;
				if (!p) {
					return;
				}
				switch (e.keyCode) {
				case 37: // Left arrow
					pm.EVENTS.PAGE_PREVIOUS(p);
					break;
				case 39: // Right arrow
					pm.EVENTS.PAGE_NEXT(p);
					break;
				}
			});
		})();

		return instance;
	};
	OCA.SpreedMe.Presentations = OCA.SpreedMe.Presentations || exports;

})(OCA, OC, $);
