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
	<div
		:style="{'background-color': backgroundColor }"
		class="video-background">
		<h3>{{ displayName[0] }}</h3>
		<p>{{ displayName }}</p>
	</div>
</template>

<script>
export default {
	name: 'VideoBackground',

	props: {
		displayName: {
			type: String,
			default: '?',
		},
	},

	computed: {
		backgroundColor() {
			// If the prop is empty. We're not checking for the default value
			// because the user's displayName might be '?'
			if (!this.$options.propsData.displayName) {
				return `var(--color-text-maxcontrast)`
			} else {
				const color = this.displayName.toRgb()
				return `rgba(${color.r}, ${color.g}, ${color.b}, 1)`
			}

		},
	},
	methods: {
	},
}
</script>

<style lang="scss" scoped>
.video-background {
	position: absolute;
	left: 0;
	top: 0;
	height: 100%;
	width: 100%;

	h3 {
		color: white;
		position: absolute;
		top: 40%;
		left: 50%;
		font-size: 50px;
		font-weight: 500;
		text-transform: uppercase;
	}
	p {
		color: white;
		position: absolute;
		left: 24px;
		bottom: 24px;
		font-size: 20px;
	}
}
</style>
