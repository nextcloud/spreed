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
		<div class="background background__1">
			<Button text="Button text" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
		</div>
		<div class="background background__1">
			<Button text="Button text" color="primary" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
		</div>
		<div class="background background__2">
			<Button text="Button text" color="success" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
		</div>
		<div class="background background__3">
			<Button text="Button text" color="warning" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
		</div>
		<div class="background background__4">
			<Button text="Button text" color="error" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
		</div>
		<div class="background background__5">
			<Button text="Button text" color="primary" type="contained">
				<MicrophoneOff
					:size="16"
					decorative />
			</Button>
		</div>
		<div class="background background__6">
			<Button text="Button text" color="primary" type="contained">
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

.background {
	display: flex;
	justify-content: center;
	align-items: center;
}

.wrapper {
	margin-top: 50px;
	width: 100%;
	height: 100%;
	display: grid;
	grid-row-gap: 40px;
	grid-column-gap: 28px;
	grid-template-columns: repeat(3, auto);
	grid-template-rows: repeat(5, auto)
}

</style>
