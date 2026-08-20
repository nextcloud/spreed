<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { imagePath } from '@nextcloud/router'
import {
	LControlAttribution,
	LIcon,
	LMap,
	LMarker,
	LTileLayer,
} from '@vue-leaflet/vue-leaflet'
import { computed } from 'vue'

// Leaflet icon patch | re-uses images from ~leaflet package
import 'leaflet/dist/leaflet.css'
import 'leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.webpack.css'
import 'leaflet-defaulticon-compatibility'

const props = withDefaults(defineProps<{
	/** The latitude of the location */
	latitude: string
	/** The longitude of the location */
	longitude: string
	/** The name of the location */
	name?: string
	/** The component appearance (take full width) */
	wide?: boolean
}>(), {
	name: '',
})

// {s} subdomains are used by ~leaflet package instead of the policy-preferred 'tile.openstreetmap.org' host
const url = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
// The zoom level of the map in the Talk app (chat, shared items tab)
const previewZoom = 13
// The zoom level of the map in the new OpenStreetMap tab upon opening the link
const linkZoom = 18

// Map preview is non-interactive
// Attribution control from ~leaflet package is replaced with LControlAttribution
const mapOptions = {
	scrollWheelZoom: false,
	zoomControl: false,
	dragging: false,
	attributionControl: false,
}
// Visible attribution is required by the OpenStreetMap tile usage policy (https://operations.osmfoundation.org/policies/tiles/)
const attribution = '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'

// HTTP Referer header is required by the OpenStreetMap tile usage policy (https://operations.osmfoundation.org/policies/tiles/)
const tileLayerOptions = {
	referrerPolicy: 'strict-origin-when-cross-origin',
}

const linkAriaLabel = t('spreed', 'Open this location in OpenStreetMap')
const iconUrl = imagePath('spreed', 'icon-marker-openstreetmap.svg')

const center = computed(() => [Number(props.latitude), Number(props.longitude)])
const mapLink = computed(() => 'https://www.openstreetmap.org/'
	+ `?mlat=${props.latitude}`
	+ `&mlon=${props.longitude}`
	+ `#map=${linkZoom}/${props.latitude}/${props.longitude}`)
</script>

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
			:options="mapOptions"
			@scroll.prevent="">
			<LTileLayer
				:url="url"
				:options="tileLayerOptions" />
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
