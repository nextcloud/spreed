<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul v-if="value">
		<NcAppNavigationCaption :name="t('spreed', 'Phone numbers')" />
		<Hint v-if="errorHint" :hint="errorHint" />
		<template v-if="libPhoneNumber">
			<NcListItem :name="name" @click="selectPhoneNumber">
				<template #icon>
					<Phone :size="AVATAR.SIZE.DEFAULT" />
				</template>
				<template #subname>
					{{ participantPhoneItem.phoneNumber }}
				</template>
			</NcListItem>
		</template>
	</ul>
</template>

<script>
import { parsePhoneNumberFromString, validatePhoneNumberLength } from 'libphonenumber-js'

import Phone from 'vue-material-design-icons/Phone.vue'

import { t } from '@nextcloud/l10n'

import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

import Hint from './UIShared/Hint.vue'

import { ATTENDEE, AVATAR } from '../constants.js'

export default {
	name: 'SelectPhoneNumber',

	components: {
		Hint,
		NcAppNavigationCaption,
		NcListItem,
		Phone,
	},

	props: {
		name: {
			type: String,
			required: true,
		},

		value: {
			type: String,
			required: true,
		},

		participantPhoneItem: {
			type: Object,
			required: true,
		},
	},

	emits: ['select', 'update:participantPhoneItem'],

	setup() {
		return {
			AVATAR,
		}
	},

	computed: {
		/**
		 *
		 * @return {import('libphonenumber-js').PhoneNumber|undefined}
		 */
		libPhoneNumber() {
			return this.value
				? parsePhoneNumberFromString(this.value)
				: undefined
		},

		errorHint() {
			switch (validatePhoneNumberLength(this.value)) {
			case 'INVALID_LENGTH': return t('spreed', 'Number length is not valid')
			case 'INVALID_COUNTRY': return t('spreed', 'Region code is not valid')
			case 'TOO_SHORT': return t('spreed', 'Number length is too short')
			case 'TOO_LONG': return t('spreed', 'Number length is too long')
			case 'NOT_A_NUMBER': return t('spreed', 'Number is not valid')
			default: return ''
			}
		},
	},

	watch: {
		libPhoneNumber(value) {
			if (!value) {
				this.$emit('update:participantPhoneItem', {})
				return
			}

			const phoneNumber = value?.format('E.164')
			this.$emit('update:participantPhoneItem', {
				id: `PHONE(${phoneNumber})`,
				source: ATTENDEE.ACTOR_TYPE.PHONES,
				label: phoneNumber,
				phoneNumber,
			})
		}
	},

	methods: {
		t,
		selectPhoneNumber() {
			this.$emit('select', this.participantPhoneItem)
		}
	},
}
</script>
