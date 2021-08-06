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
		<!-- Wide -->
		<div class="button-wrapper">
			<Button
				aria-label="asdasdasdasd">
				<Video
					title=""
					:size="16" />
			</Button>
		</div>
		<div class="button-wrapper">
			<Button
				:disabled="true"
				tooltip-text="test tooltip"
				aria-label="asdasdasdasd"
				text="Example text"
				type="primary">
				<Video
					:size="20" />
			</Button>
		</div>
		<div class="button-wrapper">
			<Button
				tooltip-text="test tooltip"
				aria-label="asdasdasdasd"
				text="Example text"
				type="success">
				<Video />
			</Button>
		</div>
		<div class="button-wrapper">
			<Button
				tooltip-text="test tooltip"
				aria-label="asdasdasdasd"
				text="Example text"
				type="warning" />
		</div>
		<div class="button-wrapper">
			<Button
				tooltip-text="test tooltip"
				aria-label="asdasdasdasd"
				text="Example text"
				type="no-background" />
		</div>
		<div class="button-wrapper">
			<Button
				tooltip-text="test tooltip"
				aria-label="asdasdasdasd"
				text="Example text"
				type="error" />
		</div>
		<!-- non wide -->
		<div class="button-wrapper">
			<Button
				tooltip-text="test tooltip"
				aria-label="asdasdasdasd"
				text="Example text"
				:wide="false">
				<Video
					:size="16" />
			</Button>
		</div>
		<div class="button-wrapper">
			<Button
				tooltip-text="test tooltip"
				aria-label="asdasdasdasd"
				text="Example text"
				type="primary"
				:wide="false">
				<Video
					:size="16" />
			</Button>
		</div>
		<div class="button-wrapper">
			<Button
				aria-label="asdasdasdasd"
				text="Example text"
				type="success"
				:wide="false">
				<Video
					:size="16" />
			</Button>
		</div>
		<div class="button-wrapper">
			<Button
				aria-label="asdasdasdasd"
				text="Example text"
				type="warning"
				:wide="false" />
		</div>
		<div class="button-wrapper">
			<Button
				aria-label="asdasdasdasd"
				text="Example text"
				type="error"
				:wide="false" />
		</div>
		<div class="button-wrapper">
			<Button
				aria-label="asdasdasdasd">
				<Video
					:size="16" />
			</Button>
		</div>
		<div class="button-wrapper">
			<Button
				aria-label="asdasdasdasd"
				type="primary">
				<Video />
			</Button>
		</div>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import Video from 'vue-material-design-icons/Video'

export default {
	name: 'App',

	components: {
		Button,
		Video,
	},
}
</script>

<style lang="scss">

.wrapper {
	margin: 80px auto;
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	grid-template-rows: repeat(auto-fill, 80px);
	height: 100%;
	position: relative;
	padding: 0 80px;
	overflow: auto;
}

.button-wrapper {
	margin: 20px;
}
</style>
