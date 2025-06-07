<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<TransitionWrapper class="toaster"
		name="toast"
		tag="ul"
		group>
		<li v-for="toast in toasts"
			:key="toast.seed"
			class="toast"
			:style="styled(toast.name, toast.seed)">
			<img v-if="toast.reactionURL"
				class="toast__reaction-img"
				:src="toast.reactionURL"
				:alt="toast.reaction"
				width="34"
				height="34">
			<span v-else class="toast__reaction">
				{{ toast.reaction }}
			</span>
			<span class="toast__name">
				{{ toast.name }}
			</span>
		</li>
	</TransitionWrapper>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { imagePath } from '@nextcloud/router'
import usernameToColor from '@nextcloud/vue/functions/usernameToColor'
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'
import { useActorStore } from '../../../stores/actor.ts'
import { useGuestNameStore } from '../../../stores/guestName.js'

const reactions = {
	'❤️': 'Heart.gif',
	'🎉': 'Party.gif',
	'👏': 'Clap.gif',
	'👋': 'Wave.gif',
	'👍': 'Thumbs-up.gif',
	'👎': 'Thumbs-down.gif',
	'🔥': 'Fire.gif',
	'😂': 'Joy.gif',
	'🤩': 'Star-struck.gif',
	'🤔': 'Thinking-face.gif',
	'😲': 'Surprised.gif',
	'😥': 'Concerned.gif',
}

export default {
	name: 'ReactionToaster',

	components: {
		TransitionWrapper,
	},

	props: {
		/**
		 * The conversation token
		 */
		token: {
			type: String,
			required: true,
		},

		/**
		 * Supported reactions
		 */
		supportedReactions: {
			type: Array,
			validator: (prop) => prop.every((e) => typeof e === 'string'),
			required: true,
		},

		callParticipantModels: {
			type: Array,
			required: true,
		},
	},

	setup() {
		const guestNameStore = useGuestNameStore()
		return {
			guestNameStore,
			actorStore: useActorStore(),
		}
	},

	data() {
		return {
			registeredModels: {},
			reactionsQueue: [],
			intervalId: null,
			animationLength: 2000,
			toasts: [],
		}
	},

	computed: {
		participants() {
			return this.$store.getters.participantsList(this.token)
		},
	},

	watch: {
		callParticipantModels(models) {
			// subscribe connected models for reaction signals
			const addedModels = models.filter((model) => !this.registeredModels[model.attributes.peerId])
			addedModels.forEach((addedModel) => {
				this.registeredModels[addedModel.attributes.peerId] = addedModel
				this.registeredModels[addedModel.attributes.peerId].on('reaction', this.handleReaction)
			})

			// unsubscribe disconnected models
			const removedModelIds = Object.keys(this.registeredModels).filter((registeredModelId) => !models.find((model) => model.attributes.peerId === registeredModelId))
			removedModelIds.forEach((removedModelId) => {
				this.registeredModels[removedModelId].off('reaction', this.handleReaction)
				delete this.registeredModels[removedModelId]
			})
		},
	},

	mounted() {
		this.intervalId = setInterval(this.processReactionsQueue, this.animationLength / 4)
		subscribe('send-reaction', this.handleOwnReaction)
	},

	beforeDestroy() {
		clearInterval(this.intervalId)
		unsubscribe('send-reaction', this.handleOwnReaction)
		Object.keys(this.registeredModels).forEach((modelId) => {
			this.registeredModels[modelId].off('reaction', this.handleReaction)
			delete this.registeredModels[modelId]
		})
	},

	methods: {
		t,
		handleOwnReaction({ model, reaction }) {
			this.handleReaction(model, reaction, true)
		},

		handleReaction(model, reaction, isLocalModel = false) {
			// prevent spamming to queue from a single account
			if (this.reactionsQueue.some((item) => item.id === model.attributes.peerId)) {
				return
			}

			// prevent receiving anything rather than defined reactions in capabilities
			if (!this.supportedReactions.includes(reaction)) {
				return
			}

			this.reactionsQueue.push({
				id: model.attributes.peerId,
				reaction,
				reactionURL: this.getReactionURL(reaction),
				name: isLocalModel
					? this.actorStore.displayName || t('spreed', 'Guest')
					: this.getParticipantName(model),
				seed: Math.random(),
			})
		},

		processReactionsQueue() {
			if (this.reactionsQueue.length > 0) {
				// Move reactions from queue to visible array
				this.toasts.push(this.reactionsQueue.shift())

				// Delete reactions from array after animation ends
				setTimeout(() => {
					this.toasts.shift()
				}, this.animationLength)
			}
		},

		getParticipantName(model) {
			const { name, nextcloudSessionId } = model.attributes
			if (name) {
				return name
			}

			const participant = this.participants.find((participant) => participant.sessionIds.includes(nextcloudSessionId))
			if (participant?.displayName) {
				return participant.displayName
			}

			return this.guestNameStore.getGuestName(this.token, Hex.stringify(SHA1(nextcloudSessionId)))
		},

		getReactionURL(emoji) {
			return reactions[emoji]
				? imagePath('spreed', 'emojis/' + reactions[emoji])
				: undefined
		},

		styled(name, seed) {
			const color = usernameToColor(name)

			return {
				'--background-color': `rgb(${color.r}, ${color.g}, ${color.b})`,
				'--animation-length': `${this.animationLength + 300}ms`,
				'--horizontal-offset': `${10 + 20 * seed}%`,
				'--vertical-offset': 30 + 5 * seed,
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.toaster {
	position: absolute;
	bottom: 20px;
	inset-inline-start: 0;
	display: flex;
	flex-direction: column;
	gap: 20px;
	width: 100%;
	z-index: 1;
}

.toast {
	position: absolute;
	bottom: 0;
	inset-inline-start: var(--horizontal-offset, 0);
	display: flex;
	align-items: center;
	gap: 8px;
	animation: toast-floating var(--animation-length) linear;

	&__reaction {
		font-size: 250%;
		line-height: 100%;

		@media only screen and (max-width: 1920px) {
			& {
				font-size: 150%;
			}
			&-img {
				width: 30px;
				height: 30px;
			}
		}
	}

	&__name {
		padding: 8px 12px;
		border-radius: 6px;
		line-height: 100%;
		white-space: nowrap;
		color: #ffffff;
		background-color: var(--background-color);
		box-shadow: 1px 1px 4px var(--color-box-shadow);
	}
}

@keyframes toast-floating {
	0% {
		transform: translateY(0);
		opacity: 1;
	}
	50% {
		transform: translateY(calc(-0.5 * var(--vertical-offset) * 1vh));
		opacity: 1;
	}
	100% {
		transform: translateY(calc(-1 * var(--vertical-offset) * 1vh));
		opacity: 0;
	}
}
</style>
