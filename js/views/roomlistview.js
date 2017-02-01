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

	var ITEM_TEMPLATE = '<a class="app-navigation-entry-link" href="#{{id}}" data-roomId="{{id}}"><div class="avatar" data-user="{{name}}" data-user-display-name="{{displayName}}"></div> {{displayName}}</a>'+
						'<div class="app-navigation-entry-utils">'+
							'<ul>'+
								'<li class="app-navigation-entry-utils-menu-button"><button></button></li>'+
							'</ul>'+
						'</div>'+
						'<div class="app-navigation-entry-menu">'+
							'<ul class="app-navigation-entry-menu-list">'+
								'<li>'+
									'<button class="add-person-button">'+
										'<span class="icon-add"></span>'+
										'<span>'+t('spreed', 'Add person')+'</span>'+
									'</button>'+
								'</li>'+
								'{{#isNameEditable}}'+
								'<li>'+
									'<button class="rename-room-button">'+
										'<span class="icon-rename"></span>'+
										'<span>'+t('spreed', 'Rename')+'</span>'+
									'</button>'+
								'</li>'+
								'<input class="hidden-important rename-element rename-input" maxlength="200" type="text"/>'+
								'<button class="icon-confirm hidden-important rename-element rename-confirm"></button>'+
								'{{/isNameEditable}}'+
								'<li>'+
									'<button class="share-link-button">'+
										'<span class="icon-public"></span>'+
										'<span>'+t('spreed', 'Share link')+'</span>'+
									'</button>'+
									'<input id="shareInput-{{id}}" class="share-link-input private-room" readonly="readonly" type="text"/>'+
									'<div class="clipboardButton icon-clippy private-room" data-clipboard-target="#shareInput-{{id}}"></div>'+
									'<div class="icon-delete private-room"></div>'+
								'</li>'+
								'<li>'+
									'<button class="leave-group-button">'+
										'<span class="icon-close"></span>'+
										'<span>'+t('spreed', 'Leave call')+'</span>'+
									'</button>'+
								'</li>'+
							'</ul>'+
							'<form class="oca-spreedme-add-person hidden">'+
								'<input class="add-person-input" type="text" placeholder="Type name..."/>'+
							'</form>'+
						'</div>';

	var RoomItenView = Marionette.View.extend({
		tagName: 'li',
		modelEvents: {
			'change:active': function() {
				this.render();
			},
			'change:displayName': function() {
				this.render();
			},
			'change:type': function() {
				this.render();
				this.checkSharingStatus();
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
		onRender: function() {
			var roomURL, completeURL;

			this.initPersonSelector();
			this.checkSharingStatus();

			roomURL = OC.generateUrl('/apps/spreed') + '?roomId=' + this.model.get('id');
			completeURL = window.location.protocol + '//' + window.location.host + roomURL;

			this.ui.shareLinkInput.attr('value', completeURL);
			this.$el.find('.clipboardButton').attr('data-clipboard-text', completeURL);
			this.$el.find('.clipboardButton').tooltip({
				placement: 'bottom',
				trigger: 'hover',
				title: t('spreed', 'Copy')
			});
			this.initClipboard();

			this.$el.find('.app-navigation-entry-link').attr('href', roomURL);

			if (this.model.get('active')) {
				this.$el.addClass('active');
				this.addRoomMessage();
			} else {
				this.$el.removeClass('active');
			}

			//If the room is not a one2one room, we show tooltip.
			if (this.model.get('type') !== 1) {
				this.addTooltip();
			}

			this.toggleMenuClass();
		},
		events: {
			'click .app-navigation-entry-utils-menu-button button': 'toggleMenu',
			'click .app-navigation-entry-menu .add-person-button': 'addPerson',
			'click .app-navigation-entry-menu .rename-room-button': 'showRenameInput',
			'click .app-navigation-entry-menu .rename-confirm': 'confirmRoomRename',
			'click .app-navigation-entry-menu .share-link-button': 'shareGroup',
			'click .app-navigation-entry-menu .leave-group-button': 'leaveGroup',
			'click .icon-delete': 'unshareGroup',
			'click .app-navigation-entry-link': 'joinRoom'
		},
		ui: {
			'room': '.app-navigation-entry-link',
			'menu': '.app-navigation-entry-menu',
			'shareLinkInput': '.share-link-input',
			'menuList': '.app-navigation-entry-menu-list',
			'personSelectorForm' : '.oca-spreedme-add-person',
			'personSelectorInput': '.add-person-input'
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
			if (this.model.get('type') === 1) { // 1on1
				this.$el.find('.public-room').removeClass('public-room').addClass('private-room');

				_.each(this.$el.find('.avatar'), function(a) {
					if ($(a).data('user-display-name')) {
						$(a).avatar($(a).data('user'), 32, undefined, false, undefined, $(a).data('user-display-name'));
					} else {
						$(a).avatar($(a).data('user'), 32);
					}
				});
			} else if (this.model.get('type') === 2) { // Group
				this.$el.find('.public-room').removeClass('public-room').addClass('private-room');

				_.each(this.$el.find('.avatar'), function(a) {
					$(a).removeClass('icon-public').addClass('icon-contacts-dark');
				});
			} else if (this.model.get('type') === 3) { // Public room
				this.$el.find('.private-room').removeClass('private-room').addClass('public-room');

				_.each(this.$el.find('.avatar'), function(a) {
					$(a).removeClass('icon-contacts-dark').addClass('icon-public');
				});
			}

			if (this.model.get('active')) {
				this.addRoomMessage();
			}
		},
		addPerson: function() {
			this.ui.menuList.attr('style', 'display: none !important');
			this.ui.personSelectorForm.toggleClass('hidden');
			this.ui.personSelectorInput.select2('open');
		},
		showRenameInput: function() {
			var currentRoomName = this.model.get('name'),
				self = this;

			this.$el.find('.rename-element').removeClass('hidden-important');
			this.$el.find('.rename-room-button').addClass('hidden-important');

			if (currentRoomName) {
				this.$el.find('.rename-input').val(currentRoomName);
			}

			this.$el.find('.rename-input').focus();
			this.$el.find('.rename-input').select();
			this.$el.keyup(function(e) {
				if (e.keyCode === 13) {
					self.confirmRoomRename();
				} else if (e.keyCode === 27) {
					self.$el.find('.rename-element').addClass('hidden-important');
					self.$el.find('.rename-room-button').removeClass('hidden-important');
				}
			});
		},
		confirmRoomRename: function() {
			var currentRoomName = this.model.get('name');
			var newRoomName = $.trim(this.$el.find('.rename-input').val());

			this.$el.find('.rename-element').addClass('hidden-important');
			this.$el.find('.rename-room-button').removeClass('hidden-important');

			if (currentRoomName !== newRoomName) {
				console.log('Changing room name to: '+newRoomName+' from: '+currentRoomName);
				this.renameRoom(newRoomName);
			}
		},
		renameRoom: function(roomName) {
			var app = OCA.SpreedMe.app;

			// This should be the only case
			if ((this.model.get('type') !== 1) && (roomName.length <= 200)) {
				$.ajax({
					url: OC.generateUrl('/apps/spreed/api/room/') + this.model.get('id'),
					type: 'PUT',
					data: 'roomName='+roomName,
					success: function() {
						app.syncRooms();
					}
				});
			}
		},
		shareGroup: function() {
			var app = OCA.SpreedMe.app;

			// This should be the only case
			if (this.model.get('type') !== 3) {
				$.ajax({
					url: OC.generateUrl('/apps/spreed/api/room/public'),
					type: 'POST',
					data: 'roomId='+this.model.get('id'),
					success: function() {
						app.syncRooms();
					}
				});
			}
		},
		unshareGroup: function() {
			var app = OCA.SpreedMe.app;

			// This should be the only case
			if (this.model.get('type') === 3) {
				$.ajax({
					url: OC.generateUrl('/apps/spreed/api/room/public'),
					type: 'DELETE',
					data: 'roomId='+this.model.get('id'),
					success: function() {
						app.syncRooms();
					}
				});
			}
		},
		leaveGroup: function() {
			//If user is in that room, it should leave that room first.
			if (this.model.get('active')) {
				OCA.SpreedMe.Rooms.leaveCurrentRoom();
				OCA.SpreedMe.Rooms.showRoomDeletedMessage(true);
			}

			this.$el.slideUp();

			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/room/') + this.model.get('id'),
				type: 'DELETE'
			});
		},
		joinRoom: function(e) {
			e.preventDefault();
			var roomId = parseInt(this.ui.room.attr('data-roomId'), 10);
			OCA.SpreedMe.Rooms.join(roomId);
		},
		addRoomMessage: function() {
			var message, messageAdditional, participants;

			//Remove previous icon, avatar or link from emptycontent
			var emptyContentIcon = document.getElementById('emptycontent-icon');
			emptyContentIcon.removeAttribute('class');
			emptyContentIcon.innerHTML = '';
			$('#shareRoomInput').addClass('hidden');
			$('#shareRoomClipboardButton').addClass('hidden');

			participants = this.model.get('participants');

			switch(this.model.get('type')) {
				case 1:
					var waitingParticipantId, waitingParticipantName;

					$.each(participants, function(participantId, participantName) {
						if (oc_current_user !== participantId) {
							waitingParticipantId = participantId;
							waitingParticipantName = participantName;
						}
					});

					// Avatar for username
					var avatar = document.createElement('div');
					avatar.className = 'avatar room-avatar';

					$('#emptycontent-icon').append(avatar);

					$('#emptycontent-icon').find('.avatar').each(function () {
						if (waitingParticipantName && (waitingParticipantId !== waitingParticipantName)) {
							$(this).avatar(waitingParticipantId, 128, undefined, false, undefined, waitingParticipantName);
						} else {
							$(this).avatar(waitingParticipantId, 128);
						}
					});

					message = t('spreed', 'Waiting for {participantName} to join the call …', {participantName: waitingParticipantName});
					messageAdditional = '';
					break;
				case 2:
					if (Object.keys(participants).length > 1) {
						message = t('spreed', 'Waiting for others to join the call …');
						messageAdditional = '';
					} else {
						message = t('spreed', 'No other people in this call');
						messageAdditional = 'You can invite others by clicking "+ Add person" in the call menu.';
					}
					$('#emptycontent-icon').addClass('icon-contacts-dark');
					break;
				case 3:
					if (Object.keys(participants).length > 1) {
						message = t('spreed', 'Waiting for others to join the call …');
					} else {
						message = t('spreed', 'No other people in this call');
					}
					messageAdditional = 'Share this link to invite others!';
					$('#emptycontent-icon').addClass('icon-public');

					//Add link
					var url = window.location.protocol + '//' + window.location.host + OC.generateUrl('/apps/spreed?roomId=' + this.model.get('id'));
					$('#shareRoomInput').val(url);
					$('#shareRoomInput').removeClass('hidden');
					$('#shareRoomClipboardButton').removeClass('hidden');
					break;
				default:
					break;
			}

			$('#emptycontent h2').html(message);
			$('#emptycontent p').text(messageAdditional);
		},
		addTooltip: function () {
			var participants = [];
			$.each(this.model.get('participants'), function(participantId, participantName) {
				if (participantId !== oc_current_user) {
					participants.push(escapeHTML(participantName));
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
		},
		initClipboard: function () {
			var clipboard = new Clipboard('.clipboardButton');
			clipboard.on('success', function(e) {
				var $input = $(e.trigger);
				$input.tooltip('hide')
					.attr('data-original-title', t('core', 'Copied!'))
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$input.tooltip('hide')
						.attr('data-original-title', t('core', 'Copy'))
						.tooltip('fixTitle');
				}, 3000);
			});
			clipboard.on('error', function (e) {
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
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function () {
					$input.tooltip('hide')
						.attr('data-original-title', t('spreed', 'Copy'))
						.tooltip('fixTitle');
				}, 3000);
			});
		},
		initPersonSelector: function() {
			var _this = this;

			this.ui.personSelectorInput.select2({
				ajax: {
					url: OC.linkToOCS('apps/files_sharing/api/v1') + 'sharees',
					dataType: 'json',
					quietMillis: 100,
					data: function (term) {
						return {
							format: 'json',
							search: term,
							perPage: 200,
							itemType: 'call'
						};
					},
					results: function (response) {
						// TODO improve error case
						if (response.ocs.data === undefined) {
							console.error('Failure happened', response);
							return;
						}

						var results = [],
							participants = _this.model.get('participants');

						$.each(response.ocs.data.exact.users, function(id, user) {
							var isExactUserInGroup = false;

							$.each(participants, function(participantId) {
								if (participantId === user.value.shareWith) {
									isExactUserInGroup = true;
								}
							});

							if (!isExactUserInGroup) {
								results.push({ id: user.value.shareWith, displayName: user.label, type: "user"});
							}
						});

						$.each(response.ocs.data.users, function(id, user) {
							var isUserInGroup = false;

							$.each(participants, function(participantId) {
								if (participantId === user.value.shareWith) {
									isUserInGroup = true;
								}
							});

							if (!isUserInGroup) {
								results.push({ id: user.value.shareWith, displayName: user.label, type: "user"});
							}
						});

						return {
							results: results,
							more: false
						};
					}
				},
				initSelection: function (element, callback) {
					console.log(element);
					callback({id: element.val()});
				},
				formatResult: function (element) {
					return '<span><div class="avatar" data-user="' + escapeHTML(element.id) + '" data-user-display-name="' + escapeHTML(element.displayName) + '"></div>' + escapeHTML(element.displayName) + '</span>';
				},
				formatSelection: function () {
					return '<span class="select2-default" style="padding-left: 0;">'+OC.L10N.translate('spreed', 'Choose person…')+'</span>';
				}
			});
			this.ui.personSelectorInput.on('change', function(e) {
				var app = OCA.SpreedMe.app;

				$.post(
				OC.generateUrl('/apps/spreed/api/room/') + _this.model.get('id'),
					{
						newParticipant: e.val
					}
				).done(function() {
					app.syncRooms();
				});

				$('.select2-drop').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});
			this.ui.personSelectorInput.on('click', function() {
				$('.select2-drop').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			this.ui.personSelectorInput.on('select2-loaded', function() {
				$('.select2-drop').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			this.ui.personSelectorInput.on('select2-close', function () {
				_this.ui.menuList.attr('style', 'display: block !important');
				_this.ui.personSelectorForm.toggleClass('hidden');
				_this.menuShown = false;
				_this.toggleMenuClass();
			});
		}
	});

	var RoomListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		childView: RoomItenView
	});

	OCA.SpreedMe.Views.RoomListView = RoomListView;

})(OC, OCA, Marionette, Handlebars);
