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
	<div class="message">
		<div class="message__author">
			<h6>{{ actorDisplayName }}</h6>
		</div>
		<div class="message__main">
			<div class="message__main__text">
				<p>{{ message }}</p>
			</div>
			<div class="message__main__right">
				<Actions v-if="isNewMessageFormQuote" class="message__main__right__actions">
					<ActionButton
						icon="icon-delete"
						:close-after-click="true"
						@click.stop="handleAbortReply" />
				</Actions>
			</div>
		</div>
	</div>
</template>

<script>
import Actions from 'nextcloud-vue/dist/Components/Actions'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'

export default {
	name: 'Quote',
	components: {
		Actions,
		ActionButton
	},
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
		 * The message id.
		 */
		id: {
			type: Number,
			required: true
		},
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true
		},
		isNewMessageFormQuote: {
			type: Boolean,
			default: false
		}
	},
	methods: {
		handleAbortReply() {
			this.$store.dispatch('removeMessageToBeReplied', this.token)
		}
	}
}
</script>

<style lang="scss" scoped>

.message {
	border-left: 4px solid var(--color-primary);
	margin: 4px 0 4px 0;
	padding: 0 0 0 10px;
	flex-direction: column;
	&__author {
		color: var(--color-text-maxcontrast);
	}
	&__main {
		display: flex;
		justify-content: space-between;
		min-width: 100%;
		&__text {
			flex: 1 1 400px;
			color: var(--color-text-light);
			&--quote {
			border-left: 4px solid var(--color-primary);
			padding: 4px 0 0 8px;
			}
		}
		&__right {
			justify-self: flex-start;
			position: relative;
			flex: 0 0 110px;
			display: flex;
			color: var(--color-text-maxcontrast);
			font-size: 13px;
			padding: 0 8px 0 8px;
			&__actions {
				position: absolute;
				top: -12px;
				right: 0;
			}
		}
	}
}
</style>
