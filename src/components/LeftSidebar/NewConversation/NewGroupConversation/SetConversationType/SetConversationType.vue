<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
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
		<span>
			<input 
				type="checkbox"
				class="conversation-type"
				@input="handleInput" />
			{{t('spreed', 'make ')}}<strong>{{conversationDisplayName}}</strong>{{t('spreed', ' public')}}
		</span>
		<p v-if="value">{{t('spreed', 'You will get a link to share this conversation.')}}</p>
	</div>
</template>

<script>

export default {
	name: 'SetConversationType',
	props: {
		value: {
			type: Boolean,
			required: true,
		},
		conversationName: {
			type: String,
			default: t('spreed', 'your conversation'),
		}
	},
	computed: {
		conversationDisplayName() {
			if (this.conversationName === '') {
				return t('spreed', 'your conversation')
			} else {
				return this.conversationName
			}
		},
	},
	methods: {
		handleInput(event) {
			this.$emit('input', event.target.checked)
		}
	}

}

</script>

<style lang="scss" scoped>
</style>
