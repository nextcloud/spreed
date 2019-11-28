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

	var LocalCallParticipantModel = Backbone.Model.extend({

		defaults: {
			guestName: null,
		},

		sync: function(method, model, options) {
			throw 'Method not supported by LocalCallParticipantModel: ' + method;
		},

		setWebRtc: function(webRtc) {
			this._webRtc = webRtc;

			// The webRtc object is assumed to be brand new, so the default
			// state matches the state of the object.
			this.set(this.defaults);
		},

		setGuestName: function(guestName) {
			if (!this._webRtc) {
				throw 'WebRtc not initialized yet';
			}

			this.set('guestName', guestName);

			this._webRtc.sendDirectlyToAll('status', 'nickChanged', guestName);
		},

	});

	OCA.Talk.Models.LocalCallParticipantModel = LocalCallParticipantModel;

})(OCA, Backbone);
