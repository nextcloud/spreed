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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<ul v-if="value">
		<NcAppNavigationCaption :name="t('spreed', 'Phone numbers')" />
		<Hint v-if="errorHint" :hint="errorHint" />
		<template v-if="libPhoneNumber">
			<NcListItem class="new-phone"
				:name="name"
				compact
				@click="selectPhoneNumber">
				<template #icon>
					<Phone :size="30" />
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

import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

import Hint from './Hint.vue'

import { ATTENDEE } from '../constants.js'

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
		selectPhoneNumber() {
			this.$emit('select', this.participantPhoneItem)
		}
	},
}
</script>

<style lang="scss" scoped>
.new-phone {
	margin: 4px 0;
	padding: 0 4px;
	height: 56px;
	border-radius: var(--border-radius-pill);

	:deep(.list-item-content) {
		padding-left: 20px;
	}
}
</style>
