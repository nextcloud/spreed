<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<div class="turn-server">
		<select
			class="schemes"
			:value="schemes"
			:disabled="loading"
			:aria-label="t('spreed', 'TURN server schemes')"
			@input="updateSchemes">
			<option value="turn,turns">
				{{ t('spreed', '{option1} and {option2}', { option1: 'turn:', option2: 'turns:' }) }}
			</option>
			<option value="turn">
				{{ t('spreed', '{option} only', { option: 'turn:' }) }}
			</option>
			<option value="turns">
				{{ t('spreed', '{option} only', { option: 'turns:' }) }}
			</option>
		</select>

		<input
			ref="turn_server"
			v-tooltip.auto="turnServerError"
			type="text"
			name="turn_server"
			placeholder="turnserver:port"
			:class="turnServerClasses"
			:value="server"
			:disabled="loading"
			:aria-label="t('spreed', 'TURN server URL')"
			@input="updateServer">
		<input
			ref="turn_secret"
			type="text"
			name="turn_secret"
			placeholder="secret"
			:value="secret"
			:disabled="loading"
			:aria-label="t('spreed', 'TURN server secret')"
			@input="updateSecret">

		<select
			class="protocols"
			:value="protocols"
			:disabled="loading"
			:aria-label="t('spreed', 'TURN server protocols')"
			@input="updateProtocols">
			<option value="udp,tcp">
				{{ t('spreed', '{option1} and {option2}', { option1: 'UDP', option2: 'TCP' }) }}
			</option>
			<option value="udp">
				{{ t('spreed', '{option} only', { option: 'UDP' }) }}
			</option>
			<option value="tcp">
				{{ t('spreed', '{option} only', { option: 'TCP' }) }}
			</option>
		</select>

		<a
			v-show="!loading"
			v-tooltip.auto="testResult"
			class="icon"
			:class="testIconClasses"
			@click="testServer" />
		<a
			v-show="!loading"
			v-tooltip.auto="t('spreed', 'Delete this server')"
			class="icon icon-delete"
			@click="removeServer" />
	</div>
</template>

<script>
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import hmacSHA1 from 'crypto-js/hmac-sha1'
import Base64 from 'crypto-js/enc-base64'
import debounce from 'debounce'

