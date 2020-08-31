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
	<li class="app-navigation-caption">
		{{ title }}
	</li>
</template>

<script>
export default {
	name: 'Caption',
	props: {
		title: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
	.app-navigation-caption {
		color: var(--color-primary);
		line-height: 44px;
		list-style-type:none;
		white-space: nowrap;
		text-overflow: ellipsis;
		box-shadow: none !important;
		user-select: none;
		pointer-events: none;
		padding-left: 10px;
		font-weight: bold;

		&:not(:first-child) {
			margin-top: 22px;
		}
	}
</style>
