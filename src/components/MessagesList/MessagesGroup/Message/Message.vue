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
	<div
		class="message"
		:class="{ 'hover': hover }"
		@mouseover="hover=true"
		@mouseleave="hover=false">
		<div class="message__author">
			<h6>{{ actorDisplayName }}</h6>
		</div>
		<div class="message__main">
			<div class="message__main__text">
				<p>{{ message }}</p>
			</div>
			<div v-show="isTemporary" class="message__main__right icon-loading-small" />
			<div v-show="!isTemporary" class="message__main__right">
				<h6>{{ messageTime }}</h6>
				<Actions v-show="hover" class="actions">
					<ActionButton icon="icon-delete" @click="handleDelete">
						Delete
					</ActionButton>
					<ActionButton icon="icon-delete" @click="handleDelete">
						Delete
					</ActionButton>
				</Actions>
			</div>
		</div>
	</div>
</template>

<script>
import Actions from 'nextcloud-vue/dist/Components/Actions'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'

export default {
	name: 'Message',
	components: {
		Actions,
		ActionButton
	},
	data: function() {
		return {
			hover: false
		}
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
		 * The message timestamp.
		 */
		timestamp: {
			type: Number,
			default: 0
		},
		/**
		 * if true, it displays the message author on top of the message.
		 */
		showAuthor: {
			type: Boolean,
			default: true
		},
		/**
		 * Style the message as a quote.
		 */
		isQuote: {
			type: Boolean,
			default: false
		},
		isTemporary: {
			type: Boolean,
			required: true
		}
	},
	computed: {
		messageTime() {
			return OC.Util.formatDate(this.timestamp * 1000, 'LT')
		}
	},
	methods: {
		handleDelete() {
			this.$store.dispatch('deleteMessage', this.message)
		}
	}
}
</script>

<style lang="scss" scoped>
@import '../../../../assets/variables';

.message {
	min-width: 100%;
	padding: 4px 0 4px 0;
	flex-direction: column;
	&:hover {
		background-color: rgba(47, 47, 47, 0.068);
	}
	&__author {
		color: var(--color-text-maxcontrast);
	}
	&__main {
		display: flex;
		justify-content: space-between;
		min-width: 100%;
		&__text {
			width: 400px;
			color: var(--color-text-light);
		}
		&__right {
			width: 80px;
		}
	}
}

.message {
	&-main {
		display: inline-flex;
		flex-grow: 1;
		flex-direction: column;

		&--quote {
			border-left: 4px solid var(--color-primary);
			padding: 4px 0 0 8px;
		}
	}
}

.right {
		display: flex;
		min-width: 110px;
		color: #989898;
		padding: 0 8px 0 8px;
	}

	.actions {
		position: absolute;
		margin: -14px 0 0 50px;
		padding:2px;
	}
</style>
