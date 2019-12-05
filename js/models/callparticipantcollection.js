/* global Backbone, OCA */

/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

	OCA.Talk = OCA.Talk || {};
	OCA.Talk.Models = OCA.Talk.Models || {};

	var CallParticipantCollection = Backbone.Collection.extend({

		model: function(attrs, options) {
			return new OCA.Talk.Models.CallParticipantModel(attrs, options);
		},

		sync: function(method, model, options) {
			throw 'Method not supported by CallParticipantCollection: ' + method;
		},

	});

	OCA.Talk.Models.CallParticipantCollection = CallParticipantCollection;

})(OCA, Backbone);