export default {
	name: 'TurnServer',

	directives: {
		tooltip: Tooltip,
	},

	props: {
		schemes: {
			type: String,
			default: '',
			required: true,
		},
		server: {
			type: String,
			default: '',
			required: true,
		},
		secret: {
			type: String,
			default: '',
			required: true,
		},
		protocols: {
			type: String,
			default: '',
			required: true,
		},
		index: {
			type: Number,
			default: -1,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			testing: false,
			testingError: false,
			testingSuccess: false,
		}
	},

	computed: {
		turnServerError() {
			if (this.schemes.includes('turns') && /^(?:\d{1,3}\.){3}\d{1,3}(?::\d{1,5})?$/.test(this.server.trim())) {
				return t('spreed', '{schema} scheme must be used with a domain', { schema: 'turns:' })
			}

			return false
		},
		turnServerClasses() {
			return {
				error: this.turnServerError,
			}
		},
		testIconClasses() {
			return {
				'icon-category-monitoring': !this.testing && !this.testingError && !this.testingSuccess,
				'icon-loading-small': this.testing,
				'icon-error': this.testingError,
				'icon-checkmark': this.testingSuccess,
			}
		},
		testResult() {
			if (this.testingSuccess) {
				return t('spreed', 'OK: Successful ICE candidates returned by the TURN server')
			} else if (this.testingError) {
				return t('spreed', 'Error: No working ICE candidates returned by the TURN server')
			} else if (this.testing) {
				return t('spreed', 'Testing whether the TURN server returns ICE candidates')
			}
			return t('spreed', 'Test this server')
		},
	},

	mounted() {
		this.testing = false
		this.testingError = false
		this.testingSuccess = false
	},

	methods: {
		debounceTestServer: debounce(function() {
			this.testServer()
		}, 1000),

		testServer() {
			this.testing = true
			this.testingError = false
			this.testingSuccess = false

			const schemes = this.schemes.split(',')
			const protocols = this.protocols.split(',')
			if (!schemes.length || !this.server || !this.secret || !protocols.length) {
				return
			}

			const urls = []
			for (let i = 0; i < schemes.length; i++) {
				for (let j = 0; j < protocols.length; j++) {
					urls.push(schemes[i] + ':' + this.server + '?transport=' + protocols[j])
				}
			}

			const expires = Math.round((new Date()).getTime() / 1000) + (5 * 60)
			const username = expires + ':turn-test-user'
			const password = Base64.stringify(hmacSHA1(username, this.secret))

			const iceServer = {
				username,
				credential: password,
				urls,
			}

			// Create a PeerConnection with no streams, but force a m=audio line.
			const config = {
				iceServers: [
					iceServer,
				],
				iceTransportPolicy: 'relay',
			}
			const offerOptions = {
				offerToReceiveAudio: 1,
			}
			console.info('Creating PeerConnection with', config)
			const candidates = []

			const pc = new RTCPeerConnection(config)
			const timeout = setTimeout(function() {
				this.notifyTurnResult(candidates, timeout)
				pc.close()
			}.bind(this), 10000)
			pc.onicecandidate = this.iceCallback.bind(this, pc, candidates, timeout)
			pc.onicegatheringstatechange = this.gatheringStateChange.bind(this, pc, candidates, timeout)
			pc.createOffer(
				offerOptions
			).then(
				function(description) {
					pc.setLocalDescription(description)
				},
				function(error) {
					console.error('Error creating offer', error)
					this.notifyTurnResult(candidates, timeout)
					pc.close()
				}.bind(this)
			)
		},

		iceCallback(pc, candidates, timeout, e) {
			if (e.candidate) {
				candidates.push(this.parseCandidate(e.candidate.candidate))
			} else if (!('onicegatheringstatechange' in RTCPeerConnection.prototype)) {
				pc.close()
				this.notifyTurnResult(candidates, timeout)
			}
		},

		notifyTurnResult(candidates, timeout) {
			console.info('Received candidates', candidates)

			const types = candidates.map((cand) => cand.type)

			this.testing = false
			if (types.indexOf('relay') === -1) {
				this.testingError = true
			} else {
				this.testingSuccess = true
			}

			setTimeout(() => {
				this.testingError = false
				this.testingSuccess = false
			}, 30000)

			clearTimeout(timeout)
		},

		// Parse a candidate:foo string into an object, for easier use by other methods.
		parseCandidate(text) {
			const candidateStr = 'candidate:'
			const pos = text.indexOf(candidateStr) + candidateStr.length
			const parts = text.substr(pos).split(' ')

			return {
				component: parts[1],
				type: parts[7],
				foundation: parts[0],
				protocol: parts[2],
				address: parts[4],
				port: parts[5],
				priority: parts[3],
			}
		},

		gatheringStateChange(pc, candidates, timeout) {
			if (pc.iceGatheringState !== 'complete') {
				return
			}

			pc.close()
			this.notifyTurnResult(candidates, timeout)
		},

		removeServer() {
			this.$emit('remove-server', this.index)
		},
		updateSchemes(event) {
			this.$emit('update:schemes', event.target.value)
			this.debounceTestServer()
		},
		updateServer(event) {
			this.$emit('update:server', event.target.value)
			this.debounceTestServer()
		},
		updateSecret(event) {
			this.$emit('update:secret', event.target.value)
			this.debounceTestServer()
		},
		updateProtocols(event) {
			this.$emit('update:protocols', event.target.value)
			this.debounceTestServer()
		},
	},
}
</script>

<style lang="scss" scoped>
.turn-server {
	height: 44px;
	display: flex;
	align-items: center;

	&.error {
		border: solid 1px var(--color-error);
	}
}
</style>
