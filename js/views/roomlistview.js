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

	var ITEM_TEMPLATE = '<a href="#{{id}}">{{name}}</a>'+
						'<span class="utils">'+
									'<span class="action">{{count}}</span>'+
									'<span class="action icon-more" href="#" title="More" role="button"></span>'+
								'</span>'+
								'<div id="more-actions-{{name}}" class="app-navigation-entry-menu">'+
									'<ul>'+
										'<li><button>'+
												'<span class="icon-add svg"></span>'+
												'<span>Add person</span>'+
											'</button>'+
										'</li>'+
										'<li><button>'+
												'<span class="icon-share svg"></span>'+
												'<span>Share group</span>'+
											'</button>'+
										'</li>'+
										'<li><button>'+
												'<span class="icon-close svg"></span>'+
												'<span>Leave group</span>'+
											'</button>'+
										'</li>'+
									'</ul>'+
								'</div>';

	var RoomItenView = Marionette.View.extend({
		tagName: 'li',
		modelEvents: {
			change: 'render'
		},
		onRender: function() {
			if (this.model.get('active')) {
				this.$el.addClass('active');
			} else {
				this.$el.removeClass('active');
			}
		},
		template: Handlebars.compile(ITEM_TEMPLATE)
	});

	var RoomListView = Marionette.CollectionView.extend({
		tagName: 'ul',
		childView: RoomItenView
	});

	OCA.SpreedMe.Views.RoomListView = RoomListView;

})(OCA, Marionette, Handlebars);
