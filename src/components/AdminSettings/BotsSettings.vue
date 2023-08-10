<!--
 - @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 -
 - @author Maksim Sukharev <antreesy.web@gmail.com>
 -
 - @license AGPL-3.0-or-later
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
	<section id="bots_settings" class="bots-settings section">
		<h2>{{ t('spreed', 'Bots settings') }}</h2>

		<p class="settings-hint">
			{{ botsSettingsDescription }}
		</p>

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
						:container="`#last_error_bot_${bot.id}`"
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
	</section>
</template>

<script>
import Cancel from 'vue-material-design-icons/Cancel.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Lock from 'vue-material-design-icons/Lock.vue'

import moment from '@nextcloud/moment'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

import { BOT } from '../../constants.js'
import { getAllBots } from '../../services/botsService.js'

export default {
	name: 'BotsSettings',

	components: {
		NcPopover,
		NcButton,
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			loading: true,
			bots: [],
		}
	},

	computed: {
		botsSettingsDescription() {
			return this.bots.length
				? t('spreed', 'The following bots can be enabled. Reach out to your administration to get more bots installed on this server.')
				: t('spreed', 'No bots are installed on this server. Reach out to your administration to get bots installed on this server.')
		},

		botsExtended() {
			return this.bots.map(bot => ({
				...bot,
				...this.getStateIcon(bot.state),
				description: bot.description ?? t('spreed', 'Description is not provided'),
				last_error_date: bot.last_error_date ? moment(bot.last_error_date * 1000).format('ll LTS') : '---',
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
		getStateIcon(state) {
			switch (state) {
			case BOT.STATE.NO_SETUP:
				return { state_icon_component: Lock, state_icon_label: t('spreed', 'Locked for moderators'), state_icon_color: 'var(--color-placeholder-dark)' }
			case BOT.STATE.ENABLED:
				return { state_icon_component: Check, state_icon_label: t('spreed', 'Enabled'), state_icon_color: 'var(--color-success)' }
			case BOT.STATE.DISABLED:
			default:
				return { state_icon_component: Cancel, state_icon_label: t('spreed', 'Disabled'), state_icon_color: 'var(--color-placeholder-dark)' }
			}
		},
	},
}
</script>

<style scoped lang="scss">
h3 {
	margin-top: 24px;
	font-weight: 600;
}

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
}

</style>
