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
				:aria-label="t('spreed', 'Toggle fullscreen')"
				@shortkey.native="toggleFullscreen"
				@click="toggleFullscreen">
				{{ labelFullscreen }}
			</ActionButton>
		</Actions>
		<!-- Call layout switcher -->
		<Popover v-if="isInCall"
			class="top-bar__button"
			trigger="manual"
			:open="showLayoutHint && !hintDismissed"
			@auto-hide="showLayoutHint=false">
			<Actions slot="trigger">
				<ActionButton v-if="isInCall"
					:icon="changeViewIconClass"
					@click="changeView">
					{{ changeViewText }}
				</actionbutton>
			</Actions>
			<div class="hint">
				{{ layoutHintText }}
				<div class="hint__actions">
					<button
						class="error"
						@click="showLayoutHint=false, hintDismissed=true">
						{{ t('spreed', 'Dismiss') }}
					</button>
					<button
						class="primary"
						@click="changeView">
						{{ t('spreed', 'Use promoted-view') }}
					</button>
				</div>
			</div>
		</Popover>
		<!-- sidebar toggle -->
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
import Popover from '@nextcloud/vue/dist/Components/Popover'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import CallButton from './CallButton'
import { EventBus } from '../../services/EventBus'

export default {
	name: 'TopBar',

	components: {
		ActionButton,
		Actions,
		CallButton,
		Popover,
	},

	props: {
		isInCall: {
			type: Boolean,
			required: true,
		},
		isGrid: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			showLayoutHint: false,
			hintDismissed: false,
		}
	},

	computed: {
		isFullscreen() {
			return this.$store.getters.isFullscreen()
		},

		iconFullscreen() {
			if (this.isInCall) {
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
			if (this.isInCall) {
				return 'forced-white icon-menu-people'
			}
			return 'icon-menu-people'
		},

		showOpenSidebarButton() {
			return !this.$store.getters.getSidebarStatus()
		},

		changeViewText() {
			if (this.isGrid) {
				return t('spreed', 'Switch to promoted view')
			} else {
				return t('spreed', 'Switch to grid view')
			}
		},
		changeViewIconClass() {
			if (this.isGrid) {
				return 'icon-promoted-view-white'
			} else {
				return 'icon-toggle-pictures-white'
			}
		},

		layoutHintText() {
			return t('Spreed', `The videos in this call don't fit in your window: consider maximising it or switching to 'promoted view' for a better experience`)
		},
	},

	mounted() {
		document.addEventListener('fullscreenchange', this.fullScreenChanged, false)
		document.addEventListener('mozfullscreenchange', this.fullScreenChanged, false)
		document.addEventListener('MSFullscreenChange', this.fullScreenChanged, false)
		document.addEventListener('webkitfullscreenchange', this.fullScreenChanged, false)
		// Add call layout hint listener
		EventBus.$on('toggleLayoutHint', (display) => {
			this.showLayoutHint = display
		})
	},

	beforeDestroy() {
		document.removeEventListener('fullscreenchange', this.fullScreenChanged, false)
		document.removeEventListener('mozfullscreenchange', this.fullScreenChanged, false)
		document.removeEventListener('MSFullscreenChange', this.fullScreenChanged, false)
		document.removeEventListener('webkitfullscreenchange', this.fullScreenChanged, false)
		// Remove call layout hint listener
		EventBus.$off('toggleLayoutHint', (display) => {
			this.showLayoutHint = display
		})
	},

	methods: {
		openSidebar() {
			this.$store.dispatch('showSidebar')
		},

		fullScreenChanged() {
			this.$store.dispatch(
				'setIsFullscreen',
				document.webkitIsFullScreen || document.mozFullScreen || document.msFullscreenElement
			)
		},

		toggleFullscreen() {
			if (this.isFullscreen) {
				this.disableFullscreen()
				this.$store.dispatch('setIsFullscreen', false)
			} else {
				this.enableFullscreen()
				this.$store.dispatch('setIsFullscreen', true)
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

		changeView() {
			this.$emit('changeView')
			this.showLayoutHint = false
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

.hint {
	padding: 4px;
	text-align: left;
	&__actions{
		display: flex;
		justify-content: space-between;
		padding-top:4px;
	}
}
</style>
