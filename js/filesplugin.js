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
			delete this._currentFileId;
			OCA.Talk.FilesPlugin.leaveCurrentRoom();
			// TODO Not needed when changing to another room as the new one
			// will override the values of the previous one, but needed when
			// there is no room to stop the signaling from pinging the
			// previous room; this should probably be fixed anyways in the
			// signaling.
			OCA.SpreedMe.app.signaling.disconnect();

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

			this._currentFileId = fileInfo.get('id');

		// TODO probably move to a model
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
					// TODO show error somehow, maybe in the empty content of
					// the chat?
					OCA.Talk.FilesPlugin.leaveCurrentRoom();
				}
			});

			// TODO
			OCA.SpreedMe.app._chatView.$el.appendTo(this.$el);
			OCA.SpreedMe.app._chatView.setTooltipContainer($('#app-sidebar'));
		},

	});

	var roomsChannel = Backbone.Radio.channel('rooms');

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

			this.setupSignalingEventHandlers();

			fileList.registerTabView(new OCA.Talk.TalkChatDetailTabView());
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
	OCA.SpreedMe.app.start();

	OC.Plugins.register('OCA.Files.FileList', OCA.Talk.FilesPlugin);
})(OC, OCA);
