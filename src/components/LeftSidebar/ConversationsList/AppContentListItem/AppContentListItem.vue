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
		:class="{ 'active' : isActive }"
		v-bind="navElement"
		href="#"
		class="acli"
		@click="onClick">
		<!-- default slot for avatar or icon -->
		<slot name="icon" />
		<div class="acli__content">
			<div class="acli__content__line-one">
				<span class="acli__content__line-one__title">
					{{ title }}
				</span>
				<span>
					<Actions
						v-if="hasActions"
						menu-align="right"
						class="acli__content__line-one__actions">
						<slot name="actions" />
					</Actions>
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
	},
	computed: {
		isActive() {
			return this.to && this.$route.params.token === this.to.params.token
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

// AppContentListItem
.acli {
	position: relative;
	padding: 10px 2px 10px 8px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex: 0 0 auto;
	cursor: pointer;
	&__content {
		width: 240px;
		margin-left: 6px;
		&__line-one {
			display: flex;
			justify-content: space-between;
			align-items: center;
			white-space: nowrap;
			&__title {
				flex-grow: 1;
				overflow: hidden;
				text-overflow: ellipsis;
				color: var(--color-main-text);
				padding-right: 4px;
				cursor: pointer;
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
			justify-content: space-between;
			align-items: flex-start;
			white-space: nowrap;
			&__subtitle {
				flex-grow: 1;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
				color: var(--color-text-lighter);
				padding-right: 4px;
				cursor: pointer;
			}
			&__counter {
				margin-right: 12px;
			}
		}
	}
}

.active {
	background-color: var(--color-primary-light);
	box-shadow: inset 4px 0 var(--color-primary)
}

</style>
