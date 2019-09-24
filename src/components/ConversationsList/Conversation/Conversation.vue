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
  -
  -->

<!--
<docs>

# Usage

### Simple element

* With a spinning loader instead of the icon:

```
<Conversation title="Loading Item" :loading="true" />
```

### Element with actions
Wrap the children in a template. If you have more than 2 actions, a popover menu and a menu
button will be automatically created.

```
<Conversation title="Item with actions" icon="icon-category-enabled">
	<template slot="actions">
		<ActionButton icon="icon-edit" @click="alert('Edit')">
			Edit
		</ActionButton>
		<ActionButton icon="icon-delete" @click="alert('Delete')">
			Delete
		</ActionButton>
		<ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
	</template>
</Conversation>
```

### Element with counter
Just nest the counter into <Conversation> and add `slot="counter"` to it.

```
<Conversation title="Item with counter" icon="icon-folder">
	<AppNavigationCounter slot="counter">
		99+
	</AppNavigationCounter>
</Conversation>
```

### Element with children

Wrap the children in a template with the `slot` property and use the prop `allowCollapse` to choose wether to allow or
prevent the user from collapsing the items.

```
<Conversation title="Item with children" :allowCollapse="true">
	<template>
		<Conversation title="ConversationChild1" />
		<Conversation title="ConversationChild2" />
		<Conversation title="ConversationChild3"  />
		<Conversation title="ConversationChild4"  />
	</template>
</Conversation>
```
### Editable element
Add the prop `:editable=true` and an edit placeholder if you need it. By devault
the placeholder is the previous title of the element.

```
<Conversation title="Editable Item" :editable="true"
	editPlaceholder="your_placeholder_here" />
```
### Pinned element
Just set the `pinned` prop.
```
<Conversation title="Pinned item" :pinned="true" />
```

</docs>
-->

<template>
	<router-link
		tag="li"
		:to="to"
		:exact="exact"
		:title="title"
		class="conversation"
		@click="onClick">
		<div
			class="wrapper"
			href="#">
			<div
				:class="{ 'icon-loading-small': loading, [icon]: icon && isIconShown }"
				class="conversation__icon">
				<slot
					v-if="!loading"
					v-show="isIconShown"
					name="icon" />
			</div>
			<div class="conversation__body">
				<p class="conversation-body__title">
					{{ title }}
				</p>
				<p class="conversation-body__subtitle">
					{{ subtitle }}
				</p>
			</div>
			<!-- Counter and Actions -->
			<div v-if="hasUtils" class="conversation__utils">
				<Actions menu-align="right">
					<slot name="actions" />
				</Actions>
				<slot name="counter" />
			</div>
		</div>
	</router-link>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import Actions from 'nextcloud-vue/dist/Components/Actions'

export default {
	name: 'Conversation',

	components: {
		Actions
	},
	directives: {
		ClickOutside
	},
	props: {
		/**
		 * The title of the element.
		 */
		title: {
			type: String,
			required: true
		},
		/**
		 * The text underneath the title (Last message)
		 */
		subtitle: {
			type: String,
			required: true
		},
		/**
		* Refers to the icon on the left, this prop accepts a class
		* like 'icon-category-enabled'.
		*/
		icon: {
			type: String,
			default: ''
		},

		/**
		* Displays a loading animated icon on the left of the element
		* instead of the icon.
		*/
		loading: {
			type: Boolean,
			default: false
		},
		/**
		* The route for for the router link.
		*/
		to: {
			type: [String, Object],
			default: ''
		},
		/**
		* Pass in `true` if you want the matching behaviour to
		* be non-inclusive: https://router.vuejs.org/api/#exact
		*/
		exact: {
			type: Boolean,
			default: false
		},
		/**
		* Pins the item to the top left area (TODO).
		*/
		pinned: {
			type: Boolean,
			default: false
		}
	},
	computed: {
		hasUtils() {
			if (this.$slots.actions || this.$slots.counter) {
				return true
			} else {
				return false
			}
		}
	},
	mounted() {
		// prevent click outside event with popupItem.
		this.popupItem = this.$el
	},
	methods: {
		// forward click event
		onClick(event) {
			this.$emit('click', event)
		}
	}
}
</script>

<style lang="scss" scoped>

@import '../../../assets/variables.scss';

.wrapper {
	display: flex;
	flex: 1 1 0;
	box-sizing: border-box;
	min-height: $clickable-area;
	padding: 0;
	padding:10px 7px;
	white-space: nowrap;
	color: var(--color-text-light);
	background-repeat: no-repeat;
	background-position: $icon-margin center;
	background-size: $icon-size $icon-size;
}

.conversation {
	z-index: 100;
	white-space: nowrap;
	&.active,
	a:hover,
	a:focus,
	a:active {
		color: var(--color-main-text);
		box-shadow: inset 4px 0 var(--color-primary);
	}
	&__icon {
		display: flex;
		justify-items: center;
		align-items: center;
		flex: 0 0 $clickable-area;
		justify-content: center;
		width: $clickable-area;
	}
	&__body{
		flex-grow: 1;
		margin: auto;
		width : auto;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
		& span {
			overflow: hidden;
			max-width: 100%;
			white-space: nowrap;
			text-overflow: ellipsis;
		}
	}
	&__utils{
		margin: auto;
		display: flex;
		flex-direction: column;
	}
}

</style>
