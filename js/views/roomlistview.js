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

	Handlebars.registerHelper('isGroupCall', function(options) {
		if(typeof this.type !== 'undefined') {
			if(this.type === "1") {
				return options.inverse(this);
			} else {
				return options.fn(this);
			}
		}
	});

	var uiChannel = Backbone.Radio.channel('ui');

	var ITEM_TEMPLATE = '<a href="#{{id}}"><div class="avatar" data-userName="{{name}}"></div> {{displayName}}</a>'+
						'<div class="app-navigation-entry-utils">'+
							'<ul>'+
								'{{#isGroupCall}}<li class="app-navigation-entry-utils-counter">{{count}}</li>{{/isGroupCall}}'+
								'<li class="app-navigation-entry-utils-menu-button svg"><button></button></li>'+
							'</ul>'+
						'</div>'+
						'<div class="app-navigation-entry-menu">'+
							'<ul>'+
								'<li>'+
									'<button>'+
										'<span class="icon-add svg"></span>'+
										'<span>'+t('spreedMe', 'Add person')+'</span>'+
									'</button>'+
								'</li>'+
							'</ul>'+
							'<ul>'+
								'<li>'+
									'<button>'+
										'<span class="icon-share svg"></span>'+
										'<span>'+t('spreedMe', 'Share group')+'</span>'+
									'</button>'+
								'</li>'+
							'</ul>'+
							'<ul>'+
								'<li>'+
									'<button>'+
										'<span class="icon-close svg"></span>'+
										'<span>'+t('spreedMe', 'Leave group')+'</span>'+
									'</button>'+
								'</li>'+
							'</ul>'+
						'</div>';

	var RoomItenView = Marionette.View.extend({
		tagName: 'li',
		modelEvents: {
			change: 'render'
		},
		initialize: function() {
			// Add class to every room list item to detect it on click.
			this.$el.addClass('room-list-item');

			this.listenTo(uiChannel, 'document:click', function(event) {
				var target = $(event.target);

				if (!this.$el.is(target.closest('li.room-list-item'))) {
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

			_.each(this.$el.find('.avatar'), function(a) {
				$(a).avatar($(a).data('username'), 32);
			});
		},
		events: {
			'click .app-navigation-entry-utils-menu-button button': 'toggleMenu',
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
		}
	});

	var RoomListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		childView: RoomItenView
	});

	OCA.SpreedMe.Views.RoomListView = RoomListView;

})(OCA, Marionette, Handlebars);
