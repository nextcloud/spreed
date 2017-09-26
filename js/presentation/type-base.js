// TODO(fancycode): Should load through AMD if possible.
/* global OCA */

// require(['jquery'])
(function(OCA, $) {
	'use strict';

	var exports = {};

	var Presentation = exports.Presentation = function(id, token, url) {
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

	// This will hopefully go away once we support AMD
	for (var k in exports) {
		if (!exports.hasOwnProperty(k)) {
			continue;
		}
		OCA.SpreedMe.Presentation[k] = exports[k];
	}

})(OCA, $);
