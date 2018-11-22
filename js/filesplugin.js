/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function(OC, OCA) {

	'use strict';

	OCA.Talk = OCA.Talk || {};

	var roomsChannel = Backbone.Radio.channel('rooms');

	OCA.Talk.RoomForFileModel = function() {
	};
	OCA.Talk.RoomForFileModel.prototype = {

		// TODO use promises for proper handling of calling leave will waiting
		// for joining to a room

		join: function(currentFileId) {
			if (this._currentFileId === currentFileId) {
				return;
			}

			this.leave();

			this._currentFileId = currentFileId;

			// TODO do not join the new room before leaving the previous one to
			// ensure that the UI was restored, the call ended, etc.
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'file/' + currentFileId,
				type: 'GET',
				beforeSend: function(request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: function(ocsResponse) {
					OCA.Talk.FilesPlugin.joinRoom(ocsResponse.ocs.data.token);
				},
				error: function() {
					// TODO show error somehow, maybe in the empty content of
					// the chat?
					OCA.Talk.FilesPlugin.leaveCurrentRoom();
				}
			});
		},

		leave: function() {
			if (this._currentFileId === undefined) {
				return;
			}

			delete this._currentFileId;

			OCA.Talk.FilesPlugin.leaveCurrentRoom();
			// TODO Not needed when changing to another room as the new one
			// will override the values of the previous one, but needed when
			// there is no room to stop the signaling from pinging the
			// previous room; this should probably be fixed anyways in the
			// signaling.
			OCA.SpreedMe.app.signaling.disconnect();
		}
	};

	OCA.Talk.TalkCallDetailFileInfoView = OCA.Files.DetailFileInfoView.extend({

		className: 'talkCallInfoView',

		initialize: function(options) {
			this._roomForFileModel = options.roomForFileModel;
			this._fileList = options.fileList;

			this._boundHideCallUi = this._hideCallUi.bind(this);

			this.listenTo(roomsChannel, 'joinedRoom', this.setActiveRoom);
		},

		/**
		 * Sets the file info to be displayed in the view
		 *
		 * @param {OCA.Files.FileInfo} fileInfo file info to set
		 */
		setFileInfo: function(fileInfo) {
			// TODO
			if (this.model === fileInfo) {
				// TODO the same file info seems to be set again when closing
				// and opening the sidebar; it seems that in that case the event
				// handlers for the join call button no longer work, check why
				return;
			}

			this.model = fileInfo;

			// Discard the call button until joining to the new room.
			delete this._callButton;

			this.render();

			if (OCA.Talk.FilesPlugin.isTalkSidebarSupportedForFile(this.model)) {
				this._roomForFileModel.join(this.model.get('id'));
			} else {
				this._roomForFileModel.leave();
			}
		},

		setActiveRoom: function(activeRoom) {
			this.stopListening(this._activeRoom, 'change:participantFlags', this._updateCallContainer);
// 			this.stopListening(OCA.SpreedMe.app.signaling, 'leaveCall', this._hideCallUi);
			OCA.SpreedMe.app.signaling.off('leaveCall', this._boundHideCallUi);

			this._activeRoom = activeRoom;

			if (activeRoom) {
				this._callButton = new OCA.SpreedMe.Views.CallButton({
					model: activeRoom,
				});
				// Force initial rendering; changes in the room state will
				// automatically render the button again from now on.
				this._callButton.render();

				// TODO unify this somehow
				this.listenTo(activeRoom, 'change:participantFlags', this._updateCallContainer);
// 				this.listenTo(OCA.SpreedMe.app.signaling, 'leaveCall', this._hideCallUi);
				OCA.SpreedMe.app.signaling.on('leaveCall', this._boundHideCallUi);
			} else {
				// TODO needed here? Or is it enough deleting it in setFileInfo?
				delete this._callButton;
			}

			this.render();
		},

		// TODO render again when the sidebar is closed and opened again
		render: function() {
			this.$el.empty();
			this._$talkSidebar = null;
			this._callUiShown = false;

			if (!OCA.Talk.FilesPlugin.isTalkSidebarSupportedForFile(this.model) || !this._callButton) {
				return;
			}

			this._$talkSidebar = $('<div id="talk-sidebar" class="hidden"></div>');

			this.$el.append(this._$talkSidebar);
			$('#talk-sidebar').append('<div id="call-container"></div>');
			$('#call-container').append('<div id="videos"><div id="localVideoContainer" class="videoView videoContainer"></div></div>');
			$('#call-container').append('<div id="screens"></div>');

			$('#localVideoContainer').append(
				'<video id="localVideo"></video>' +
				'<div class="avatar-container hidden">' +
				'	<div class="avatar"></div>' +
				'</div>' +
				'<div class="nameIndicator">' +
				'	<button id="mute" class="icon-audio icon-white icon-shadow" data-placement="top" data-toggle="tooltip" data-original-title="' + t('spreed', 'Mute audio (m)') + '"></button>' +
				'	<button id="hideVideo" class="icon-video icon-white icon-shadow" data-placement="top" data-toggle="tooltip" data-original-title="' + t('spreed', 'Disable video (v)') + '"></button>' +
// 				'	<button id="screensharing-button" class="app-navigation-entry-utils-menu-button icon-screen-off icon-white icon-shadow screensharing-disabled" data-placement="top" data-toggle="tooltip" data-original-title="' + t('spreed', 'Share screen') + '"></button>' +
// 				'	<div id="screensharing-menu" class="app-navigation-entry-menu">' +
// 				'		<ul>' +
// 				'			<li>' +
// 				'				<button id="show-screen-button">' +
// 				'					<span class="icon-screen"></span>' +
// 				'					<span>' + t('spreed', 'Show your screen') + '</span>' +
// 				'				</button>' +
// 				'			</li>' +
// 				'			<li>' +
// 				'				<button id="stop-screen-button">' +
// 				'					<span class="icon-screen-off"></span>' +
// 				'					<span>' + t('spreed', 'Stop screensharing') + '</span>' +
// 				'				</button>' +
// 				'			</li>' +
// 				'		</ul>' +
// 				'	</div>' +
				'</div>');

			OCA.SpreedMe.app.registerLocalVideoButtonHandlers();

			this.$el.append(this._callButton.$el);
		},

		_updateCallContainer: function() {
			var flags = this._activeRoom.get('participantFlags') || 0;
			var inCall = flags & OCA.SpreedMe.app.FLAG_IN_CALL !== 0;
			if (inCall) {
				this._showCallUi();
			} else {
				this._hideCallUi();
			}
		},

		// TODO show again after closing the sidebar and opening it again; also
		// ensure that an audio call is not broken when the sidebar is closed.
		_showCallUi: function() {
			if (!this._$talkSidebar || this._callUiShown) {
				return;
			}

			this._fileList.getRegisteredDetailViews().forEach(function(detailView) {
				if (!(detailView instanceof OCA.Talk.TalkCallDetailFileInfoView)) {
					detailView.$el.addClass('hidden-by-call');
				}
			});

			// TODO it seems that "#talk-sidebar" classes are changed in a
			// strange way by webrtc.js or something like that; even if that is
			// fixed probably #talk-sidebar should not be used in the end, as it
			// is just a quick way to get the CSS style from the public share
			// page.
			this._$talkSidebar.removeClass('hidden');

			// The icon to close the sidebar overlaps the video, so use its
			// white version with a shadow instead of the black one.
			// TODO change it only when there is a call in progress; while
			// waiting for other participants it should be kept black.
			$('#app-sidebar .icon-close').addClass('icon-white icon-shadow');

			this._callUiShown = true;
		},

		_hideCallUi: function() {
			// TODO the _$talkSidebar could be undefined when changing to a
			// different file, so the detail view has to be unhidden in any
			// case.
			// TODO mmm, no, it does not work... let's check why :-P
			this._fileList.getRegisteredDetailViews().forEach(function(detailView) {
				if (!(detailView instanceof OCA.Talk.TalkCallDetailFileInfoView)) {
					detailView.$el.removeClass('hidden-by-call');
				}
			});

			if (!this._$talkSidebar || !this._callUiShown) {
				return;
			}

			this._$talkSidebar.addClass('hidden');

			// Restore the icon to close the sidebar.
			$('#app-sidebar .icon-close').removeClass('icon-white icon-shadow');

			this._callUiShown = false;
		}

	});

	/**
	 * Tab view for Talk chat in the details view of the Files app.
	 *
	 * This view shows the chat for the Talk room associated with the file. The
	 * tab is shown only for those files in which the Talk sidebar is supported,
	 * otherwise it is hidden.
	 */
	OCA.Talk.TalkChatDetailTabView = OCA.Files.DetailTabView.extend({

		id: 'talkChatTabView',

		/**
		 * Higher priority than other tabs.
		 */
		order: -10,

		initialize: function(options) {
			this._roomForFileModel = options.roomForFileModel;
		},

		/**
		 * Returns a CSS class to force scroll bars in the chat view instead of
		 * in the whole sidebar.
		 */
		getTabsContainerExtraClasses: function() {
			return 'with-inner-scroll-bars force-minimum-height';
		},

		getLabel: function() {
			return t('spreed', 'Chat');
		},

		getIcon: function() {
			return 'icon-talk';
		},

		/**
		 * Returns whether the Talk tab can be displayed for the file.
		 *
		 * @param OCA.Files.FileInfoModel fileInfo
		 * @return True if the tab can be displayed, false otherwise.
		 * @see OCA.Talk.FilesPlugin.isTalkSidebarSupportedForFile
		 */
		canDisplay: function(fileInfo) {
			// TODO how to check again when shares for a file change?
			if (OCA.Talk.FilesPlugin.isTalkSidebarSupportedForFile(fileInfo)) {
				return true;
			}

			// If the Talk tab can not be displayed then the current room is
			// left; this must be done here because "setFileInfo" will not get
			// called with the new file if the tab can not be displayed.
			this._roomForFileModel.leave();

			return false;
		},

		/**
		 * Sets the FileInfoModel for the currently selected file.
		 *
		 * Rooms are associated to the id of the file, so the chat can not be
		 * loaded until the file info is set and the token for the room is got.
		 *
		 * @param OCA.Files.FileInfoModel fileInfo
		 */
		setFileInfo: function(fileInfo) {
			if (this.model === fileInfo) {
				// If the tab was hidden and it is being shown again at this
				// point the tab has not been made visible yet, so the
				// operations need to be delayed. However, the scroll position
				// is saved before the tab is made visible to avoid it being
				// reset.
				// TODO the system tags may finish to load once the chat view
				// was already loaded; in that case the input for tags will be
				// shown, "compressing" slightly the chat view and thus causing
				// it to "lose" the last visible element (as the scroll position
				// is kept so the elements at the bottom are hidden).
				var lastKnownScrollPosition = OCA.SpreedMe.app._chatView.getLastKnownScrollPosition();
				setTimeout(function() {
					OCA.SpreedMe.app._chatView.restoreScrollPosition(lastKnownScrollPosition);

					// Load the pending elements that may have been added while
					// the tab was hidden.
					OCA.SpreedMe.app._chatView.reloadMessageList();

					OCA.SpreedMe.app._chatView.focusChatInput();
				}, 0);

				return;
			}

			this.model = fileInfo;

			if (!fileInfo || fileInfo.get('id') === undefined) {
				// This should never happen, except during the initial setup of
				// the Files app.
				// TODO disconnect from the previous room just in case?

				return;
			}

			// TODO join the room again due to the room model not keeping
			// previous messages, as due to that when the chat is rendered again
			// the previous messages are gone.
// 			if (this._currentFileId === fileInfo.get('id')) {
// 				// "setFileInfo" may have been called due to the parent view
// 				// being rendered again. In that case the chat view needs to be
// 				// rendered again too, as the references to its elements are no
// 				// longer valid.
// 				OCA.SpreedMe.app._chatView.render();
// 				return;
// 			}

			// TODO if id changed probably stop Talk until the new room is
			// fetch; replace chat by a loading spinner

			this._roomForFileModel.join(this.model.get('id'));

// 			this._currentFileId = fileInfo.get('id');
// 
// 		// TODO probably move to a model
// 			$.ajax({
// 				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'file/' + fileInfo.get('id'),
// 				type: 'GET',
// 				beforeSend: function(request) {
// 					request.setRequestHeader('Accept', 'application/json');
// 				},
// 				success: function(ocsResponse) {
// 					OCA.Talk.FilesPlugin.joinRoom(ocsResponse.ocs.data.token);
// 				},
// 				error: function() {
// 					// TODO show error somehow, maybe in the empty content of
// 					// the chat?
// 					OCA.Talk.FilesPlugin.leaveCurrentRoom();
// 				}
// 			});

			// TODO
			OCA.SpreedMe.app._chatView.$el.appendTo(this.$el);
			OCA.SpreedMe.app._chatView.reloadMessageList();
			OCA.SpreedMe.app._chatView.setTooltipContainer($('#app-sidebar'));
			OCA.SpreedMe.app._chatView.focusChatInput();
		},

	});

	/**
	 * @namespace
	 */
	OCA.Talk.FilesPlugin = {
		ignoreLists: [
			'files_trashbin',
			'files.public'
		],

		attach: function(fileList) {
			var self = this;
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return;
			}

			// If the details view is opened before the views are registered it
			// would be needed to close and open the details view again. TODO:
			// fix it in server so the details view is refreshed in this case?
			// The problem would be a lot of refreshing when several views are
			// added at once during initialization... It would be better to
			// register the views when the plugin is attached and queue the
			// actions until the app has started.
			var self = this;
			OCA.SpreedMe.app.on('start', function() {
				self.setupSignalingEventHandlers();

				var roomForFileModel = new OCA.Talk.RoomForFileModel();
				fileList.registerDetailView(new OCA.Talk.TalkCallDetailFileInfoView({ roomForFileModel: roomForFileModel, fileList: fileList }));
				fileList.registerTabView(new OCA.Talk.TalkChatDetailTabView({ roomForFileModel: roomForFileModel }));
			});

			// Unlike in the regular Talk app when Talk is embedded the
			// signaling settings are not initially included in the HTML, so
			// they need to be explicitly loaded before starting the app.
			OCA.Talk.Signaling.loadSettings().then(function() {
				OCA.SpreedMe.app.start();
			});

// 			this.setupSignalingEventHandlers();
// 
// 			var roomForFileModel = new OCA.Talk.RoomForFileModel();
// 			fileList.registerDetailView(new OCA.Talk.TalkCallDetailFileInfoView({ roomForFileModel: roomForFileModel, fileList: fileList }));
// 			fileList.registerTabView(new OCA.Talk.TalkChatDetailTabView({ roomForFileModel: roomForFileModel }));
		},

		/**
		 * Returns whether the Talk tab can be displayed for the file.
		 *
		 * @return True if the file is shared with the current user or by the
		 *         current user to another user (as a user, group...), false
		 *         otherwise.
		 */
		isTalkSidebarSupportedForFile: function(fileInfo) {
			if (!fileInfo) {
				return false;
			}

			if (fileInfo.get('shareOwnerId')) {
				// Shared with me
				// TODO How to check that it is not a remote share? At least for
				// local shares "shareTypes" is not defined when shared with me.
				return true;
			}

			if (!fileInfo.get('shareTypes')) {
				return false;
			}

			var shareTypes = fileInfo.get('shareTypes').filter(function(shareType) {
				return shareType == OC.Share.SHARE_TYPE_USER ||
						shareType == OC.Share.SHARE_TYPE_GROUP ||
						shareType == OC.Share.SHARE_TYPE_CIRCLE ||
						shareType == OC.Share.SHARE_TYPE_ROOM;
			});

			if (shareTypes.length === 0) {
				return false;
			}

			return true;
		},

		// TODO probably move to embedded
		setupSignalingEventHandlers: function() {
			var self = this;

			OCA.SpreedMe.app.signaling.on('joinRoom', function(joinedRoomToken) {
				if (OCA.SpreedMe.app.token !== joinedRoomToken) {
					return;
				}

				OCA.SpreedMe.app.signaling.syncRooms().then(function() {
					roomsChannel.trigger('joinedRoom', OCA.SpreedMe.app.activeRoom);

					// TODO apparently needed by some code in the calls in the public share
// 					var participants = OCA.SpreedMe.app.activeRoom.get('participants');
// 					OCA.SpreedMe.app.setRoomMessageForGuest(participants);

					// TODO not needed here, and probably anywhere as it will be
					// implicit when the call UI is shown
					// Ensure that the elements are shown, as they could have
					// been hidden if the password was already requested and
					// that conversation ended in this same page.
// 					$('#videos').show();

					OCA.SpreedMe.app._messageCollection.setRoomToken(OCA.SpreedMe.app.activeRoom.get('token'));
					OCA.SpreedMe.app._messageCollection.receiveMessages();
				});
			});
		},

		// TODO probably move to embedded
		joinRoom: function(token) {
			// TODO disconnect from previous room?

			OCA.SpreedMe.app.activeRoom = new OCA.SpreedMe.Models.Room({token: token});
			OCA.SpreedMe.app.signaling.setRoom(OCA.SpreedMe.app.activeRoom);

			OCA.SpreedMe.app.token = token;
			OCA.SpreedMe.app.signaling.joinRoom(token);
		},

		// TODO probably move to embedded
		leaveCurrentRoom: function() {
			OCA.SpreedMe.app.signaling.leaveCurrentRoom();

			roomsChannel.trigger('leaveCurrentRoom');

			OCA.SpreedMe.app.token = null;
			OCA.SpreedMe.app.activeRoom = null;
		}

	};

	OCA.SpreedMe.app = new OCA.Talk.Embedded();

	OC.Plugins.register('OCA.Files.FileList', OCA.Talk.FilesPlugin);

})(OC, OCA);
