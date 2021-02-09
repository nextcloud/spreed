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
	<div class="wrapper">
		<p class="section-title">
			Icon only
		</p>
		<div class="section">
			<p class="button-type">
				Text button
			</p>
			<p class="button-type">
				Outlined button
			</p>
			<p class="button-type">
				Contained button
			</p>
			<Button type="text">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
			<Button type="outlined">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
			<Button type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
			<Button color="primary" type="text">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
			<div />
			<Button color="primary" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
		</div>
		<p class="section-title">
			Text only
		</p>
		<div class="section">
			<p class="button-type">
				Text button
			</p>
			<p class="button-type">
				Outlined button
			</p>
			<p class="button-type">
				Contained button
			</p>
			<Button text="Microphone off" type="text" />
			<Button text="Microphone off" type="outlined" />
			<Button text="Microphone off" type="contained" />
			<Button color="primary" text="Microphone off" type="text" />
			<div />
			<Button color="primary" text="Microphone off" type="contained" />
		</div>
		<p class="section-title">
			Icon and text
		</p>
		<div class="section">
			<p class="button-type">
				Text button
			</p>
			<p class="button-type">
				Outlined button
			</p>
			<p class="button-type">
				Contained button
			</p>
			<Button text="Microphone off" type="text">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
			<Button text="Microphone off" type="outlined">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
			<Button text="Microphone off" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
			<Button text="Microphone off" color="primary" type="text">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
			<div />
			<Button text="Microphone off" color="primary" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
		</div>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'

import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff'

export default {
	name: 'App',

	components: {
		Button,
		MicrophoneOff,
	},
}
</script>

<style lang="scss" scoped>
.section-title {
	margin-top: 80px;
	font-size: 20px;
	font-weight: bold;
}

.button-type {
	font-style: italic;
	color: var(--color-text-lighter);
}

.wrapper {
	width: 600px;
	margin: 80px auto 0 auto;
}

.section {
	width: 100%;
	display: grid;
	justify-items: left;
	grid-row-gap: 40px;
	grid-column-gap: 28px;
	grid-template-columns: repeat(3, auto);
	grid-template-rows: repeat(2, auto)
}
</style>
