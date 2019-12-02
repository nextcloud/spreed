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
		<input 
			type="text"
			class="conversation-name"
			v-model="conversationName"
			:placeholder="t('spreed', 'Give this group conversation a name')"
			autofocus/>
		<p
			v-if="badInput"
			class="alert">
				{{t('spreed', 'bad input')}}
			</p>
		<button
			class="submit"
			@click="handleClick" />
	</div>
</template>

<script>

export default {
	name: 'SetConversationName',
	data() {
		return { 
			conversationName: ''
		}
	},

	methods: {
		handleClick() {
			if (this.conversationName !== '') {
				this.$emit('setConversationName', )
			} else {
				
			}
		}
	},

	computed: {
		badInput() {
			return false
		}
	}
}
</script>

<style>
</style>
