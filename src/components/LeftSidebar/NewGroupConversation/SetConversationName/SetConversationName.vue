<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="set-conversation-name">
		<input ref="conversationName"
			v-observe-visibility="visibilityChanged"
			type="text"
			:value="value"
			class="conversation-name"
			:placeholder="t('spreed', 'Conversation name')"
			@input="handleInput"
			@keydown.enter="handleKeydown">
	</div>
</template>

<script>

export default {
	name: 'SetConversationName',
	props: {
		value: {
			type: String,
			required: true,
		},
	},

	methods: {
		handleInput(event) {
			this.$emit('input', event.target.value)
		},
		visibilityChanged(isVisible) {
			if (isVisible) {
				// Focus the input field of the current component.
				this.$refs.conversationName.focus()
			}
		},
		// Forward the keydown event to the parent
		handleKeydown() {
			this.$emit('click-enter')
		},
	},

}

</script>

<style lang="scss" scoped>

.conversation-name {
	width: 100%;
	font-size: 16px;
	line-height: 34px;

}
</style>
