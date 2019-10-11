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

<docs>
This component displays the text inside the message component and can be used for
the main body of the message as well as a quote.
</docs>

<template>
	<div v-show="message"
		:class="{ 'message-main--quote' : isQuote }"
		class="message-main">
		<div v-if="hasAuthor" class="message-main-header">
			<h6>{{ actorDisplayName }}</h6>
		</div>
		<slot />
		<div class="message-main-text">
			<p>{{ message }}</p>
		</div>
	</div>
</template>

<script>
export default {
	inheritAttrs: false,
	props: {
		/**
		 * The sender of the message.
		 */
		actorDisplayName: {
			type: String,
			required: true
		},
		/**
		 * The message or quote text.
		 */
		message: {
			type: String,
			required: true
		},
		/**
		 * if true, it displays the message author on top of the message.
		 */
		hasAuthor: {
			type: Boolean,
			default: true
		},
		/**
		 * Style the message as a quote.
		 */
		isQuote: {
			type: Boolean,
			default: false
		}
	}
}
</script>

<style lang="scss" scoped>
.wrapper {
	width: 100%;
	padding: 4px 0 4px 0;
	&:hover {
		background-color: rgba(47, 47, 47, 0.068);
	}
}

.message {
    &-main {
        display: flex;
		flex-grow: 1;
        flex-direction: column;
		font-size: 20;
		&-header {
			color: var(--color-text-maxcontrast);
		}
		&-text {
			color: var(--color-text-light);
		}
		&--quote {
			border-left: 4px solid var(--color-primary);
			padding: 4px 0 0 8px;
		}
	}
}

</style>
