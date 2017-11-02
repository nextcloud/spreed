// TODO(fancycode): Should load through AMD if possible.
/* global OCA, OC */

// require(['underscore', 'jquery', 'presentation/consts', 'presentation/type-pdf'])
(function(OCA, OC, _, $) {
	'use strict';

	// Import list
	// This will hopefully go away once we support AMD
	var consts = OCA.SpreedMe.Presentation.consts;

	var exports = {};

	// Some helper functions for event handlers
	var isSanitizedToken = function(token) {
		return /^[a-z0-9]+$/i.test(token);
	};
	var makeDownloadUrl = function(token) {
		return OC.generateUrl("s/" + token + "/download");
	};

	var PresentationManager = function(rootElem, channel) {
		this.channel = channel;

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
				this.newEvent(event.data.type, event.data.payload);
				break;
			case consts.EVENT_TYPES.MODEL_CHANGE:
				this.channel.trigger(event.data.type, event.data);
				break;
			default:
				console.log("Got unknown event from child via postMessage", event);
			}
		}, this));
		iframe.src = OC.generateUrl("apps/spreed/sandbox/presentations");

		this.channel.on('activate', _.bind(function(id) {
			this.newEvent(consts.EVENT_TYPES.PRESENTATION_SWITCH, id);
		}, this));
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

		var add = _.bind(function(data) {
			var token = data.payload.token;
			if (!isSanitizedToken(token)) {
				// This will never happen unless someone tries to manually inject bogus tokens
				console.log("Invalid token '%s' received, ignoring", token);
				return;
			}
			data.payload.url = makeDownloadUrl(token);
			this.postMessage.post(data);
		}, this);
		// Some events need special care
		switch (data.type) {
		case consts.EVENT_TYPES.PRESENTATION_ADDED:
		case consts.EVENT_TYPES.PRESENTATION_CURRENT:
			add(data);
			break
		case consts.EVENT_TYPES.PRESENTATION_ALL_AVAILABLE:
			_.mapObject(data.payload, _.bind(function(p, k) {
				// TODO(leon): This is soooo ugly
				var fakeData = _.clone(data);
				fakeData.type = consts.EVENT_TYPES.PRESENTATION_ADDED;
				fakeData.payload = p;
				// TODO(leon): Might want to properly set 'from'
				fakeData.from = 'myself'; // Avoid privilege escalation by setting to non-null value
				add(fakeData);
				this.channel.trigger(fakeData.type, fakeData);
			}, this));
			break;
		default:
			this.postMessage.post(data);
		}

		// Pass event on presentation channel
		this.channel.trigger(data.type, data);
	};
	PresentationManager.prototype.chooseFromPicker = function() {
		var shareSelectedFiles = _.bind(function(file) {
			var split = file.split('/');
			var name = split[split.length-1];
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
						consts.EVENT_TYPES.PRESENTATION_ADDED, {
							name: name,
							token: token,
						},
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
	PresentationManager.prototype.getAllAvailable = function(cb) {
		this.postMessage.requestResponse({type: consts.EVENT_TYPES.POSTMESSAGE_REQ_ALL_AVAILABLE}, cb);
	};

	var instance = null;
	exports.instance = function() {
		if (!instance) {
			throw 'no presentation instance found';
		}
		return instance;
	};
	exports.init = function(rootElem, webrtc, channel) {
		if (instance) {
			return instance;
		}
		var pm = instance = new PresentationManager(rootElem, channel);

		var keepPosted = function(peer) {
			// Notify about all presentations
			pm.getAllAvailable(function(state) {
				var type = consts.EVENT_TYPES.PRESENTATION_ALL_AVAILABLE;
				peer.sendDirectly(consts.DATACHANNEL_NAMESPACE, type, state);
			});

			// Notify about current presentation
			pm.getCurrentState(function(state) {
				var type = consts.EVENT_TYPES.PRESENTATION_CURRENT;
				peer.sendDirectly(consts.DATACHANNEL_NAMESPACE, type, state);
			});
		};

		// TODO(leon): Only listen for this if we're a moderator
		webrtc.on('createdPeer', function(peer) {
			keepPosted(peer);
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
