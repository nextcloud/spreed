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

	var ITEM_TEMPLATE = '<a class="app-navigation-entry-link" href="#{{id}}"><div class="avatar" data-user="{{name}}"></div> {{displayName}}</a>'+
						'<div class="app-navigation-entry-utils">'+
							'<ul>'+
								'<li class="app-navigation-entry-utils-menu-button svg"><button></button></li>'+
							'</ul>'+
						'</div>'+
						'<div class="app-navigation-entry-menu">'+
							'<ul class="app-navigation-entry-menu-list">'+
								'<li>'+
									'<button class="add-person-button">'+
										'<span class="icon-add svg"></span>'+
										'<span>'+t('spreedme', 'Add person')+'</span>'+
									'</button>'+
								'</li>'+
								'<li>'+
									'<button class="share-group-button">'+
										'<span class="icon-share svg"></span>'+
										'<span>'+t('spreedme', 'Share group')+'</span>'+
									'</button>'+
								'</li>'+
								'<li>'+
									'<button class="leave-group-button">'+
										'<span class="icon-close svg"></span>'+
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
			this.initPersonSelector();

			if (this.model.get('type') === 2) { // group
				this.addTooltip();
			}

			if (this.model.get('active')) {
				this.$el.addClass('active');
			} else {
				this.$el.removeClass('active');
			}

			if (this.model.get('type') === 1) { // 1on1
				_.each(this.$el.find('.avatar'), function(a) {
					$(a).avatar($(a).data('user'), 32);
				});
			} else if (this.model.get('type') === 2) { // group
				_.each(this.$el.find('.avatar'), function(a) {
					$(a).addClass('icon-contacts-dark');
				});
			}

			this.toggleMenuClass();
		},
		events: {
			'click .app-navigation-entry-utils-menu-button button': 'toggleMenu',
			'click .app-navigation-entry-menu .add-person-button': 'addPerson',
			'click .app-navigation-entry-menu .share-group-button': 'shareGroup',
			'click .app-navigation-entry-menu .leave-group-button': 'leaveGroup',
		},
		ui: {
			'room': '.app-navigation-entry-link',
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
		addPerson: function() {
			this.ui.menuList.attr('style', 'display: none !important');
			this.ui.personSelectorForm.toggleClass('hidden');
			this.ui.personSelectorInput.select2('open');
		},
		shareGroup: function() {
			console.log("share group", this.model.get('id'));
		},
		leaveGroup: function() {
			//If user is in that room, it should leave that room first.
			if (this.model.get('active')) {
				OCA.SpreedMe.webrtc.leaveRoom();
				window.location.replace(window.location.href.slice(0, -window.location.hash.length));
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

							$.each(participants, function(participantId, participant) {
								if (participantId === user.value.shareWith) {
									isExactUserInGroup = true;
									return;
								}
							})

							if (!isExactUserInGroup) {
								results.push({ id: user.value.shareWith, displayName: user.label, type: "user"});
							}
						});

						$.each(response.ocs.data.users, function(id, user) {
							var isUserInGroup = false;

							$.each(participants, function(participantId, participant) {
								if (participantId === user.value.shareWith) {
									isUserInGroup = true;
									return;
								}
							})

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
