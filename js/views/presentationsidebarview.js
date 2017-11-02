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


(function(OC, OCA, Marionette, Handlebars, consts) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var CHANNEL_NAME_PRESENTATIONS = 'presentations';
	var ITEM_TEMPLATE = '' +
		'{{#if canModerate}}' +
			'<div class="presentation {{#if isActive}}active{{/if}}">' +
				'<div class="fa filetype fa-file-pdf-o"></div>' +
				'<div class="caption">' +
					'<span class="name">{{name}}</span>' +
					'<br />' +
					'<span class="size">{{humanReadableSize size}}</span>' +
				'</div>' +
			'</div>' +
		'{{/if}}';

	// Shamelessly stolen from https://stackoverflow.com/a/14919494
	// Thank you, mpen
	Handlebars.registerHelper('humanReadableSize', function humanFileSize(bytes) {
		var si = true;
		var thresh = si ? 1000 : 1024;
		if(Math.abs(bytes) < thresh) {
			return bytes + ' B';
		}
		var units = si
			? ['kB','MB','GB','TB','PB','EB','ZB','YB']
			: ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
		var u = -1;
		do {
			bytes /= thresh;
			++u;
		} while(Math.abs(bytes) >= thresh && u < units.length - 1);
		return bytes.toFixed(1)+' '+units[u];
	});

	OCA.SpreedMe.Views.PresentationSidebarView = Marionette.CollectionView.extend({
		tagName: 'ul',
		className: 'presentationSidebarWithList',
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
		initialize: function() {
			var chan = this.getChannel(CHANNEL_NAME_PRESENTATIONS);
			chan.on(consts.EVENT_TYPES.PRESENTATION_CURRENT, _.bind(function(data) {
				this.options.collection.setActive(data.payload.id);
			}, this));
			chan.on(consts.EVENT_TYPES.PRESENTATION_ADDED, _.bind(function(data) {
				this.options.collection.addPresentation(data.payload);
			}, this));
			chan.on(consts.EVENT_TYPES.PRESENTATION_SWITCH, _.bind(function(data) {
				this.options.collection.setActive(data.payload);
			}, this));
			chan.on(consts.EVENT_TYPES.MODEL_CHANGE, _.bind(function(data) {
				this.options.collection.updatePresentation(data.payload);
			}, this));
		},
		getChannel: function(name) {
			return this.options.channels[name];
		},
		// Event handlers
		activate: function(id) {
			var chan = this.getChannel(CHANNEL_NAME_PRESENTATIONS);
			chan.trigger('activate', id);
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
				}
			},
			initialize: function() {
			},
			templateContext: function() {
				var id = this.model.get('id');
				var isActive = this.model.get('isActive');
				var name = this.model.get('name');
				var size = this.model.get('size');
				var canModerate =
					// Current user must be owner
					OCA.SpreedMe.app.activeRoom.get('participantType') === OCA.SpreedMe.app.OWNER
					// .. or moderator
					|| OCA.SpreedMe.app.activeRoom.get('participantType') === OCA.SpreedMe.app.MODERATOR
				;

				return {
					id: id,
					isActive: isActive,
					name: name,
					size: size,
					canModerate: canModerate,
				};
			},
			onRender: function() {
			},
			events: {
				'click .presentation': 'activate',
			},
			template: Handlebars.compile(ITEM_TEMPLATE),

			activate: function() {
				var isActive = this.model.get('isActive');
				if (!isActive) {
					this._parent.activate(this.model.get('id'));
				}
			},
		}),
	});

})(OC, OCA, Marionette, Handlebars, OCA.SpreedMe.Presentation.consts);
