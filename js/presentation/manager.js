// TODO(fancycode): Should load through AMD if possible.
/* global OCA, OC */

// require(['underscore', 'jquery', 'presentation/consts', 'presentation/type-pdf'])
(function(OCA, OC, _, $) {
	'use strict';

	// Import list
	// This will hopefully go away once we support AMD
	var PDFPresentation = OCA.SpreedMe.Presentation.PDFPresentation;
	var consts = OCA.SpreedMe.Presentation.consts;

	var exports = {};

	// Some helper functions for event handlers
	var isSanitizedToken = function(token) {
		return /^[a-z0-9]+$/i.test(token);
	};
	var makeDownloadUrl = function(token) {
		return OC.generateUrl("s/" + token + "/download");
	};

	var PresentationManager = function(rootElem) {
		var iframe = document.createElement("iframe");
		iframe.setAttribute("sandbox", "allow-scripts allow-same-origin"); // TODO(leon): Remove 'allow-same-origin'
		rootElem.appendChild(iframe);
		this.postMessage = new PostMessageAPI({
			allowedPartners: [document.location.origin],
			iframe: iframe,
		});
		this.postMessage.bind(_.bind(function(event) {
			switch (event.data.type) {
			case consts.EVENT_TYPES.PAGE:
				this.newEvent(consts.EVENT_TYPES.PAGE, event.data.payload);
				break;
			default:
				console.log("Got unknown event from child via postMessage", event);
			}
		}, this));
		iframe.src = OC.generateUrl("apps/spreed/sandbox/presentations");
	};
	PresentationManager.prototype.newEvent = function(type, payload) {
		// Inform self
		this.handleEvent({type: type, payload: payload}, null); // TODO(leon): Replace null by own Peer object
		// Then inform others
		OCA.SpreedMe.webrtc.sendDirectlyToAll(consts.DATACHANNEL_NAMESPACE, type, payload);
	};
	PresentationManager.prototype.handleEvent = function(data, from) {
		// TODO(leon): from === null means the event is from ourself, see other comment
		// This should change, see other comment: "Replace null by own Peer object"
		if (from) {
			data.from = from.id; // We can't post the full Peer object as it includes unclonable objects
		} else {
			data.from = null;
		}
		// Some events need special care
		switch (data.type) {
		case consts.EVENT_TYPES.PRESENTATION_ADDED:
		case consts.EVENT_TYPES.PRESENTATION_CURRENT:
			var token = data.payload.token;
			if (!isSanitizedToken(token)) {
				// This will never happen unless someone tries to manually inject bogus tokens
				console.log("Invalid token '%s' received, ignoring", token);
				break;
			}
			data.payload.url = makeDownloadUrl(token);
			this.postMessage.post(data);
			break;
		default:
			this.postMessage.post(data);
		}
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
						consts.EVENT_TYPES.PRESENTATION_ADDED,
						{token: token}
					);
				}, this),
			});
		}, this);
		var title = t('spreed', 'Please select the file(s) you want to share');
		var allowedFileTypes = [];
		for (var type in consts.SUPPORTED_DOCUMENT_TYPES) {
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
	PresentationManager.prototype.getCurrentState = function(cb) {
		this.postMessage.requestResponse({type: consts.EVENT_TYPES.POSTMESSAGE_REQ_CURRENT}, cb);
	};

	var instance = null;
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
			var type = consts.EVENT_TYPES.PRESENTATION_CURRENT;
			pm.getCurrentState(function(state) {
				peers.forEach(function(peer, i) {
					peer.sendDirectly(consts.DATACHANNEL_NAMESPACE, type, state);
				});
			});
		};

		// TODO(leon): Only listen for this if we're a moderator
		signaling.on('usersJoined', function(users) {
			// TODO(leon): users.map(user => users[0])
			users.forEach(function(user, i) {
				var peers = OCA.SpreedMe.webrtc.getPeers(user.sessionId);
				keepPosted(peers);
			});
		});

		return instance;
	};

	// This will hopefully go away once we support AMD
	for (var k in exports) {
		if (!exports.hasOwnProperty(k)) {
			continue;
		}
		OCA.SpreedMe.Presentation[k] = exports[k];
	}

})(OCA, OC, _, $);
