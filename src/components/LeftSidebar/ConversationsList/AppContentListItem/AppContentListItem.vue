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
	<nav-element
		class="acli_wrapper"
		v-bind="navElement">
		<a
			:id="anchorId"
			ref="acli"
			:class="{ 'active' : isActive }"
			href="#"
			class="acli"
			:aria-label="conversationLinkAriaLabel"
			@mouseover="handleHover"
			@focus="handleFocus"
			@blur="handleBlur"
			@mouseleave="handleMouseleave"
			@keydown.tab="handleTab"
			@click="onClick">
			<!-- default slot for avatar or icon -->
			<slot name="icon" />
			<div class="acli-content">
				<div class="acli-content__main">
					<div class="acli-content__line-one">
						<span class="acli-content__line-one__title">
							{{ title }}
						</span>
						<span
							v-if="{hasDetails}"
							class="acli-content__line-one__details">
							{{ details }}
						</span>
					</div>
					<div class="acli-content__line-two">
						<span class="acli-content__line-two__subtitle">
							<slot name="subtitle" />
						</span>
						<span v-if="!displayActions" class="acli-content__line-two__counter">
							<slot
								name="counter" />
						</span>
					</div>
				</div>
				<div
					v-show="displayActions"
					class="acli-content__actions"
					@click.prevent.stop="">
					<Actions
						ref="actions"
						menu-align="right"
						:aria-label="conversationSettingsAriaLabel">
						<slot
							name="actions" />
					</Actions>
				</div>
			</div>
		</a>
	</nav-element>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'

export default {
	name: 'AppContentListItem',

	components: {
		Actions,
	},

	props: {
		/**
		 * The details text displayed in the upper right part
		 */
		details: {
			type: String,
			default: '',
		},
		/**
		 *  Title
		 */
		title: {
			type: String,
			required: true,
		},
		/**
		* Pass in `true` if you want the matching behaviour to
		* be non-inclusive: https://router.vuejs.org/api/#exact
		*/
		exact: {
			type: Boolean,
			default: false,
		},
		/**
		* The route for the router link.
		*/
		to: {
			type: [String, Object],
			default: '',
		},
		/**
		 * Id for the <a> element
		 */
		anchorId: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			linkIsFocused: false,
			displayActions: false,
		}
	},
	computed: {
		isActive() {
			return this.to && this.$store.getters.getToken() === this.to.params.token
		},

		hasDetails() {
			return (this.details !== '' && !this.$slots.counter)
		},
		hasActions() {
			return (!!this.$slots.actions)
		},
		// This is used to decide which outer element type to use
		// li or router-link
		navElement() {
			if (this.to !== '') {
				return {
					is: 'router-link',
					tag: 'li',
					to: this.to,
					exact: this.exactRoute,
				}
			}
			return {
				is: 'li',
			}
		},

		conversationLinkAriaLabel() {
			return t('spreed', 'Conversation "{conversationName}"', { conversationName: this.title })
		},

		conversationSettingsAriaLabel() {
			return t('spreed', 'Settings for conversation "{conversationName}"', { conversationName: this.title })
		},
	},
	methods: {
		// forward click event
		onClick(event) {
			this.$emit('click', event)
		},

		handleHover() {
			this.showActions()
		},

		handleFocus() {
			this.linkIsFocused = true
			this.showActions()
		},

		handleBlur(e) {
			this.linkIsFocused = false
		},

		showActions() {
			if (this.hasActions) {
				this.displayActions = true
			}
		},

		handleMouseleave() {
			this.displayActions = false
		},

		handleTab(e) {
			if (this.linkIsFocused && this.hasActions) {
				console.debug('preventdefault')
				e.preventDefault()
				this.$refs.actions.$refs.menuButton.focus()
				this.linkIsFocused = false
			} else {
				this.displayActions = false
				this.$refs.actions.$refs.menuButton.blur()
			}

		},
	},
}
</script>

<style lang="scss" scoped>

.acli_wrapper{
	position: relative;
	margin: 0 4px 0 8px;
	.actions {
		position: absolute;
		top: 4px;
		right: 4px;
	}
}

// AppContentListItem
.acli {
	position: relative;
	display: flex;
	align-items: center;
	flex: 0 0 auto;
	justify-content: flex-start;
	padding: 2px 2px 2px 8px;
	height: 64px;
	border-radius: 16px;
	margin: 2px 0;
	cursor: pointer;
	&:hover,
	&:focus  {
		background-color: var(--color-background-hover);
	}
	&.active,
	&:active,
	&:active ~ .app-navigation-entry__utils {
		background-color: var(--color-primary-light);
	}

	&-content {
		display: flex;
		justify-content: space-between;
		width: 210px;
		// same as the acli left padding for
		// nice visual balance around the avatar
		margin-left: 8px;
		&__main {
			flex: 1 1 auto;
			width: calc(100% - 44px);
		}

		&__line-one {
			display: flex;
			align-items: center;
			justify-content: space-between;
			white-space: nowrap;

			&__title {
				overflow: hidden;
				flex-grow: 1;
				cursor: pointer;
				text-overflow: ellipsis;
				color: var(--color-main-text);

			}
		}

		&__line-two {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			white-space: nowrap;

			&__subtitle {
				overflow: hidden;
				flex-grow: 1;
				padding-right: 4px;
				cursor: pointer;
				white-space: nowrap;
				text-overflow: ellipsis;
				color: var(--color-text-lighter);
			}
		}
		&__actions {
			flex: 0 0 auto;
			align-self: center;
			justify-content: center;
		}
	}
}

</style>
