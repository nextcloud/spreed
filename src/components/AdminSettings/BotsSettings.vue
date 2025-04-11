<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="bots_settings" class="bots-settings section">
		<h2>{{ t('spreed', 'Bots settings') }}</h2>

		<!-- eslint-disable-next-line vue/no-v-html -->
		<p class="settings-hint" v-html="botsSettingsDescription" />

		<ul v-if="bots.length" class="bots-settings__list">
			<li class="bots-settings__item bots-settings__item--head">
				<div class="state">
					{{ t('spreed', 'State') }}
				</div>
				<div class="name">
					{{ t('spreed', 'Name') }}
				</div>
				<div class="description">
					{{ t('spreed', 'Description') }}
				</div>
				<div class="last-error">
					{{ t('spreed', 'Last error') }}
				</div>
				<div class="error-count">
					{{ t('spreed', 'Total errors count') }}
				</div>
			</li>

			<li v-for="bot in botsExtended"
				:key="bot.id"
				class="bots-settings__item">
				<div class="state">
					<span class="state__icon"
						:aria-label="bot.state_icon_label"
						:title="bot.state_icon_label">
						<component :is="bot.state_icon_component"
							:fill-color="bot.state_icon_color" />
					</span>
				</div>
				<div class="name bold">
					{{ bot.name }}
				</div>
				<div class="description">
					{{ bot.description }}
				</div>
				<div :id="`last_error_bot_${bot.id}`" class="last-error">
					<NcPopover v-if="bot.last_error_message"
						container="#bots_settings"
						:focus-trap="false">
						<template #trigger>
							<NcButton type="error" :aria-label="bot.last_error_message">
								{{ bot.last_error_date }}
							</NcButton>
						</template>
						<div class="last-error__popover-content">
							{{ bot.last_error_message }}
						</div>
					</NcPopover>
				</div>
				<div class="error-count">
					<span v-if="bot.error_count">
						{{ bot.error_count }}
					</span>
				</div>
			</li>
		</ul>

		<NcButton type="primary"
			href="https://nextcloud-talk.readthedocs.io/en/latest/bot-list/"
			target="_blank"
			rel="noreferrer nofollow">
			{{ t('spreed', 'Find more bots') }} ↗
		</NcButton>
	</section>
</template>

<script>
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Lock from 'vue-material-design-icons/Lock.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import { BOT } from '../../constants.ts'
import { getAllBots } from '../../services/botsService.ts'
import { formatDateTime } from '../../utils/formattedTime.ts'

export default {
	name: 'BotsSettings',

	components: {
		NcPopover,
		NcButton,
	},

	data() {
		return {
			loading: true,
			bots: [],
		}
	},

	computed: {
		botsSettingsDescription() {
			let description = t('spreed', 'The following bots are installed on this server. In the documentation you can find details how to {linkstart1}build your own bot{linkend} or a {linkstart2}list of bots{linkend} to enable on your server.')
			if (!this.bots.length) {
				description = t('spreed', 'No bots are installed on this server. In the documentation you can find details how to {linkstart1}build your own bot{linkend} or a {linkstart2}list of bots{linkend} to enable on your server.')
			}

			return description
				.replace('{linkstart1}', '<a target="_blank" rel="noreferrer nofollow" class="external" href="https://nextcloud-talk.readthedocs.io/en/latest/bots/">')
				.replace('{linkstart2}', '<a target="_blank" rel="noreferrer nofollow" class="external" href="https://nextcloud-talk.readthedocs.io/en/latest/bot-list/">')
				.replaceAll('{linkend}', ' ↗</a>')
		},

		botsExtended() {
			return this.bots.map(bot => ({
				...bot,
				...this.getStateIcon(bot.state),
				description: bot.description ?? t('spreed', 'Description is not provided'),
				last_error_date: bot.last_error_date ? formatDateTime(bot.last_error_date * 1000, 'll LTS') : '---',
			}))
		},
	},

	async mounted() {
		this.loading = true
		try {
			const response = await getAllBots()
			this.bots = response.data.ocs.data
		} catch (error) {
			console.error(error)
		}
		this.loading = false
	},

	methods: {
		t,
		getStateIcon(state) {
			switch (state) {
			case BOT.STATE.NO_SETUP:
				return { state_icon_component: Lock, state_icon_label: t('spreed', 'Locked for moderators'), state_icon_color: 'var(--color-warning)' }
			case BOT.STATE.ENABLED:
				return { state_icon_component: Check, state_icon_label: t('spreed', 'Enabled'), state_icon_color: 'var(--color-success)' }
			case BOT.STATE.DISABLED:
			default:
				return { state_icon_component: Cancel, state_icon_label: t('spreed', 'Disabled'), state_icon_color: 'var(--color-error)' }
			}
		},
	},
}
</script>

<style scoped lang="scss">

.bots-settings {
	&__item {
		display: grid;
		grid-template-columns: minmax(50px, 100px) 1fr 2fr minmax(100px, 250px) minmax(50px, 100px);
		grid-column-gap: 5px;

		&:not(:last-child) {
			margin-bottom: 10px;
		}

		&--head {
			padding-bottom: 5px;
			border-bottom: 1px solid var(--color-border);
			font-weight: bold;
		}

		.bold {
			font-weight: bold;
		}

		.last-error__popover-content {
			margin: calc(var(--default-grid-baseline) * 2);
		}
	}

	&__list {
		margin-bottom: 30px;
	}
}

</style>
