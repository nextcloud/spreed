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

		initialize: function() {
			this.$el.append('<div class="app-not-started-placeholder icon-loading"></div>');
		},

		/**
		 * Returns a CSS class to force scroll bars in the chat view instead of
		 * in the whole sidebar.
		 */
		getTabsContainerExtraClasses: function() {
			return 'with-inner-scroll-bars';
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
			if (OCA.Talk.FilesPlugin.isTalkSidebarSupportedForFile(fileInfo)) {
				return true;
			}

			// If the Talk tab can not be displayed then the current room is
			// left; this must be done here because "setFileInfo" will not get
			// called with the new file if the tab can not be displayed.
			if (this._appStarted) {
				OCA.Talk.FilesPlugin.leaveCurrentRoom();
			} else {
				this.model = null;
			}

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
			if (!this._appStarted) {
				this.model = fileInfo;

				return;
			}

			if (this.model === fileInfo) {
				// If the tab was hidden and it is being shown again at this
				// point the tab has not been made visible yet, so the
				// operations need to be delayed. However, the scroll position
				// is saved before the tab is made visible to avoid it being
				// reset.
				// Note that the system tags may finish to load once the chat
				// view was already loaded; in that case the input for tags will
				// be shown, "compressing" slightly the chat view and thus
				// causing it to "lose" the last visible element (as the scroll
				// position is kept so the elements at the bottom are hidden).
				// Unfortunately there does not seem to be anything that can be
				// done to prevent that.
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
				// the Files app (and not even in that case due to having to
				// wait for the signaling settings to be fetched before
				// registering the tab).
				// Nevertheless, disconnect from the previous room just in case.
				OCA.Talk.FilesPlugin.leaveCurrentRoom();

				return;
			}

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'file/' + fileInfo.get('id'),
				type: 'GET',
				beforeSend: function(request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: function(ocsResponse) {
					OCA.Talk.FilesPlugin.joinRoom(ocsResponse.ocs.data.token);
				},
				error: function() {
					OC.Notification.showTemporary(t('spreed', 'Error while getting the room ID'), {type: 'error'});

					OCA.Talk.FilesPlugin.leaveCurrentRoom();
				}
			});

			// If the details view is rendered again after the chat view has
			// been appended to this tab the chat view would stop working due to
			// the element being removed instead of detached, which would make
			// the references to its elements invalid (apparently even if
			// rendered again; "delegateEvents()" should probably need to be
			// called too in that case). However, the details view would only be
			// rendered again if new tabs were added, so in general this should
			// be safe.
			OCA.SpreedMe.app._chatView.$el.appendTo(this.$el);
			OCA.SpreedMe.app._chatView.reloadMessageList();
			OCA.SpreedMe.app._chatView.setTooltipContainer($('#app-sidebar'));
			OCA.SpreedMe.app._chatView.focusChatInput();
		},

		setAppStarted: function() {
			this._appStarted = true;

			this.$el.find('.app-not-started-placeholder').remove();

			// Set again the file info now that the app has started.
			if (this.model !== null) {
				var fileInfo = this.model;
				this.model = null;
				this.setFileInfo(fileInfo);
			}
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

			var talkChatDetailTabView = new OCA.Talk.TalkChatDetailTabView();

			OCA.SpreedMe.app.on('start', function() {
				self.setupSignalingEventHandlers();

				// While the app is being started the view just shows a
				// placeholder UI that is replaced by the actual UI once
				// started.
				talkChatDetailTabView.setAppStarted();
			}.bind(this));

			fileList.registerTabView(talkChatDetailTabView);

			// Unlike in the regular Talk app when Talk is embedded the
			// signaling settings are not initially included in the HTML, so
			// they need to be explicitly loaded before starting the app.
			OCA.Talk.Signaling.loadSettings().then(function() {
				OCA.SpreedMe.app.start();
			});
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

			if (fileInfo.get('type') === 'dir') {
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
				// shareType could be an integer or a string depending on
				// whether the Sharing tab was opened or not.
				shareType = parseInt(shareType);
				return shareType === OC.Share.SHARE_TYPE_USER ||
						shareType === OC.Share.SHARE_TYPE_GROUP ||
						shareType === OC.Share.SHARE_TYPE_CIRCLE ||
						shareType === OC.Share.SHARE_TYPE_ROOM;
			});

			if (shareTypes.length === 0) {
				return false;
			}

			return true;
		},

		setupSignalingEventHandlers: function() {
			OCA.SpreedMe.app.signaling.on('joinRoom', function(joinedRoomToken) {
				if (OCA.SpreedMe.app.token !== joinedRoomToken) {
					return;
				}

				OCA.SpreedMe.app.signaling.syncRooms().then(function() {
					OCA.SpreedMe.app._messageCollection.setRoomToken(OCA.SpreedMe.app.activeRoom.get('token'));
					OCA.SpreedMe.app._messageCollection.receiveMessages();
				});
			});
		},

		joinRoom: function(token) {
			OCA.SpreedMe.app.activeRoom = new OCA.SpreedMe.Models.Room({token: token});
			OCA.SpreedMe.app.signaling.setRoom(OCA.SpreedMe.app.activeRoom);

			OCA.SpreedMe.app.token = token;
			OCA.SpreedMe.app.signaling.joinRoom(token);
		},

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
