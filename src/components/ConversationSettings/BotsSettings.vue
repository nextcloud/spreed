<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="bots-settings">
		<p class="bots-settings__hint">
			{{ botsSettingsDescription }}
		</p>

		<ul v-if="bots.length">
			<li
				v-for="bot in bots"
				:key="bot.id"
				class="bots-settings__item">
				<div class="bots-settings__item-info">
					<span class="bots-settings__item-name">
						{{ bot.name }}
					</span>
					<span class="bots-settings__item-description">
						{{ bot.description ?? t('spreed', 'Description is not provided') }}
					</span>
					<NcNoteCard v-if="isBotUnavailable(bot)" type="warning">
						<template #icon>
							<IconCancel :size="20" />
						</template>
						{{ t('spreed', 'The bot is not available anymore') }}
					</NcNoteCard>
				</div>
				<div v-if="isLoading[bot.id]" class="bots-settings__item-loader icon icon-loading-small" />
				<NcButton
					class="bots-settings__item-button"
					:variant="buttonType(bot)"
					:disabled="isBotLocked(bot) || isLoading[bot.id]"
					@click="toggleBotState(bot)">
					{{ toggleButtonTitle(bot) }}
				</NcButton>
			</li>
		</ul>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconCancel from 'vue-material-design-icons/Cancel.vue'
import { BOT } from '../../constants.ts'
import { useBotsStore } from '../../stores/bots.ts'

export default {
	name: 'BotsSettings',

	components: {
		NcButton,
		NcNoteCard,
		IconCancel,
	},

	props: {
		/**
		 * The conversation's token
		 */
		token: {
			type: String,
			required: true,
		},
	},

	setup() {
		const botsStore = useBotsStore()

		return {
			botsStore,
		}
	},

	data() {
		return {
			isLoading: {},
		}
	},

	computed: {
		bots() {
			return this.botsStore.getConversationBots(this.token)
		},

		botsSettingsDescription() {
			return this.bots.length
				? t('spreed', 'The following bots can be enabled in this conversation. Reach out to your administration to get more bots installed on this server.')
				: t('spreed', 'No bots are installed on this server. Reach out to your administration to get bots installed on this server.')
		},
	},

	async created() {
		(await this.botsStore.loadConversationBots(this.token)).forEach((id) => {
			this.isLoading[id] = false
		})
	},

	methods: {
		t,
		isBotLocked(bot) {
			return bot.state === BOT.STATE.NO_SETUP
		},

		isBotUnavailable(bot) {
			return bot.state === BOT.STATE.UNAVAILABLE
		},

		async toggleBotState(bot) {
			if (this.isBotLocked(bot)) {
				return
			}
			this.isLoading[bot.id] = true
			await this.botsStore.toggleBotState(this.token, bot)
			this.isLoading[bot.id] = false
		},

		buttonType(bot) {
			if (this.isBotUnavailable(bot)) {
				return 'warning'
			}

			return bot.state === BOT.STATE.ENABLED ? 'primary' : 'secondary'
		},

		toggleButtonTitle(bot) {
			if (this.isBotUnavailable(bot)) {
				return t('spreed', 'Disable')
			}

			if (this.isBotLocked(bot)) {
				return t('spreed', 'Enabled')
			}

			return bot.state === BOT.STATE.ENABLED ? t('spreed', 'Disable') : t('spreed', 'Enable')
		},
	},
}
</script>

<style lang="scss" scoped>
.bots-settings {
	&__hint {
		margin-bottom: calc(var(--default-grid-baseline) * 4);
		color: var(--color-text-maxcontrast);
	}

	&__item {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;

		&:not(:last-child) {
			margin-bottom: calc(var(--default-grid-baseline) * 4);
		}

		&-info {
			display: flex;
			flex-direction: column;
			max-width: 80%;
		}

		&-name {
			font-size: var(--default-font-size);
			font-weight: bold;
			color: var(--color-main-text);
		}

		&-description {
			font-size: var(--default-font-size);
			color: var(--color-text-maxcontrast);
		}

		&-loader {
			width: var(--default-clickable-area);
			height: var(--default-clickable-area);
			display: flex;
			justify-content: center;
			align-items: center;
			margin-inline-start: auto;
		}

		&-button {
			flex-shrink: 0;
		}
	}
}

</style>
