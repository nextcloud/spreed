/* global Marionette, Handlebars */

(function(OC, OCA, Marionette, Handlebars) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var ITEM_TEMPLATE = 'Hello';

	var separateWindowRegion = Marionette.View.extend({
		template: Handlebars.compile(ITEM_TEMPLATE),

		regions: {
			firstRegion: '#localVideo'
		}
	});

	OCA.SpreedMe.Views.separateWindowRegion = separateWindowRegion;

})(OC, OCA, Marionette, Handlebars);
