/* global Marionette, Handlebars */

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


(function(OC, OCA, Marionette, Handlebars, _, $) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var uiChannel = Backbone.Radio.channel('ui');

	var ITEM_TEMPLATE = '<a class="app-navigation-entry-link" href="#{{id}}" data-token="{{token}}">' +
							'<div class="avatar {{icon}}" data-user="{{name}}" data-user-display-name="{{displayName}}"></div>' +
							'{{#if isFavorite}}'+
							// The favorite mark can not be a child of the
							// avatar, as it would be removed when the avatar is
							// loaded.
							'<div class="favorite-mark">' +
								'<span class="icon icon-favorite" />' +
								'<span class="hidden-visually">' + t('spreed', 'Favorited') + '</span>' +
							'</div>' +
							'{{/if}}' +
							' {{displayName}}' +
						'</a>'+
						'<div class="app-navigation-entry-utils">'+
							'<ul>'+
								'{{#if unreadMention}}<li class="app-navigation-entry-utils-counter highlighted"><span>@</span></li>{{/if}}'+
								'{{#if unreadMessages}}<li class="app-navigation-entry-utils-counter"><span>{{numUnreadMessages}}</span></li>{{/if}}'+
								'<li class="app-navigation-entry-utils-menu-button"><button></button></li>'+
							'</ul>'+
						'</div>'+
						'<div class="app-navigation-entry-menu">'+
							'<ul class="app-navigation-entry-menu-list">'+
								'{{#if canFavorite}}'+
								'{{#if isFavorite}}'+
								'<li>'+
									'<button class="unfavorite-room-button">'+
										'<span class="icon-star-dark"></span>'+
										'<span>'+t('spreed', 'Remove from favorites')+'</span>'+
									'</button>'+
								'</li>'+
								'{{else}}'+
								'<li>'+
									'<button class="favorite-room-button">'+
										'<span class="icon-starred"></span>'+
										'<span>'+t('spreed', 'Add to favorites')+'</span>'+
									'</button>'+
								'</li>'+
								'{{/if}}'+
								'{{/if}}'+
								'<li><div class="separator"></div></li>'+
								'<li{{#if notifyAlways}} class="active"{{/if}}>'+
									'<button class="notify-always-button">'+
										'<span class="icon-sound"></span>'+
										'<span>'+t('spreed', 'Always notify')+'</span>'+
									'</button>'+
								'</li>'+
								'<li{{#if notifyMention}} class="active"{{/if}}>'+
									'<button class="notify-mention-button">'+
										'<span class="icon-user"></span>'+
										'<span>'+t('spreed', 'Notify on @-mention')+'</span>'+
									'</button>'+
								'</li>'+
								'<li{{#if notifyNever}} class="active"{{/if}}>'+
									'<button class="notify-never-button">'+
										'<span class="icon-sound-off"></span>'+
										'<span>'+t('spreed', 'Never notify')+'</span>'+
									'</button>'+
								'</li>'+
								'<li><div class="separator"></div></li>'+
								'{{#if isLeavable}}'+
								'<li>'+
									'<button class="remove-room-button">'+
										'<span class="{{#if isDeletable}}icon-close{{else}}icon-delete{{/if}}"></span>'+
										'<span>'+t('spreed', 'Leave conversation')+'</span>'+
									'</button>'+
								'</li>'+
								'{{/if}}'+
								'{{#if isDeletable}}'+
								'<li>'+
									'<button class="delete-room-button">'+
										'<span class="icon-delete"></span>'+
										'<span>'+t('spreed', 'Delete conversation')+'</span>'+
									'</button>'+
								'</li>'+
								'{{/if}}'+
							'</ul>'+
						'</div>';

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
				numUnreadMessages: this.model.get('unreadMessages') > 99 ? '99+' : this.model.get('unreadMessages')
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
			'menuList': '.app-navigation-entry-menu-list'
		},
		template: Handlebars.compile(ITEM_TEMPLATE),
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
	});

	var RoomListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		childView: RoomItemView
	});

	OCA.SpreedMe.Views.RoomListView = RoomListView;

})(OC, OCA, Marionette, Handlebars, _, $);
