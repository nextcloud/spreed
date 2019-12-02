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

	var ConnectionState = {
		NEW: 'new',
		CHECKING: 'checking',
		CONNECTED: 'connected',
		COMPLETED: 'completed',
		DISCONNECTED: 'disconnected',
		DISCONNECTED_LONG: 'disconnected-long', // Talk specific
		FAILED: 'failed',
		FAILED_NO_RESTART: 'failed-no-restart', // Talk specific
		CLOSED: 'closed',
	};

	var CallParticipantModel = Backbone.Model.extend({

		defaults: {
			peerId: null,
			connectionState: ConnectionState.NEW,
		},

		sync: function(method, model, options) {
			throw 'Method not supported by CallParticipantModel: ' + method;
		},

		initialize: function(options) {
			this._handleExtendedIceConnectionStateChangeBound = this._handleExtendedIceConnectionStateChange.bind(this);
		},

		setPeer: function(peer) {
			if (peer && this.get('peerId') !== peer.id) {
				console.warn('Mismatch between stored peer ID and ID of given peer: ', this.get('peerId'), peer.id);
			}

			if (this._peer) {
				this._peer.off('extendedIceConnectionStateChange', this._handleExtendedIceConnectionStateChangeBound);
			}

			this._peer = peer;

			// Special case when the participant has no streams.
			if (!this._peer) {
				this.set('connectionState', ConnectionState.COMPLETED);

				return;
			}

			// Reset state that depends on the Peer object.
			this._handleExtendedIceConnectionStateChange(this._peer.pc.iceConnectionState);

			this._peer.on('extendedIceConnectionStateChange', this._handleExtendedIceConnectionStateChangeBound);
		},

		_handleExtendedIceConnectionStateChange: function(extendedIceConnectionState) {
			switch (extendedIceConnectionState) {
				case 'new':
					this.set('connectionState', ConnectionState.NEW);
					break;
				case 'checking':
					this.set('connectionState', ConnectionState.CHECKING);
					break;
				case 'connected':
					this.set('connectionState', ConnectionState.CONNECTED);
					break;
				case 'completed':
					this.set('connectionState', ConnectionState.COMPLETED);
					break;
				case 'disconnected':
					this.set('connectionState', ConnectionState.DISCONNECTED);
					break;
				case 'disconnected-long':
					this.set('connectionState', ConnectionState.DISCONNECTED_LONG);
					break;
				case 'failed':
					this.set('connectionState', ConnectionState.FAILED);
					break;
				case 'failed-no-restart':
					this.set('connectionState', ConnectionState.FAILED_NO_RESTART);
					break;
				case 'closed':
					this.set('connectionState', ConnectionState.CLOSED);
					break;
				default:
					console.error('Unexpected (extended) ICE connection state: ', extendedIceConnectionState);
			}
		},

	});

	OCA.Talk.Models.CallParticipantModel = CallParticipantModel;
	OCA.Talk.Models.CallParticipantModel.ConnectionState = ConnectionState;

})(OCA, Backbone);
