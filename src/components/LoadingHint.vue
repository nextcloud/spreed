<!--
  - @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<li class="app-navigation-loading">
		<div class="icon-container" />
		<div class="placeholder">
			<div class="title" />
			<div class="subtitle" />
		</div>
	</li>
</template>

<script>
export default {
	name: 'LoadingHint',
}
</script>

<style lang="scss" scoped>
	.app-navigation-loading {
		line-height: 44px;
		list-style-type: none;
		padding-left: 10px;
		padding-top: 5px;

		$icon-size: 44px;
		.icon-container {
			width: $icon-size;
			height: $icon-size;
			margin-left: -2px;
			margin-top: 2px;
			display: inline-block;
			background-color: var(--color-background-dark);
			border-radius: 50%;
		}

		.placeholder {
			width: 220px;
			height: $icon-size;
			display: inline-block;

			.title {
				background-color: var(--color-background-dark);
				height: ($icon-size / 2) - 2px;
			}

			.subtitle {
				background-color: var(--color-background-dark);
				height: ($icon-size / 2) - 6px;
				width: 180px;
				margin-top: 4px;
			}
		}
	}
</style>
