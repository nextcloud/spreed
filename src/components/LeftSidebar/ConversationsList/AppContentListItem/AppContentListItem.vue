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
			:class="{ 'active' : isActive }"
			href="#"
			class="acli"
			:aria-label="conversationLinkAriaLabel"
			@click="onClick">
			<!-- default slot for avatar or icon -->
			<slot name="icon" />
			<div class="acli__content">
				<div class="acli__content__line-one">
					<span class="acli__content__line-one__title">
						{{ title }}
					</span>
					<span
						v-if="{hasDetails}"
						class="acli__content__line-one__details">
						{{ details }}
					</span>
				</div>
				<div class="acli__content__line-two">
					<span class="acli__content__line-two__subtitle">
						<slot name="subtitle" />
					</span>
					<span class="acli__content__line-two__counter">
						<slot
							name="counter" />
					</span>
				</div>
			</div>
		</a>
		<Actions
			v-if="hasActions"
			menu-align="right"
			:aria-label="conversationSettingsAriaLabel"
			class="actions">
			<slot name="actions" />
		</Actions>
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
	},
}
</script>

<style lang="scss" scoped>

.acli_wrapper{
	position: relative;
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
	padding: 10px 2px 10px 8px;
	height: 72px;
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

	&__content {
		width: 240px;
		// same as the acli left padding for
		// nice visual balance around the avatar
		margin-left: 8px;

		&__line-one {
			display: flex;
			align-items: center;
			justify-content: space-between;
			white-space: nowrap;

			&__title {
				overflow: hidden;
				padding-right: 52px;
				flex-grow: 1;
				cursor: pointer;
				text-overflow: ellipsis;
				color: var(--color-main-text);

			}
			&__actions {
				margin: -5px 0 -3px 0;
				&.action-item {
					position: absolute;
					top: 7px;
					right: 2px;
				}
			}
		}

		&__line-two {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			white-space: nowrap;
			width: 240px;

			&__subtitle {
				overflow: hidden;
				flex-grow: 1;
				padding-right: 4px;
				cursor: pointer;
				white-space: nowrap;
				text-overflow: ellipsis;
				color: var(--color-text-lighter);
			}
			&__counter {
				margin-right: 22px;
			}
		}
	}
}

</style>
