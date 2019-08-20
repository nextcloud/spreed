/* global Marionette */

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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


(function(OC, OCA, Marionette, _, $) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var uiChannel = Backbone.Radio.channel('ui');

	var RoomItemView = Marionette.View.extend({
		tagName: 'li',
		modelEvents: {
			'change:active': function() {
				this.render();
			},
			'change:displayName': function() {
				this.render();
			},
			'change:participants': function() {
				this.render();
			},
			'change:hasCall': function() {
				this.render();
			},
			'change:participantFlags': function() {
				this.render();
			},
			'change:participantType': function() {
				this.render();
			},
			'change:isFavorite': function() {
				this.render();
			},
			'change:notificationLevel': function() {
				this.render();
			},
			'change:unreadMessages': function() {
				this.render();
			},
			'change:type': function() {
				this.render();
			}
		},
		initialize: function() {
			// Add class to every room list item to detect it on click.
			this.$el.addClass('room-list-item');
			this.listenTo(uiChannel, 'document:click', function(event) {
				var target = $(event.target);
				if (!this.$el.is(target.closest('.room-list-item'))) {
					// Click was not triggered by this element -> close menu
					this.menuShown = false;
					this.toggleMenuClass();
				}
			});
		},
		template: function(context) {
			// OCA.Talk.Views.Templates may not have been initialized when this
			// view is initialized, so the template can not be directly
			// assigned.
			return OCA.Talk.Views.Templates['roomlistview'](context);
		},
		templateContext: function() {
			var icon = '';
			if (this.model.get('objectType') === 'file') {
				icon = 'icon icon-file';
			} else if (this.model.get('objectType') === 'share:password') {
				icon = 'icon icon-password';
			} else if (this.model.get('type') === OCA.SpreedMe.app.ROOM_TYPE_CHANGELOG) {
				icon = 'icon icon-changelog';
			} else if (this.model.get('type') === OCA.SpreedMe.app.ROOM_TYPE_GROUP) {
				icon = 'icon icon-contacts';
			} else if (this.model.get('type') === OCA.SpreedMe.app.ROOM_TYPE_PUBLIC) {
				icon = 'icon icon-public';
			}

			var isDeletable = this.model.get('type') !== 1 && (this.model.get('participantType') === 1 || this.model.get('participantType') === 2);
			var isLeavable = !isDeletable || (this.model.get('type') !== 1 && Object.keys(this.model.get('participants')).length > 1);

			return {
				icon: icon,
				canFavorite: this.model.get('participantType') !== 5,
				notifyAlways: this.model.get('notificationLevel') === OCA.SpreedMe.app.NOTIFY_ALWAYS,
				notifyMention: this.model.get('notificationLevel') === OCA.SpreedMe.app.NOTIFY_MENTION,
				notifyNever: this.model.get('notificationLevel') === OCA.SpreedMe.app.NOTIFY_NEVER,
				isLeavable: isLeavable,
				isDeletable: isDeletable,
				numUnreadMessages: this.model.get('unreadMessages') > 99 ? '99+' : this.model.get('unreadMessages'),
				favoriteMarkText: t('spreed', 'Favorited'),
				unfavoriteRoomText: t('spreed', 'Remove from favorites'),
				favoriteRoomText: t('spreed', 'Add to favorites'),
				copyLinkText: t('spreed', 'Copy link'),
				notificationCaptionText: t('spreed', 'Chat notifications'),
				notifyAlwaysText: t('spreed', 'All messages'),
				notifyMentionText: t('spreed', '@-mentions only'),
				notifyNeverText: t('spreed', 'Off'),
				leaveConversationText: t('spreed', 'Leave conversation'),
				deleteConversationText: t('spreed', 'Delete conversation'),
			};
		},
		onRender: function() {
			var roomURL;

			this.setAvatarIfNeeded();

			roomURL = OC.generateUrl('/call/' + this.model.get('token'));
			this.$el.find('.app-navigation-entry-link').attr('href', roomURL);

			if (this.model.get('active')) {
				this.$el.addClass('active');
			} else {
				this.$el.removeClass('active');
			}

			this.toggleMenuClass();

			var completeURL = window.location.protocol + '//' + window.location.host + roomURL;
			this.ui.clipboardButton.attr('value', completeURL);
			this.ui.clipboardButton.attr('data-clipboard-text', completeURL);
			this.initClipboard();
		},
		events: {
			'click .app-navigation-entry-utils-menu-button button': 'toggleMenu',
			'click @ui.menu .favorite-room-button': 'addRoomToFavorites',
			'click @ui.menu .unfavorite-room-button': 'removeRoomFromFavorites',
			'click @ui.menu .notify-always-button': 'setNotificationLevelAlways',
			'click @ui.menu .notify-mention-button': 'setNotificationLevelMention',
			'click @ui.menu .notify-never-button': 'setNotificationLevelNever',
			'click @ui.menu .remove-room-button': 'removeRoom',
			'click @ui.menu .delete-room-button': 'deleteRoom',
			'click @ui.room': 'joinRoom'
		},
		ui: {
			'room': '.app-navigation-entry-link',
			'menu': '.app-navigation-entry-menu',
			'clipboardButton': '.clipboard-button',
			'menuList': '.app-navigation-entry-menu-list'
		},
		menuShown: false,
		toggleMenu: function(e) {
			e.preventDefault();
			this.menuShown = !this.menuShown;
			this.toggleMenuClass();
		},
		toggleMenuClass: function() {
			this.ui.menu.toggleClass('open', this.menuShown);
		},
		setAvatarIfNeeded: function() {
			if (this.model.get('type') !== OCA.SpreedMe.app.ROOM_TYPE_ONE_TO_ONE) {
				return;
			}

			_.each(this.$el.find('.avatar'), function(a) {
				if ($(a).data('user-display-name')) {
					$(a).avatar($(a).data('user'), 32, undefined, false, undefined, $(a).data('user-display-name'));
				} else {
					$(a).avatar($(a).data('user'), 32);
				}
			});
		},
		removeRoom: function() {
			this.$el.slideUp();

			this.model.removeSelf({
				error: function(model, response) {
					if (response.status === 400) {
						OC.Notification.showTemporary(t('spreed', 'You need to promote a new moderator before you can leave the conversation.'));

						// Close the menu, as nothing changed and thus the item
						// will not be rendered again.
						this.menuShown = false;
						this.toggleMenuClass();

						this.$el.slideDown();
					}
				}.bind(this)
			});
		},
		deleteRoom: function() {
			if (this.model.get('participantType') !== 1 &&
				this.model.get('participantType') !== 2) {
				return;
			}

			this.$el.slideUp();

			this.model.destroy();
		},
		addRoomToFavorites: function() {
			if (this.model.get('participantType') === 5) {
				return;
			}

			this.model.set('isFavorite', 1);

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token') + '/favorite',
				type: 'POST',
				success: function() {
					OCA.SpreedMe.app.signaling.syncRooms();
				}
			});
		},
		removeRoomFromFavorites: function() {
			if (this.model.get('participantType') === 5) {
				return;
			}

			this.model.set('isFavorite', 0);

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token') + '/favorite',
				type: 'DELETE',
				success: function() {
					OCA.SpreedMe.app.signaling.syncRooms();
				}
			});
		},
		setNotificationLevelAlways: function() {
			this._setNotificationLevel(OCA.SpreedMe.app.NOTIFY_ALWAYS);
		},
		setNotificationLevelMention: function() {
			this._setNotificationLevel(OCA.SpreedMe.app.NOTIFY_MENTION);
		},
		setNotificationLevelNever: function() {
			this._setNotificationLevel(OCA.SpreedMe.app.NOTIFY_NEVER);
		},
		/**
		 * @param {integer} level
		 */
		_setNotificationLevel: function(level) {
			this.model.set('notificationLevel', level);

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token') + '/notify',
				data: { level: level },
				type: 'POST',
				success: function() {
					OCA.SpreedMe.app.signaling.syncRooms();
				}
			});
		},
		joinRoom: function(e) {
			e.preventDefault();

			this.model.join();
		},

		/**
		 * Clipboard
		 */
		initClipboard: function() {
			if (this._clipboard) {
				this._clipboard.destroy();
				delete this._clipboard;
			}

			if (this.ui.clipboardButton.length === 0) {
				return;
			}

			this._clipboard = new Clipboard(this.ui.clipboardButton[0]);
			this._clipboard.on('success', function(e) {
				var $input = $(e.trigger);
				$input.tooltip('hide')
					.attr('data-original-title', t('core', 'Link copied!'))
					.tooltip('_fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$input.tooltip('hide')
						.attr('data-original-title', t('core', 'Copy link'))
						.tooltip('_fixTitle');
				}, 3000);
			});
			this._clipboard.on('error', function (e) {
				var $input = $(e.trigger);
				var actionMsg = '';
				if (/iPhone|iPad/i.test(navigator.userAgent)) {
					actionMsg = t('core', 'Not supported!');
				} else if (/Mac/i.test(navigator.userAgent)) {
					actionMsg = t('core', 'Press ⌘-C to copy.');
				} else {
					actionMsg = t('core', 'Press Ctrl-C to copy.');
				}

				$input.tooltip('hide')
					.attr('data-original-title', actionMsg)
					.tooltip('_fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function () {
					$input.tooltip('hide')
						.attr('data-original-title', t('spreed', 'Copy link'))
						.tooltip('_fixTitle');
				}, 3000);
			});
		}
	});

	var RoomListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		childView: RoomItemView
	});

	OCA.SpreedMe.Views.RoomListView = RoomListView;

})(OC, OCA, Marionette, _, $);
