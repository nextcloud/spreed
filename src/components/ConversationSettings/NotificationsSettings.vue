<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div>
		<div class="app-settings-section__hint">
			{{ t('spreed', 'Set the notification level for the current conversation. This will affect only the notifications you receive.') }}
		</div>
		<a
			href="#"
			class="radio-element"
			:class="{'radio-element--active': isNotifyAlways}"
			@click.prevent.exact="setNotificationLevel(1)">
			<VolumeHigh
				decorative
				title=""
				:size="24"
				class="radio-element__icon" />
			<label
				class="radio-element__label">
				{{ t('spreed', 'All messages') }}
			</label>
			<Check
				v-if="isNotifyAlways"
				class="check"
				decorative
				title=""
				:size="20" />
		</a>
		<a
			href="#"
			class="radio-element"
			:class="{'radio-element--active': isNotifyMention}"
			@click.prevent.exact="setNotificationLevel(2)">
			<Account
				decorative
				title=""
				:size="24"
				class="radio-element__icon" />
			<label
				class="radio-element__label">
				{{ t('spreed', '@-mentions only') }}
			</label>
			<Check
				v-if="isNotifyMention"
				class="check"
				decorative
				title=""
				:size="20" />
		</a>
		<a href="#"
			class="radio-element"
			:class="{'radio-element--active': isNotifyNever}"
			@click.prevent.exact="setNotificationLevel(3)">
			<VolumeOff
				decorative
				title=""
				:size="24"
				class="radio-element__icon" />
			<label class="radio-element__label">
				{{ t('spreed', 'Off') }}
			</label>
			<Check
				v-if="isNotifyNever"
				class="check"
				decorative
				title=""
				:size="20" />
		</a>
	</div>
</template>

<script>
import { PARTICIPANT } from '../../constants'
import { setNotificationLevel } from '../../services/conversationsService'
import VolumeHigh from 'vue-material-design-icons/VolumeHigh'
import Account from 'vue-material-design-icons/Account'
import VolumeOff from 'vue-material-design-icons/VolumeOff'
import Check from 'vue-material-design-icons/Check'

export default {
	name: 'NotificationsSettings',

	components: {
		VolumeHigh,
		Account,
		VolumeOff,
		Check,
	},

	props: {
		conversation: {
			type: Object,
			required: true,
		},
	},

	computed: {
		token() {
			return this.conversation.token
		},

		isNotifyAlways() {
			return this.conversation.notificationLevel === PARTICIPANT.NOTIFY.ALWAYS
		},

		isNotifyMention() {
			return this.conversation.notificationLevel === PARTICIPANT.NOTIFY.MENTION
		},

		isNotifyNever() {
			return this.conversation.notificationLevel === PARTICIPANT.NOTIFY.NEVER
		},
	},

	methods: {
		/**
		 * Set the notification level for the conversation
		 * @param {int} notificationLevel The notification level to set.
		 */
		async setNotificationLevel(notificationLevel) {
			const token = this.token
			await setNotificationLevel(token, notificationLevel)
			this.$store.dispatch('changeNotificationLevel', { token, notificationLevel })
		},
	},
}
</script>

<style lang="scss" scoped>
.radio-element {
	display: flex;
	align-items: center;
	height: 44px;
	padding: 0 12px;
	margin: 4px 0;
	border-radius: var(--border-radius-pill);
	&:hover,
	&:focus {
		background-color: var(--color-background-hover);
	}
	&--active{
		background-color: var(--color-primary-light) !important;
	}
	&__icon {
		display: flex;
	}
	&__label {
		margin-left: 12px;
	}
}

.check {
	display: flex;
	margin: 0 8px 0 auto;
}
</style>
