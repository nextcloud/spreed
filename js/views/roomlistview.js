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

	// These constants must match the values in "lib/Room.php".
	var ROOM_TYPE_ONE_TO_ONE = 1;
	var ROOM_TYPE_GROUP_CALL = 2;
	var ROOM_TYPE_PUBLIC_CALL = 3;

	var ITEM_TEMPLATE = '<a class="app-navigation-entry-link" href="#{{id}}" data-token="{{token}}"><div class="avatar" data-user="{{name}}" data-user-display-name="{{displayName}}"></div> {{displayName}}</a>'+
						'<div class="app-navigation-entry-utils">'+
							'<ul>'+
								'<li class="app-navigation-entry-utils-menu-button"><button></button></li>'+
							'</ul>'+
						'</div>'+
						'<div class="app-navigation-entry-menu">'+
							'<ul class="app-navigation-entry-menu-list">'+
								'<li>'+
									'<button class="leave-room-button">'+
										'<span class="{{#if isDeletable}}icon-close{{else}}icon-delete{{/if}}"></span>'+
										'<span>'+t('spreed', 'Leave room')+'</span>'+
									'</button>'+
								'</li>'+
								'{{#if isDeletable}}'+
								'<li>'+
									'<button class="delete-room-button">'+
										'<span class="icon-delete"></span>'+
										'<span>'+t('spreed', 'Delete room')+'</span>'+
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
			'change:participantInCall': function() {
				this.render();
			},
			'change:participantType': function() {
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
			return {
				isDeletable: (this.model.get('participantType') === 1 || this.model.get('participantType') === 2) &&
					(Object.keys(this.model.get('participants')).length > 2 || this.model.get('numGuests') > 0)
			};
		},
		onRender: function() {
			var roomURL;

			this.checkSharingStatus();

			roomURL = OC.generateUrl('/call/' + this.model.get('token'));
			this.$el.find('.app-navigation-entry-link').attr('href', roomURL);

			if (this.model.get('active')) {
				this.$el.addClass('active');
				this.addRoomMessage();
			} else {
				this.$el.removeClass('active');
			}

			//If the room is not a one2one room, we show tooltip.
			if (this.model.get('type') !== ROOM_TYPE_ONE_TO_ONE) {
				this.addTooltip();
			}

			this.toggleMenuClass();
		},
		events: {
			'click .app-navigation-entry-utils-menu-button button': 'toggleMenu',
			'click @ui.menu .leave-room-button': 'leaveRoom',
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
		checkSharingStatus: function() {
			if (this.model.get('type') === ROOM_TYPE_ONE_TO_ONE) { // 1on1
				this.$el.find('.public-room').removeClass('public-room').addClass('private-room');

				_.each(this.$el.find('.avatar'), function(a) {
					if ($(a).data('user-display-name')) {
						$(a).avatar($(a).data('user'), 32, undefined, false, undefined, $(a).data('user-display-name'));
					} else {
						$(a).avatar($(a).data('user'), 32);
					}
				});
			} else if (this.model.get('type') === ROOM_TYPE_GROUP_CALL) { // Group
				this.$el.find('.public-room').removeClass('public-room').addClass('private-room');

				_.each(this.$el.find('.avatar'), function(a) {
					$(a).removeClass('icon-public').addClass('icon-contacts-dark');
				});
			} else if (this.model.get('type') === ROOM_TYPE_PUBLIC_CALL) { // Public room
				this.$el.find('.private-room').removeClass('private-room').addClass('public-room');

				_.each(this.$el.find('.avatar'), function(a) {
					$(a).removeClass('icon-contacts-dark').addClass('icon-public');
				});
			}

			if (this.model.get('active')) {
				this.addRoomMessage();
			}
		},
		leaveRoom: function() {
			// If user is in that room, it should leave the associated call first.
			if (this.model.get('active')) {
				OCA.SpreedMe.Calls.leaveCurrentCall(true);
			}

			this.$el.slideUp();

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token') + '/participants/self',
				type: 'DELETE'
			});
		},
		deleteRoom: function() {
			if (this.model.get('participantType') !== 1 &&
				this.model.get('participantType') !== 2) {
				return;
			}

			//If user is in that room, it should leave that room first.
			if (this.model.get('active')) {
				OCA.SpreedMe.Calls.leaveCurrentCall(true);
				OC.Util.History.pushState({}, OC.generateUrl('/apps/spreed'));
			}

			this.$el.slideUp();

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.model.get('token'),
				type: 'DELETE'
			});
		},
		joinRoom: function(e) {
			e.preventDefault();
			var token = this.ui.room.attr('data-token');
			OCA.SpreedMe.Calls.joinRoom(token);

			OC.Util.History.pushState({
				token: token
			}, OC.generateUrl('/call/' + token));
		},
		addRoomMessage: function() {
			var message = '',
				messageAdditional = '',
				participants = this.model.get('participants'),
				hasCall = this.model.get('hasCall'),
				isInCall = this.model.get('participantInCall');

			//Remove previous icon, avatar or link from emptycontent
			var $emptyContentIcon = $('#emptycontent-icon');
			$emptyContentIcon.attr('class', '');
			$emptyContentIcon.innerHTML = '';
			$('#shareRoomInput').addClass('hidden');
			$('#shareRoomClipboardButton').addClass('hidden');


			switch(this.model.get('type')) {
				case ROOM_TYPE_ONE_TO_ONE:
					var waitingParticipantId = '',
						waitingParticipantName = '';

					_.each(participants, function(data, participantId) {
						if (OC.getCurrentUser().uid !== participantId) {
							waitingParticipantId = participantId;
							waitingParticipantName = data.name;
						}
					});

					// Avatar for username
					var avatar = document.createElement('div');
					avatar.className = 'avatar room-avatar';

					$emptyContentIcon.append(avatar);

					$emptyContentIcon.find('.avatar').each(function () {
						if (waitingParticipantName && (waitingParticipantId !== waitingParticipantName)) {
							$(this).avatar(waitingParticipantId, 128, undefined, false, undefined, waitingParticipantName);
						} else {
							$(this).avatar(waitingParticipantId, 128);
						}
					});

					message = t('spreed', 'Waiting for {participantName} to join the room …', {participantName: waitingParticipantName});
					if (hasCall) {
						if (isInCall) {
							message = t('spreed', 'Waiting for {participantName} to join the call …', {participantName: waitingParticipantName});
						} else {
							message = t('spreed', '{participantName} is waiting for you to join the call …', {participantName: waitingParticipantName});
						}
					}
					break;
				case ROOM_TYPE_PUBLIC_CALL:
				case ROOM_TYPE_GROUP_CALL:
					message = t('spreed', 'Waiting for others to join the room …');

					if (OC.getCurrentUser().uid !== null && Object.keys(participants).length === 1) {
						message = t('spreed', 'No other people in this call');
						if (this.model.get('participantType') === 0 || this.model.get('participantType') === 1) {
							messageAdditional = t('spreed', 'You can invite others in the participant tab of the sidebar');
						}
					}

					if (hasCall) {
						$emptyContentIcon.addClass('icon-video');
						if (isInCall) {
							message = t('spreed', 'Waiting for others to join the call …');

						} else {
							var others = [];
							_.each(participants, function(data) {
								if (data.call) {
									others.push(data.name);
								}
							});

							if (others.length === 1) {
								message = t('spreed', '{participantName} is waiting for you to join the call …', {participantName: others[0]});
							} else {
								message = t('spreed', 'Call in progress …');
								switch (others.length) {
									case 0:
										break;
									case 2:
										messageAdditional = t('spreed', 'Join {participant1} and {participant2}', {
											participant1: others[0],
											participant2: others[1]
										});
										break;
									case 3:
										messageAdditional = t('spreed', 'Join {participant1}, {participant2} and {participant3}', {
											participant1: others[0],
											participant2: others[1],
											participant3: others[2]
										});
										break;
									case 4:
										messageAdditional = t('spreed', 'Join {participant1}, {participant2}, {participant3} and {participant4}', {
											participant1: others[0],
											participant2: others[1],
											participant3: others[2],
											participant4: others[3]
										});
										break;
									case 5:
										messageAdditional = t('spreed', 'Join {participant1}, {participant2}, {participant3}, {participant4} and {participant5}', {
											participant1: others[0],
											participant2: others[1],
											participant3: others[2],
											participant4: others[3],
											participant5: others[4]
										});
										break;
									default:
										messageAdditional = t('spreed', 'Join {participant1}, {participant2}, {participant3}, {participant4}, …', {
											participant1: others[0],
											participant2: others[1],
											participant3: others[2],
											participant4: others[3]
										});
										break;
								}
							}
						}
					} else {
						if (this.model.get('type') === ROOM_TYPE_PUBLIC_CALL) {
							$emptyContentIcon.addClass('icon-public');
						} else {
							$emptyContentIcon.addClass('icon-contacts-dark');
						}
					}

					if (messageAdditional === '' && this.model.get('type') === ROOM_TYPE_PUBLIC_CALL) {
						messageAdditional = t('spreed', 'Share this link to invite others!');

						//Add link
						var url = window.location.protocol + '//' + window.location.host + OC.generateUrl('/call/' + this.model.get('token'));
						$('#shareRoomInput').val(url);
						$('#shareRoomInput').removeClass('hidden');
						$('#shareRoomClipboardButton').removeClass('hidden');

					}
					break;
				default:
					console.log("Unknown room type", this.model.get('type'));
					break;
			}

			$('#emptycontent h2').html(message);
			$('#emptycontent p').text(messageAdditional);
		},
		addTooltip: function () {
			var participants = [];
			$.each(this.model.get('participants'), function(participantId, data) {
				if (participantId !== oc_current_user) {
					participants.push(escapeHTML(data.name));
				}
			});

			if (this.model.get('guestList') !== '') {
				participants.push(this.model.get('guestList'));
			}

			if (participants.length === 0) {
				participants.push(t('spreed', 'You'));
			} else {
				participants.push(t('spreed', 'and you'));
			}

			var htmlstring = participants.join('<br>');

			this.ui.room.tooltip({
				placement: 'bottom',
				trigger: 'hover',
				html: 'true',
				title: htmlstring
			});
		}
	});

	var RoomListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		childView: RoomItemView
	});

	OCA.SpreedMe.Views.RoomListView = RoomListView;

})(OC, OCA, Marionette, Handlebars, _, $);
