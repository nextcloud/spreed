<!--
  - @copyright Copyright (c) 2022, Marco Ambrosini <marcoambrosini@pm.me>
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
  -
  -->

<template>
	<form class="poll-option">
		<TextField class="poll-option__input" v-bind="$attrs" v-on="$listeners" />
		<ButtonVue v-if="canDelete"
			type="tertiary-no-background"
			@click="deleteOption">
			<Close slot="icon"
				decorative
				title=""
				:size="20" />
		</ButtonVue>
	</form>
</template>

<script>
import TextField from '@nextcloud/vue/dist/Components/TextField'
import ButtonVue from '@nextcloud/vue/dist/Components/ButtonVue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
	name: 'PollOption',

	components: {
		TextField,
		ButtonVue,
		Close,
	},

	props: {

		// This is in order to prevent removing the first option from the array
		canDelete: {
			type: Boolean,
			required: true,
		},
	},

	methods: {
		deleteOption() {
			this.$emit('delete-option')
		},
	},
}
</script>

<style lang="scss" scoped>

.poll-option {
	display: flex;
	width: 100%;
	&__input {
		margin: auto;
	}
}

</style>
