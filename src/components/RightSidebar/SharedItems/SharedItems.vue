<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="shared-items">
		<button class="shared-items__caption" @click="handleCaptionClick">
			{{ title }}
		</button>
		<slot />
	</div>
</template>

<script>

export default {
	name: 'SharedItems',

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	methods: {
		handleCaptionClick() {
			console.debug('Show more')
		},
	},
}
</script>

<style lang="scss" scoped>
.shared-items {
	&__caption{
		text-align: left;
		font-size: 16px;
		background: none;
		border: none;
		width: 100%;
		height: 44px;
		padding: 0;
		margin: 0 0 0 0 !important;
		font-weight: bold;
	}
}
</style>
