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
	<div class="top-bar">
		<CallButton />
		<Actions class="top-bar__button">
			<ActionButton
				v-shortkey="['f']"
				:icon="iconFullscreen"
				@shortkey.native="toggleFullscreen"
				@click="toggleFullscreen">
				{{ labelFullscreen }}
			</ActionButton>
		</Actions>
		<Actions v-if="showOpenSidebarButton"
			class="top-bar__button"
			close-after-click="true">
			<ActionButton
				:icon="iconMenuPeople"
				@click="openSidebar" />
		</Actions>
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import CallButton from './CallButton'

export default {
	name: 'TopBar',

	components: {
		ActionButton,
		Actions,
		CallButton,
	},

	props: {
		forceWhiteIcons: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			isFullscreen: false,
		}
	},

	computed: {
		iconFullscreen() {
			if (this.forceWhiteIcons) {
				return 'forced-white icon-fullscreen'
			}
			return 'icon-fullscreen'
		},

		labelFullscreen() {
			if (this.isFullscreen) {
				return t('spreed', 'Exit fullscreen (f)')
			}
			return t('spreed', 'Fullscreen (f)')
		},

		iconMenuPeople() {
			if (this.forceWhiteIcons) {
				return 'forced-white icon-menu-people'
			}
			return 'icon-menu-people'
		},

		showOpenSidebarButton() {
			return !this.$store.getters.getSidebarStatus()
		},
	},

	methods: {
		openSidebar() {
			this.$store.dispatch('showSidebar')
		},
		toggleFullscreen() {
			if (this.isFullscreen) {
				this.disableFullscreen()
				this.isFullscreen = false
			} else {
				this.enableFullscreen()
				this.isFullscreen = true
			}
		},

		enableFullscreen() {
			const fullscreenElem = document.getElementById('content')

			if (fullscreenElem.requestFullscreen) {
				fullscreenElem.requestFullscreen()
			} else if (fullscreenElem.webkitRequestFullscreen) {
				fullscreenElem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT)
			} else if (fullscreenElem.mozRequestFullScreen) {
				fullscreenElem.mozRequestFullScreen()
			} else if (fullscreenElem.msRequestFullscreen) {
				fullscreenElem.msRequestFullscreen()
			}
		},

		disableFullscreen() {
			if (document.exitFullscreen) {
				document.exitFullscreen()
			} else if (document.webkitExitFullscreen) {
				document.webkitExitFullscreen()
			} else if (document.mozCancelFullScreen) {
				document.mozCancelFullScreen()
			} else if (document.msExitFullscreen) {
				document.msExitFullscreen()
			}
		},
	},
}
</script>

<style lang="scss" scoped>

@import '../../assets/variables';

.top-bar {
	height: $top-bar-height;
	position: absolute;
	top: 0;
	right: 0;
	display: flex;
	z-index: 10;
	justify-content: flex-end;
	padding: 0 6px;
	&__button {
		align-self: center;
	}

}
</style>
