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
This component is intended to be used both in `NewMessageForm` and `Message`
components.
</docs>

<template>
	<div class="quote">
		<div class="quote__main">
			<div class="quote__main__author">
				<h6>{{ actorDisplayName }}</h6>
			</div>
			<div class="quote__main__text">
				<p>{{ message }}</p>
			</div>
		</div>
		<div v-if="isNewMessageFormQuote" class="quote__main__right">
			<Actions class="quote__main__right__actions">
				<ActionButton
					icon="icon-close"
					:close-after-click="true"
					@click.stop="handleAbortReply" />
			</Actions>
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
		 * The sender of the message to be replied to.
		 */
		actorDisplayName: {
			type: String,
			required: true
		},
		/**
		 * The text of the message to be replied to.
		 */
		message: {
			type: String,
			required: true
		},
		/**
		 * The message id of the message to be replied to.
		 */
		id: {
			type: Number,
			required: true
		},
		/**
		 * The conversation token of the message to be replied to.
		 */
		token: {
			type: String,
			required: true
		},
		/**
		 * If the quote component is used in the `NewMessageForm` component we display
		 * the remove button.
		 */
		isNewMessageFormQuote: {
			type: Boolean,
			default: false
		}
	},
	methods: {
		/**
		 * Stops the quote-reply operation by removing the MessageToBeReplied from
		 * the quoteReplyStore.
		 */
		handleAbortReply() {
			this.$store.dispatch('removeMessageToBeReplied', this.token)
		}
	}
}
</script>

<style lang="scss" scoped>

.quote {
	border-left: 4px solid var(--color-primary);
	margin: 10px 0 10px 0;
	padding: 0 0 0 10px;
	display: flex;
	&__main {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		&__author {
			color: var(--color-text-maxcontrast);
		}
		&__text {
			color: var(--color-text-light);
		}
	}
	&__right {
		flex: 0 0 44px;
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		padding: 0 8px 0 8px;
		position: relative;
		margin: auto;
	}
}
</style>
