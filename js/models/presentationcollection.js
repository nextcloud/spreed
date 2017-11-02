/* global Backbone, OC, OCA */

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

(function(OCA, OC, Backbone) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Models = OCA.SpreedMe.Models || {};

	OCA.SpreedMe.Models.PresentationCollection = Backbone.Collection.extend({
		model: OCA.SpreedMe.Models.Presentation,
		active: null,

		getPresentationById: function(id) {
			return this.models.filter(function(c) {
				return c.id === id;
			})[0];
		},
		updatePresentation: function(p) {
			var id = p.id;
			var cur = this.getPresentationById(id);
			if (!cur) {
				console.log('No such presentation "%s"', id);
				return;
			}

			cur.set({
				name: p.name || '',
				size: p.size || 0,
			});
			this.trigger('update'); // TODO(leon): Why doesn't this fire automatically?
		},
		addPresentation: function(p) {
			var data = {
				id: p.token,
				name: p.name || '', // Immediately add name
			};
			this.add(data);
			this.updatePresentation(data);
			if (!this.active) {
				this.setActive(data.id);
			}
		},
		/**
		 * @param int id
		 * @returns {Array}
		 */
		setActive: function(id) {
			var p = this.getPresentationById(id);
			if (!p) {
				// Don't do anything if we don't have a presentation with this id
				console.log('No such presentation "%s"', id);
				return;
			}

			if (this.active) {
				this.active.set({isActive: false});
			}
			this.active = p;
			this.active.set({isActive: true});
			this.trigger('update'); // TODO(leon): Why doesn't this fire automatically?
		},
	});

})(OCA, OC, Backbone);
