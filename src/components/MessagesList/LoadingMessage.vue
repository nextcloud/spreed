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
	<li class="message-placeholder">
		<div class="icon-container" />
		<div class="placeholder">
			<div class="user" />
			<div class="line" />
			<div class="line" />
			<div class="half-line" />
		</div>
	</li>
</template>

<script>
export default {
	name: 'LoadingMessage',
}
</script>

<style lang="scss" scoped>
	@import '../../assets/variables';

	.message-placeholder {
		line-height: 44px;
		list-style-type: none;
		padding-left: 10px;
		padding-bottom: 5px;

		$icon-size: 44px;
		.icon-container {
			width: $icon-size;
			height: $icon-size;
			margin-left: -2px;
			margin-top: 2px;
			display: inline-block;
			background-color: var(--color-background-dark);
			border-radius: 50%;
			vertical-align: top;
		}

		.placeholder {
			width: 220px;
			height: $icon-size * 2;
			display: inline-block;

			.user,
			.line,
			.half-line {
				background-color: var(--color-background-dark);
				height: ($icon-size / 2) - 6px;
				width: $message-max-width - 60px;
				margin-top: 4px;
			}

			.user {
				width: 175px;
			}

			.half-line {
				width: $message-max-width / 2;
			}
		}
	}
</style>
