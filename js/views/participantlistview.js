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


(function(OC, OCA, Marionette) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.Talk = OCA.Talk || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

	var uiChannel = Backbone.Radio.channel('ui');

	OCA.SpreedMe.Views.ParticipantListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		className: 'participantWithList',
		reorderOnSort: true,

		childView: Marionette.View.extend({
			tagName: 'li',
			modelEvents: {
				'change:sessionId': function() {
					// The sessionId is used to know if the user is online.
					this.render();
				},
				'change:displayName': function() {
					this.render();
				},
				'change:participantType': function() {
					this.render();
				},
				'change:inCall': function() {
					this.render();
				},
			},
			initialize: function() {
				this.room = this.model.collection.room;

				// When the type of the current participant changes the
				// available actions on the participant shown by this item view
				// change too, so the view should be rendered again.
				this.listenTo(this.room, 'change:participantType', function() {
					this.render();
				});

				this.listenTo(uiChannel, 'document:click', function(event) {
					var target = $(event.target);
					if (!target.closest('.popovermenu').is(this.ui.menu) && !target.is(this.ui.menuButton)) {
						// Close the menu when clicking outside it or the button
						// that toggles it.
						this.closeMenu();
					}
				});
			},
			template: function(context) {
				// OCA.Talk.Views.Templates may not have been initialized when this
				// view is initialized, so the template can not be directly
				// assigned.
				return OCA.Talk.Views.Templates['participantlistview'](context);
			},
			templateContext: function() {
				var isSelf = false,
					isModerator = false;
				if (OCA.Talk.getCurrentUser().uid) {
					isSelf = this.model.get('userId') === OCA.Talk.getCurrentUser().uid;
					isModerator = this.room.get('participantType') === OCA.SpreedMe.app.OWNER ||
						this.room.get('participantType') === OCA.SpreedMe.app.MODERATOR;
				} else {
					isSelf = this.model.get('sessionId') === this.room.get('sessionId');
					isModerator = this.room.get('participantType') === OCA.SpreedMe.app.GUEST_MODERATOR;
				}

				var canModerate = this.model.get('participantType') !== OCA.SpreedMe.app.OWNER &&       // can not moderate owners
					!isSelf && isModerator,
					name = '';


				if (this.model.get('userId').length || this.model.get('displayName').length) {
					name = this.model.get('displayName');
				} else {
					name = t('spreed', 'Guest');
				}

				var isGuestOrGuestModerator = this.model.get('participantType') === OCA.SpreedMe.app.GUEST || this.model.get('participantType') === OCA.SpreedMe.app.GUEST_MODERATOR;
				var hasContactsMenu = OCA.Talk.getCurrentUser().uid && !isSelf && !isGuestOrGuestModerator;

				return {
					canModerate: canModerate,
					canBePromoted: this.model.get('participantType') === OCA.SpreedMe.app.USER || this.model.get('participantType') === OCA.SpreedMe.app.GUEST,
					canBeDemoted: this.model.get('participantType') === OCA.SpreedMe.app.MODERATOR || this.model.get('participantType') === OCA.SpreedMe.app.GUEST_MODERATOR,
					name: name,
					participantHasContactsMenu: hasContactsMenu,
					participantIsSelf: isSelf,
					participantIsUser: this.model.get('participantType') === OCA.SpreedMe.app.USER,
					participantIsGuestOrGuestModerator: isGuestOrGuestModerator,
					participantIsGuestModerator: this.model.get('participantType') === OCA.SpreedMe.app.GUEST_MODERATOR,
					participantIsModerator: this.model.get('participantType') === OCA.SpreedMe.app.MODERATOR,
					participantIsOwner: this.model.get('participantType') === OCA.SpreedMe.app.OWNER,
					moderatorIndicator: '(' + t('spreed', 'moderator') + ')',
					demoteModeratorText: t('spreed', 'Demote from moderator'),
					promoteModeratorText: t('spreed', 'Promote to moderator'),
					removeParticipantText: t('spreed', 'Remove participant')
				};
			},
			onRender: function() {
				var model = this.model;
				this.$el.find('.avatar').each(function() {
					var $element = $(this);
					var firstChar = $element.data('displayName') ? $element.data('displayName').substr(0, 1) : '?';

					if (model.get('participantType') === OCA.SpreedMe.app.GUEST_MODERATOR) {
						$element.imageplaceholder(firstChar, model.get('displayName'), 32);
						$element.css('background-color', '#b9b9b9');
					} else if (model.get('participantType') === OCA.SpreedMe.app.GUEST) {
						$element.imageplaceholder(firstChar, model.get('displayName'), 32);
						$element.css('background-color', '#b9b9b9');
					} else {
						$element.avatar(model.get('userId'), 32, undefined, false, undefined, model.get('displayName'));
					}
				});

				if (OCA.Talk.getCurrentUser().uid && model.get('userId') &&
					model.get('userId') !== OCA.Talk.getCurrentUser().uid) {
					this.$el.find('.participant-entry .avatar').contactsMenu(
						model.get('userId'), 0, this.$el.find('.participant-entry'));

					this.$el.find('.participant-entry .avatar-wrapper').on('keyup', function(event) {
						if (event.key === ' ' || event.key === 'Enter') {
							$(this).find('.avatar').click();
						}
					});
				}

				this.$el.attr('data-session-id', this.model.get('sessionId'));
				this.$el.attr('data-participant', this.model.get('userId'));
				this.$el.addClass('participant');

				if (!this.model.isOnline()) {
					this.$el.addClass('participant-offline');
				} else {
					this.$el.removeClass('participant-offline');
				}

				this.toggleMenuClass();
			},
			events: {
				'click .participant-entry-utils-menu-button button': 'toggleMenu',
				'click .popovermenu .promote-moderator': 'promoteToModerator',
				'click .popovermenu .demote-moderator': 'demoteFromModerator',
				'click .popovermenu .remove-participant': 'removeParticipant'
			},
			ui: {
				'participant': 'li.participant',
				'menu': '.popovermenu',
				'menuButton': '.participant-entry-utils-menu-button button',
				'menuButtonIconLoading': '.participant-entry-utils-menu-button .icon-loading-small'
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
			closeMenu: function() {
				this.menuShown = false;
				this.toggleMenuClass();
			},
			promoteToModerator: function() {
				if (this.model.get('participantType') !== OCA.SpreedMe.app.USER &&
					this.model.get('participantType') !== OCA.SpreedMe.app.GUEST) {
					return;
				}

				this.closeMenu();
				this.ui.menuButton.addClass('hidden');
				this.ui.menuButtonIconLoading.removeClass('hidden');

				var data = {},
					self = this;

				if (this.model.get('userId')) {
					data = {
						participant: this.model.get('userId')
					};
				} else {
					data = {
						sessionId: this.model.get('sessionId')
					};
				}

				$.ajax({
					type: 'POST',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.room.get('token') + '/moderators',
					data: data,
					success: function() {
						if (self.model.get('userId')) {
							self.model.set('participantType', OCA.SpreedMe.app.MODERATOR);
						} else {
							self.model.set('participantType', OCA.SpreedMe.app.GUEST_MODERATOR);
						}
						// When an attribute that affects the order of a
						// collection is set the collection has to be explicitly
						// sorted again.
						self.model.collection.sort();
					},
					error: function() {
						self.ui.menuButtonIconLoading.addClass('hidden');
						self.ui.menuButton.removeClass('hidden');

						OC.Notification.showTemporary(t('spreed', 'Error while promoting user to moderator'), {type: 'error'});
					}
				});
			},
			demoteFromModerator: function() {
				if (this.model.get('participantType') !== OCA.SpreedMe.app.MODERATOR &&
					this.model.get('participantType') !== OCA.SpreedMe.app.GUEST_MODERATOR) {
					return;
				}

				this.closeMenu();
				this.ui.menuButton.addClass('hidden');
				this.ui.menuButtonIconLoading.removeClass('hidden');

				var data = {},
					self = this;

				if (this.model.get('userId')) {
					data = {
						participant: this.model.get('userId')
					};
				} else {
					data = {
						sessionId: this.model.get('sessionId')
					};
				}

				$.ajax({
					type: 'DELETE',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.room.get('token') + '/moderators',
					data:data,
					success: function() {
						if (self.model.get('userId')) {
							self.model.set('participantType', OCA.SpreedMe.app.USER);
						} else {
							self.model.set('participantType', OCA.SpreedMe.app.GUEST);
						}
						// When an attribute that affects the order of a
						// collection is set the collection has to be explicitly
						// sorted again.
						self.model.collection.sort();
					},
					error: function() {
						self.ui.menuButtonIconLoading.addClass('hidden');
						self.ui.menuButton.removeClass('hidden');

						OC.Notification.showTemporary(t('spreed', 'Error while demoting moderator'), {type: 'error'});
					}
				});
			},
			removeParticipant: function() {
				if (this.model.get('participantType') === OCA.SpreedMe.app.OWNER) {
					return;
				}

				this.closeMenu();
				this.ui.menuButton.addClass('hidden');
				this.ui.menuButtonIconLoading.removeClass('hidden');

				var self = this,
					participantId = this.model.get('userId'),
					endpoint = '/participants';

				if (this.model.get('participantType') === OCA.SpreedMe.app.GUEST ||
						this.model.get('participantType') === OCA.SpreedMe.app.GUEST_MODERATOR) {
					participantId = this.model.get('sessionId');
					endpoint += '/guests';
				}

				$.ajax({
					type: 'DELETE',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.room.get('token') + endpoint,
					data: {
						participant: participantId
					},
					success: function() {
						self.model.collection.remove(self.model);
					},
					error: function() {
						self.ui.menuButtonIconLoading.addClass('hidden');
						self.ui.menuButton.removeClass('hidden');

						OC.Notification.showTemporary(t('spreed', 'Error while removing user from room'), {type: 'error'});
					}
				});
			}
		})
	});

})(OC, OCA, Marionette);
