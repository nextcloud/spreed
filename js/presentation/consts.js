// TODO(fancycode): Should load through AMD if possible.
/* global OCA */

// require([''])
var OCA = OCA || {}; // Required for our sandbox, this will hopefully go away once we support AMD
(function(OCA) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Presentation = OCA.SpreedMe.Presentation || {};

	var exports = {
		consts: {
			EVENT_NAMESPACE: 'presentation',
			DATACHANNEL_NAMESPACE: 'presentation',
			SUPPORTED_DOCUMENT_TYPES: {
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
				//"application/vnd.oasis.opendocument.text-master": "odf",
			},
			EVENT_TYPES: {
				PRESENTATION_CURRENT: "current", // Issued to inform new participants about current presentation / page
				PRESENTATION_ADDED: "added", // Indicates that a new presentation was added
				PRESENTATION_REMOVED: "removed", // Indicates that a presentation was removed
				PRESENTATION_SWITCH: "switch", // Indicates that we switched presentations
				PAGE: "page", // Indicates that the page changed
				POSTMESSAGE_REQ_CURRENT: "pm_req_current", // This kind of message is used to request the 'current' state via postmessage
			},
		},
	};

	// This will hopefully go away once we support AMD
	for (var k in exports) {
		if (!exports.hasOwnProperty(k)) {
			continue;
		}
		OCA.SpreedMe.Presentation[k] = exports[k];
	}

})(OCA);
