/* global Backbone, OCA */

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

(function(OCA, Backbone) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Models = OCA.SpreedMe.Models || {};

	/**
	 * Model for the local storage of the browser.
	 *
	 * This makes possible to use the local storage of the browser as a Backbone
	 * model, for example, with a Marionette view.
	 *
	 * The local storage keys to handle must be specified in the "attributes"
	 * parameter of the constructor.
	 */
	var LocalStorageModel = Backbone.Model.extend({
		isNew: function() {
			return false;
		},
		sync: function(method, model, options) {
			if (method !== 'read' && method !== 'update') {
				throw 'Method not supported by LocalStorageModel: ' + method;
			}

			var response = {};

			if (method === 'read') {
				response = _.clone(model.attributes);
				_.each(response, function(value, attribute) {
					response[attribute] = localStorage.getItem(attribute);
				});
			} else {
				_.each(model.attributes, function(value, attribute) {
					localStorage.setItem(attribute, value);
				});
			}

			if (_.isFunction(options.success)) {
				options.success.call(this, response);
			}
		}
	});

	OCA.SpreedMe.Models.LocalStorageModel = LocalStorageModel;

})(OCA, Backbone);
