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

	var ITEM_TEMPLATE = '<a class="app-navigation-entry-link" href="#{{id}}" data-roomId="{{id}}"><div class="avatar" data-user="{{name}}"></div> {{displayName}}</a>'+
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
										'<span>'+t('spreedme', 'Add person')+'</span>'+
									'</button>'+
								'</li>'+
								'<li>'+
									'<button class="share-link-button">'+
										'<span class="icon-public"></span>'+
										'<span>'+t('spreedme', 'Share link')+'</span>'+
										'<span class="icon-delete private-room"></span>'+
									'</button>'+
									'<input id="shareInput-{{id}}"class="share-link-input private-room" readonly="readonly" type="text"/>'+
									'<div class="clipboardButton icon-clippy private-room" data-clipboard-target="#shareInput-{{id}}"</div>'+
								'</li>'+
								'<li>'+
									'<button class="leave-group-button">'+
										'<span class="icon-close"></span>'+
										'<span>'+t('spreedme', 'Leave group')+'</span>'+
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

			this.initClipboard();
		},
		onRender: function() {
			this.initPersonSelector();
			this.checkSharingStatus();

			this.$el.find('.app-navigation-entry-link').attr('href', OC.generateUrl('/apps/spreed') + '?roomId=' + this.model.get('id'));
			this.$el.find('.app-navigation-entry-link').on('click', function(e) {
				e.preventDefault();
				var roomId = parseInt($(this).attr('data-roomId'), 10);
				OCA.SpreedMe.Rooms.join(roomId);
			});

			if (this.model.get('active')) {
				this.$el.addClass('active');
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
			'click .app-navigation-entry-menu .share-link-button': 'shareGroup',
			'click .app-navigation-entry-menu .leave-group-button': 'leaveGroup',
			'click .icon-delete' : 'unshareGroup'
		},
		ui: {
			'room': '.app-navigation-entry-link',
			'shareInput': '.share-link-input',
			'shareButton': '.clipboardButton',
			'menu': '.app-navigation-entry-menu',
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
					$(a).avatar($(a).data('user'), 32);
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

				var url = window.location.protocol + '//' + window.location.host + OC.generateUrl('/apps/spreed?roomId=' + this.model.get('id'));
				this.ui.shareInput.val(url);
			}
		},
		addPerson: function() {
			this.ui.menuList.attr('style', 'display: none !important');
			this.ui.personSelectorForm.toggleClass('hidden');
			this.ui.personSelectorInput.select2('open');
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
				var homeURL = OC.generateUrl('/apps/spreed');

				OCA.SpreedMe.webrtc.leaveRoom();
				window.location.replace(homeURL);
			}

			this.$el.slideUp();

			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/room/') + this.model.get('id'),
				type: 'DELETE'
			});
		},
		addTooltip: function () {
			var htmlstring = this.model.get('displayName').replace(/\, /g, '<br>');

			this.ui.room.tooltip({
				placement: 'bottom',
				trigger: 'hover',
				html: 'true',
				title: htmlstring
			});
		},
		initClipboard: function () {
			this.$el.find('.clipboardButton').tooltip({
				placement: 'bottom',
				trigger: 'hover',
				title: t('spreedme', 'Copy')
			});

			var clipboard = new Clipboard('.clipboardButton');
			clipboard.on('success', function(e) {
				var $input = $(e.trigger);
				$input.tooltip('hide')
					.attr('data-original-title', t('spreedme', 'Copied!'))
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$input.tooltip('hide')
						.attr('data-original-title', t('spreedme', 'Copy'))
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
						.attr('data-original-title', t('spreedme', 'Copy'))
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
				$.post(
				OC.generateUrl('/apps/spreed/api/room/') + _this.model.get('id'),
					{
						newParticipant: e.val
					}
				);
				$('body').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});
			this.ui.personSelectorInput.on('click', function() {
				$('body').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			this.ui.personSelectorInput.on('select2-loaded', function() {
				$('body').find('.avatar').each(function () {
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
