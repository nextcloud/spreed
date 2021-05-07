<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@pm.me>
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
	<a :href="mapLink"
		target="_blank"
		rel="noopener noreferrer"
		class="location"
		:aria-label="linkAriaLabel">
		<LMap
			style="height: 200px"
			:zoom="zoom"
			:center="center"
			:options="{
				scrollWheelZoom: false,
				zoomControl: false,
				dragging: false,
				attributionControl: false,
			}"
			@scroll.prevent="">
			<LTileLayer :url="url" />
			<LMarker :lat-lng="center" s />
		</LMap>
	</a>
</template>

<script>
import { LMap, LTileLayer, LMarker } from 'vue2-leaflet'

export default {
	name: 'Location',

	components: {
		LMap,
		LTileLayer,
		LMarker,
	},

	props: {
		/**
		 * The latitude of the location
		 */
		latitude: {
			type: String,
			required: true,
		},

		/**
		 * The longitude of the location
		 */
		longitude: {
			type: String,
			required: true,
		},

		/**
		 * The name of the location
		 */
		name: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			zoom: 13,
		}
	},

	computed: {
		center() {
			return [this.latitude, this.longitude]
		},

		mapLink() {
			return `https://www.openstreetmap.org/?mlat=${this.latitude}&mlon=${this.longitude}#map=15/${this.latitude}/${this.longitude}`
		},
	},
}
</script>

<style lang="scss" scoped>
.location {
	overflow: hidden;
	border-radius: var(--border-radius-large);
	position: relative;
	display: block;
	z-index: 1;
	height: 200px;
	width: 350px;
}
</style>
