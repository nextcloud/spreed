<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<a
		:href="mapLink"
		target="_blank"
		rel="noopener noreferrer"
		class="location"
		:class="{ wide: wide }"
		:aria-label="linkAriaLabel"
		:title="name">
		<LMap
			:zoom="previewZoom"
			:center="center"
			:options="{
				scrollWheelZoom: false,
				zoomControl: false,
				dragging: false,
				attributionControl: false,
			}"
			@scroll.prevent="">
			<LTileLayer :url="url" />
			<LControlAttribution
				position="bottomright"
				:prefix="attribution" />
			<LMarker :latLng="center">
				<LIcon
					:iconSize="[26, 40]"
					:iconAnchor="[13, 40]"
					:iconUrl />
			</LMarker>
		</LMap>
	</a>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { imagePath } from '@nextcloud/router'
import {
	LControlAttribution,
	LIcon,
	LMap,
	LMarker,
	LTileLayer,
} from '@vue-leaflet/vue-leaflet'

export default {
	name: 'LocationCard',

	components: {
		LControlAttribution,
		LIcon,
		LTileLayer,
		LMap,
		LMarker,
	},

	props: {
		/**
		 * The latitude of the location
		 */
		latitude: {
			type: Number,
			required: true,
		},

		/**
		 * The longitude of the location
		 */
		longitude: {
			type: Number,
			required: true,
		},

		/**
		 * The name of the location
		 */
		name: {
			type: String,
			default: '',
		},

		wide: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			// The zoom level of the map in the messages list
			previewZoom: 13,
			// The zoom level of the map in the new openstreetmap tab upon
			// Opening the link
			linkZoom: 18,

			attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
		}
	},

	computed: {
		center() {
			return [this.latitude, this.longitude]
		},

		mapLink() {
			return `https://www.openstreetmap.org/?mlat=${this.latitude}&mlon=${this.longitude}#map=${this.linkZoom}/${this.latitude}/${this.longitude}`
		},

		linkAriaLabel() {
			return t('spreed', 'Open this location in OpenStreetMap')
		},

		iconUrl() {
			return imagePath('spreed', 'icon-marker-openstreetmap.svg')
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
.location {
	display: flex;
	flex-direction: column;
	position: relative;
	z-index: 1;
	white-space: initial;
	overflow: hidden;
	border-radius: var(--border-radius-large);
	height: min(200px, 30vh);
	min-width: 200px;
	margin: 4px;
	transition: outline 0.1s ease-in-out;

	&:hover,
	&:focus,
	&:focus-visible {
		outline: 2px solid var(--color-primary-element);
	}

	&.wide {
		width: 100%;
		height: 100%;
		margin: 0;
	}
}
</style>
