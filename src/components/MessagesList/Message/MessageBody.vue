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
	<div v-show="messageText"
		:class="{ 'message-main--quote' : isQuote }"
		class="message-main">
		<div v-if="isFirstMessage || isQuote" class="message-main-header">
			<h6>{{ userName }}</h6>
		</div>
		<slot />
		<div class="message-main-text">
			<p>{{ messageText }}</p>
		</div>
	</div>
</template>

<script>
export default {
	inheritAttrs: false,
	props: {
		userName: {
			type: String,
			required: true
		},
		messageText: {
			type: String,
			required: true
		},
		isFirstMessage: {
			type: Boolean,
			default: false
		}
	},
	computed: {
		isQuote() {
			return !!this.$parent.messageText
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
			color: #989898;
		}
		&-text {
			color: #000000;
		}
		&--quote {
			border-left: 4px solid #0083C9;
			padding: 4px 0 0 8px;
		}
	}
}

</style>
