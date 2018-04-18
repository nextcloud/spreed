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


(function(OC, OCA, Marionette, Handlebars) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var uiChannel = Backbone.Radio.channel('ui');

	var ITEM_TEMPLATE = '' +
		'<a class="participant-entry-link {{#if isOffline}}participant-offline{{/if}}" href="#" data-sessionId="{{sessionId}}">' +
			'<div class="avatar"></div>' +
			' {{name}}' +
			'{{#if participantIsOwner}}<span class="participant-moderator-indicator">(' + t('spreed', 'moderator') + ')</span>{{/if}}' +
			'{{#if participantIsModerator}}<span class="participant-moderator-indicator">(' + t('spreed', 'moderator') + ')</span>{{/if}}' +
			'{{#if inCall}}<span class="icon icon-video"></span>{{/if}}' +
		'</a>'+
		'{{#if canModerate}}' +
			'<div class="participant-entry-utils">'+
				'<ul>'+
					'<li class="participant-entry-utils-menu-button"><button class="icon icon-more"></button></li>'+
				'</ul>'+
			'</div>'+
			'<div class="popovermenu bubble menu">'+
				'<ul class="popovermenu-list">'+
					'{{#if participantIsModerator}}' +
					'<li>' +
						'<button class="demote-moderator">' +
							'<span class="icon icon-star"></span>' +
							'<span>' + t('spreed', 'Demote from moderator') + '</span>' +
						'</button>' +
					'</li>' +
					'{{else}}' +
						'{{#if participantIsUser}}' +
						'<li>' +
							'<button class="promote-moderator">' +
								'<span class="icon icon-rename"></span>' +
								'<span>' + t('spreed', 'Promote to moderator') + '</span>' +
							'</button>' +
						'</li>' +
						'{{/if}}' +
					'{{/if}}' +
					'<li>' +
						'<button class="remove-participant">' +
							'<span class="icon icon-delete"></span>' +
							'<span>' + t('spreed', 'Remove participant') + '</span>' +
						'</button>' +
					'</li>' +
				'</ul>' +
			'</div>' +
		'{{/if}}';

	OCA.SpreedMe.Views.ParticipantListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		className: 'participantWithList',
		collectionEvents: {
			'update': function() {
				this.render();
			},
			'reset': function() {
				this.render();
			},
			'sort': function() {
				this.render();
			},
			'sync': function() {
				this.render();
			}
		},
		childView: Marionette.View.extend({
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
				'change:type': function() {
					this.render();
					this.checkSharingStatus();
				}
			},
			initialize: function() {
				this.listenTo(uiChannel, 'document:click', function(event) {
					var target = $(event.target);
					if (!this.$el.is(target.closest('.participant'))) {
						// Click was not triggered by this element -> close menu
						this.menuShown = false;
						this.toggleMenuClass();
					}
				});
			},
			templateContext: function() {
				var canModerate = this.model.get('participantType') !== OCA.SpreedMe.app.OWNER &&       // can not moderate owners
					this.model.get('userId') !== OC.getCurrentUser().uid &&                // can not moderate yourself
					(OCA.SpreedMe.app.activeRoom.get('participantType') === OCA.SpreedMe.app.OWNER ||   // current user must be owner
						OCA.SpreedMe.app.activeRoom.get('participantType') === OCA.SpreedMe.app.MODERATOR), // or moderator.
					name = '';


				if (this.model.get('userId').length || this.model.get('displayName').length) {
					name = this.model.get('displayName');
				} else {
					name = t('spreed', 'Guest');
				}

				return {
					canModerate: canModerate,
					name: name,
					participantIsUser: this.model.get('participantType') === OCA.SpreedMe.app.USER,
					participantIsModerator: this.model.get('participantType') === OCA.SpreedMe.app.MODERATOR,
					participantIsOwner: this.model.get('participantType') === OCA.SpreedMe.app.OWNER
				};
			},
			onRender: function() {
				var model = this.model;
				this.$el.find('.avatar').each(function() {
					var $element = $(this);

					if (model.get('participantType') !== OCA.SpreedMe.app.GUEST) {
						$element.avatar(model.get('userId'), 32, undefined, false, undefined, model.get('displayName'));
					} else {
						$element.imageplaceholder('?', model.get('displayName'), 32);
						$element.css('background-color', '#b9b9b9');
					}
				});

				this.$el.attr('data-session-id', this.model.get('sessionId'));
				this.$el.attr('data-participant', this.model.get('userId'));
				this.$el.addClass('participant');

				if (!this.model.isOnline()) {
					this.$el.addClass('participant-offline');
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
				'menu': '.popovermenu'
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
			promoteToModerator: function() {
				if (this.model.get('participantType') !== OCA.SpreedMe.app.USER) {
					return;
				}

				var participantId = this.model.get('userId'),
					self = this;

				$.ajax({
					type: 'POST',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + OCA.SpreedMe.app.activeRoom.get('token') + '/moderators',
					data: {
						participant: participantId
					},
					success: function() {
						self.render();
					},
					error: function() {
						console.log('Error while promoting user to moderator');
					}
				});
			},
			demoteFromModerator: function() {
				if (this.model.get('participantType') !== OCA.SpreedMe.app.MODERATOR) {
					return;
				}

				var participantId = this.model.get('userId'),
					self = this;

				$.ajax({
					type: 'DELETE',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + OCA.SpreedMe.app.activeRoom.get('token') + '/moderators',
					data: {
						participant: participantId
					},
					success: function() {
						self.render();
					},
					error: function() {
						console.log('Error while demoting moderator');
					}
				});
			},
			removeParticipant: function() {
				if (this.model.get('participantType') === OCA.SpreedMe.app.OWNER) {
					return;
				}

				var self = this,
					participantId = this.model.get('userId'),
					endpoint = '/participants';

				if (this.model.get('participantType') === OCA.SpreedMe.app.GUEST) {
					participantId = this.model.get('sessionId');
					endpoint += '/guests';
				}

				$.ajax({
					type: 'DELETE',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + OCA.SpreedMe.app.activeRoom.get('token') + endpoint,
					data: {
						participant: participantId
					},
					success: function() {
						self.render();
					},
					error: function() {
						console.log('Error while removing user from room');
					}
				});
			}
		})
	});

})(OC, OCA, Marionette, Handlebars);
