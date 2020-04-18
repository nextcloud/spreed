/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import { localMediaModel, localCallParticipantModel, callParticipantCollection } from '../utils/webrtc/index'

const call = {

	data() {
		return {
			screens: [],
			localMediaModel: localMediaModel,
			localCallParticipantModel: localCallParticipantModel,
		}
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	methods: {
	},

	watch: {

		localScreen: function(localScreen) {
			this._setScreenAvailable(localCallParticipantModel.attributes.peerId, localScreen)
		},

		callParticipantModels: function(models) {
			this.updateDataFromCallParticipantModels(models)
		},

		'speakers': function() {
			this._setPromotedParticipant()
		},

		'screenSharingActive': function() {
			this._setPromotedParticipant()
		},

		'screens': function() {
			this._setScreenVisible()
		},

	},

	computed: {
		callParticipantModels() {
			return callParticipantCollection.callParticipantModels
		},

		reversedCallParticipantModels() {
			return this.callParticipantModels.slice().reverse()
		},

		remoteParticipantsCount() {
			return this.callParticipantModels.length
		},

		callParticipantModelsWithScreen() {
			return this.callParticipantModels.filter(callParticipantModel => callParticipantModel.attributes.screen)
		},

		localScreen() {
			return localMediaModel.attributes.localScreen
		},

		screenSharingActive() {
			return this.screens.length > 0
		},
	},
}

export default call

export { localMediaModel, localCallParticipantModel, callParticipantCollection }
