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

	var ITEM_TEMPLATE = '<li data-session-id="{{sessionId}}" data-participant="{{participantId}}" class="participant {{#if pariticipantIsOffline}}participant-offline{{/if}}">' +
		'<div class="avatar " data-username="test1" data-displayname="User One" style="height: 32px; width: 32px; background-color: rgb(213, 231, 116); color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 32px; font-size: 17.6px;">U</div>' +
		'<!--div class="avatar" data-username="{{participantId}}" data-displayname="{{participantDisplayName}}"></div-->' +
		'<span class="username" title="">' +
			'{{username}}' +
			'{{#if participantIsOwner}}<span class="participant-moderator-indicator">(' + t('spreed', 'owner') + ')</span>{{/if}}' +
			'{{#if participantIsModerator}}<span class="participant-moderator-indicator">(' + t('spreed', 'moderator') + ')</span>{{/if}}' +
		'</span>' +
		'{{#if canModerate}}' +
			'<span class="actionOptionsGroup">' +
				'<a href="#"><span class="icon icon-more"></span></a>' +
				'<div class="popovermenu bubble hidden menu">' +
				'<ul>' +
					'{{#if participantIsModerator}}' +
					'<li>' +
						'<a href="#" class="menuitem action action-demote permanent">' +
							'<span class="icon icon-star"></span><span>' + t('spreed', 'Demote from moderator') + '</span>' +
						'</a>' +
					'</li>' +
					'{{else}}' +
						'{{if participantIsUser}}' +
						'<li>' +
							'<a href="#" class="menuitem action action-promote permanent">' +
								'<span class="icon icon-rename"></span><span>' + t('spreed', 'Promote to moderator') + '</span>' +
							'</a>' +
						'</li>' +
						'{{/if}}' +
					'{{/if}}' +
					'<li>' +
						'<a href="#" class="menuitem action action-remove permanent">' +
							'<span class="icon icon-delete"></span><span>' + t('spreed', 'Remove participant') + '</span>' +
						'</a>' +
					'</li>' +
				'</ul>' +
				'</div>' +
			'</span>' +
		'{{/if}}' +
	'</li>';

	OCA.SpreedMe.Views.ParticipantView = Marionette.CollectionView.extend({
		tagName: 'ul',
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
				// FIXME this is checking the wrong user
				var canModerate = true;//this.model.get('participantType') === 1 || this.model.get('participantType') === 2;
				return {
					canModerate: canModerate,
					participantIsUser: this.model.get('participantType') === 3,
					participantIsModerator: this.model.get('participantType') === 2,
					participantIsOwner: this.model.get('participantType') === 1,
					participantIsOffline: this.model.get('sessionId') !== ''
				};
			},
			onRender: function() {
				// TODO ?
				// var roomURL, completeURL;
				// this.initPersonSelector();
			},
			events: {
				'click .actionOptionsGroup .action-promote': 'promoteToModerator',
				'click .actionOptionsGroup .action-demote': 'demoteFromModerator',
				'click .actionOptionsGroup .action-remove': 'removeParticipant'
			},
			ui: {
				'participant': 'li.participant',
				'menu': 'li.participant .actionOptionsGroup .menu'
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
				if (this.model.get('participantType') !== 3) {
					return;
				}

				var participantId = this.ui.participant.attr('data-participant'),
					self = this;

				$.ajax({
					type: 'POST',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + '/' + token + '/moderators',
					data: {
						participant: participantId
					},
					success: function() {
						self.render();
					},
					error: function() {
						console.log('Error while promoting user to moderator');
					}
				})
			},
			demoteFromModerator: function() {
				if (this.model.get('participantType') !== 2) {
					return;
				}

				var participantId = this.ui.participant.attr('data-participant'),
					self = this;

				$.ajax({
					type: 'DELETE',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + '/' + token + '/moderators',
					data: {
						participant: participantId
					},
					success: function() {
						self.render();
					},
					error: function() {
						console.log('Error while demoting moderator');
					}
				})
			},
			removeParticipant: function() {
				if (this.model.get('participantType') === 1) {
					return;
				}

				var participantId = this.ui.participant.attr('data-participant'),
					self = this;

				$.ajax({
					type: 'DELETE',
					url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + '/' + token + '/participants',
					data: {
						participant: participantId
					},
					success: function() {
						self.render();
					},
					error: function() {
						console.log('Error while removing user from room');
					}
				})
			}
		})
	});

})(OC, OCA, Marionette, Handlebars);
