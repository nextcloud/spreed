<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="turn-server">
		<NcSelect v-model="turnSchemes"
			class="turn-server__select"
			name="turn_schemes"
			:disabled="loading"
			:aria-label-combobox="t('spreed', 'TURN server schemes')"
			:options="schemesOptions"
			:clearable="false"
			:searchable="false"
			label="label"
			track-by="value"
			no-wrap />

		<NcTextField ref="turn_server"
			v-model="turnServer"
			name="turn_server"
			placeholder="turnserver:port"
			class="turn-server__textfield"
			:class="{ error: turnServerError }"
			:title="turnServerError"
			:disabled="loading"
			:label="t('spreed', 'TURN server URL')" />

		<NcPasswordField ref="turn_secret"
			v-model="turnSecret"
			name="turn_secret"
			as-text
			placeholder="secret"
			class="turn-server__textfield"
			:disabled="loading"
			:label="t('spreed', 'TURN server secret')" />

		<NcSelect v-model="turnProtocols"
			class="turn-server__select"
			name="turn_protocols"
			:disabled="loading"
			:aria-label-combobox="t('spreed', 'TURN server protocols')"
			:options="protocolOptions"
			:clearable="false"
			:searchable="false"
			label="label"
			track-by="value"
			no-wrap />

		<NcButton v-show="!loading"
			type="tertiary"
			:aria-label="testResult"
			:disabled="!testAvailable"
			@click="testServer">
			<template #icon>
				<span v-if="testing" class="icon icon-loading-small" />
				<AlertCircle v-else-if="testingError" :fill-color="'#E9322D'" />
				<Check v-else-if="testingSuccess" :fill-color="'#46BA61'" />
				<Pulse v-else />
			</template>
		</NcButton>
		<NcButton v-show="!loading"
			type="tertiary"
			:aria-label="t('spreed', 'Delete this server')"
			@click="removeServer">
			<template #icon>
				<Delete :size="20" />
			</template>
		</NcButton>
	</li>
</template>

<script>
import Base64 from 'crypto-js/enc-base64.js'
import hmacSHA1 from 'crypto-js/hmac-sha1.js'
import debounce from 'debounce'
import webrtcSupport from 'webrtcsupport'

import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Pulse from 'vue-material-design-icons/Pulse.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { isCertificateValid } from '../../services/certificateService.ts'
import { convertToUnix } from '../../utils/formattedTime.ts'

