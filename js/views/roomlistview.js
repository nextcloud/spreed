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

(function(OCA, Marionette, Handlebars) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var uiChannel = Backbone.Radio.channel('ui');

	var ITEM_TEMPLATE = '<a href="#{{id}}"><div class="avatar" data-user="{{name}}"></div> {{displayName}}</a>'+
						'<div class="app-navigation-entry-utils">'+
							'<ul>'+
								'<li class="app-navigation-entry-utils-menu-button svg"><button></button></li>'+
							'</ul>'+
						'</div>'+
						'<div class="app-navigation-entry-menu">'+
							'<ul>'+
								'<li>'+
									'<button class="add-person-button">'+
										'<span class="icon-add svg"></span>'+
										'<span>'+t('spreedme', 'Add person')+'</span>'+
									'</button>'+
								'</li>'+
							'</ul>'+
							'<ul>'+
								'<li>'+
									'<button class="share-group-button">'+
										'<span class="icon-share svg"></span>'+
										'<span>'+t('spreedme', 'Share group')+'</span>'+
									'</button>'+
								'</li>'+
							'</ul>'+
							'<ul>'+
								'<li>'+
									'<button class="leave-group-button">'+
										'<span class="icon-close svg"></span>'+
										'<span>'+t('spreedme', 'Leave group')+'</span>'+
									'</button>'+
								'</li>'+
							'</ul>'+
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
			'menu': 'div.app-navigation-entry-menu',
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
			console.log("add person", this.model.get('id'));
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
		}
	});

	var RoomListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		childView: RoomItenView
	});

	OCA.SpreedMe.Views.RoomListView = RoomListView;

})(OCA, Marionette, Handlebars);
