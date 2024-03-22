<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<li class="app-navigation-hint">
		{{ hint }}
	</li>
</template>

<script>
export default {
	name: 'Hint',
	props: {
		hint: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
	.app-navigation-hint {
		color: var(--color-text-maxcontrast);
		line-height: 44px;
		list-style-type:none;
		white-space: nowrap;
		text-overflow: ellipsis;
		box-shadow: none !important;
		user-select: none;
		pointer-events: none;
		padding-left: 10px;
	}
</style>
