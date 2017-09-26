// TODO(fancycode): Should load through AMD if possible.
/* global OCA */

// require(['underscore', 'presentation/type-base'])
(function(OCA, _) {
	'use strict';

	// Import list
	// This will hopefully go away once we support AMD
	var Presentation = OCA.SpreedMe.Presentation.Presentation;

	var exports = {};

	var PDFPresentation = exports.PDFPresentation = function(id, token, url) {
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
			console.log("Failed to load PDFPresentation", e);
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

	// This will hopefully go away once we support AMD
	for (var k in exports) {
		if (!exports.hasOwnProperty(k)) {
			continue;
		}
		OCA.SpreedMe.Presentation[k] = exports[k];
	}

})(OCA, _);