export default {
	name: 'TurnServer',

	components: {
		AlertCircle,
		Check,
		Delete,
		NcButton,
		NcSelect,
		NcTextField,
		NcPasswordField,
		Pulse,
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

	emits: ['remove-server', 'update:schemes', 'update:server', 'update:secret', 'update:protocols'],

	data() {
		return {
			testing: false,
			testingError: false,
			testingSuccess: false,
			debounceTestServer: () => {},
		}
	},

	computed: {
		turnServer: {
			get() {
				return this.server
			},

			set(value) {
				this.updateServer(value)
			}
		},

		turnSchemes: {
			get() {
				return this.schemesOptions.find((i) => i.value === this.schemes)
			},

			set(value) {
				this.updateSchemes(value)
			}
		},

		turnProtocols: {
			get() {
				return this.protocolOptions.find((i) => i.value === this.protocols)
			},

			set(value) {
				this.updateProtocols(value)
			}
		},

		turnSecret: {
			get() {
				return this.secret
			},

			set(value) {
				this.updateSecret(value)
			},
		},

		turnServerError() {
			if (this.schemes.includes('turns') && /^(?:\d{1,3}\.){3}\d{1,3}(?::\d{1,5})?$/.test(this.server.trim())) {
				return t('spreed', '{schema} scheme must be used with a domain', { schema: 'turns:' })
			}

			return false
		},

		protocolOptions() {
			return [
				{ value: 'udp,tcp', label: t('spreed', '{option1} and {option2}', { option1: 'UDP', option2: 'TCP' }) },
				{ value: 'udp', label: t('spreed', '{option} only', { option: 'UDP' }) },
				{ value: 'tcp', label: t('spreed', '{option} only', { option: 'TCP' }) },
			]
		},

		schemesOptions() {
			return [
				{ value: 'turn,turns', label: t('spreed', '{option1} and {option2}', { option1: 'turn:', option2: 'turns:' }) },
				{ value: 'turn', label: t('spreed', '{option} only', { option: 'turn:' }) },
				{ value: 'turns', label: t('spreed', '{option} only', { option: 'turns:' }) },
			]
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

		testAvailable() {
			const schemes = this.schemes.split(',')
			const protocols = this.protocols.split(',')
			return !!(schemes.length && this.server && this.secret && protocols.length)
		},
	},

	mounted() {
		this.debounceTestServer = debounce(this.testServer, 1000)
		this.testing = false
		this.testingError = false
		this.testingSuccess = false
	},

	beforeDestroy() {
		this.debounceTestServer.clear?.()
	},

	methods: {
		t,
		testServer() {
			this.testingError = false
			this.testingSuccess = false

			const schemes = this.schemes.split(',')
			const protocols = this.protocols.split(',')
			if (!this.testAvailable) {
				return
			}

			this.testing = true

			const urls = []
			for (let i = 0; i < schemes.length; i++) {
				for (let j = 0; j < protocols.length; j++) {
					urls.push(schemes[i] + ':' + this.server + '?transport=' + protocols[j])
				}
			}

			const expires = convertToUnix(Date.now()) + 5 * 60
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
			const timeout = setTimeout(() => {
				this.notifyTurnResult(candidates, timeout)
				pc.close()
			}, 10000)
			pc.onicecandidate = this.iceCallback.bind(this, pc, candidates, timeout)
			pc.onicegatheringstatechange = this.gatheringStateChange.bind(this, pc, candidates, timeout)

			// This test will always fail without a data channel on Safari
			if (webrtcSupport.supportDataChannel) {
				pc.createDataChannel('status')
			}

			pc.createOffer(
				offerOptions,
			).then(
				(description) => {
					pc.setLocalDescription(description)
				},
				(error) => {
					console.error('Error creating offer', error)
					this.notifyTurnResult(candidates, timeout)
					pc.close()
				},
			)
		},

		iceCallback(pc, candidates, timeout, e) {
			if (e.candidate) {
				const parseCandidate = this.parseCandidate(e.candidate.candidate)
				candidates.push(parseCandidate)

				// We received a relay candidate, no need to wait any longer
				if (parseCandidate.type.includes('relay')) {
					pc.close()
					this.notifyTurnResult(candidates, timeout)
				}
			} else if (!('onicegatheringstatechange' in RTCPeerConnection.prototype)) {
				pc.close()
				this.notifyTurnResult(candidates, timeout)
			}
		},

		notifyTurnResult(candidates, timeout) {
			console.info('Received candidates', candidates)

			const types = candidates.map((cand) => cand.type)

			if (types.includes('relay')) {
				if (!this.schemes.includes('turns')) {
					// No 'turns' is used and we received relay candidates -> TURN is working
					this.testing = false
					this.testingSuccess = true
				} else {
					// We received relay candidates, but since 'turns' is used, we check the certificate additionally
					isCertificateValid(this.server).then((isValid) => {
						this.testing = false
						this.testingSuccess = isValid
						this.testingError = !isValid
					})
				}
			} else {
				this.testing = false
				this.testingError = true
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
			const parts = text.slice(pos).split(' ')

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
			this.$emit('update:schemes', event.value)
			this.debounceTestServer()
		},

		updateServer(value) {
			this.$emit('update:server', value)
			this.debounceTestServer()
		},

		updateSecret(value) {
			this.$emit('update:secret', value)
			this.debounceTestServer()
		},

		updateProtocols(event) {
			this.$emit('update:protocols', event.value)
			this.debounceTestServer()
		},
	},
}
</script>

<style lang="scss" scoped>
.turn-server {
	display: grid;
	grid-template-columns: minmax(100px, 180px) 1fr 1fr minmax(100px, 180px) var(--default-clickable-area) var(--default-clickable-area);
	grid-column-gap: 4px;
	align-items: center;
	margin-bottom: 4px;

	& &__textfield {
		&.error :deep(.input-field__input) {
			border: 2px solid var(--color-error);
		}
	}

	& &__select {
		margin-block-start: 6px;
		min-width: unset;
	}
}
</style>
