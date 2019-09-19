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
	<div
		class="wrapper"
		:class="{ 'hover': hover }"
		@mouseover="hover=true"
		@mouseleave="hover=false">
		<div class="message">
			<div class="message-avatar">
				<Avatar :user="actorDisplayName" :display-name="actorDisplayName" />
			</div>
			<slot />
			<div v-show="isTemporary" class="message-right icon-loading-small" />
			<div v-show="!isTemporary" class="message-right">
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
		<div />
	</div>
</template>

<script>
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import Actions from 'nextcloud-vue/dist/Components/Actions'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'

export default {
	name: 'Message',
	components: {
		Avatar,
		Actions,
		ActionButton
	},
	props: {
		/**
		 * The message username.
		 */
		actorDisplayName: {
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
		/**
		 * The message object.
		 */
		message: {
			type: Object,
			required: true
		}
	},
	data: function() {
		return {
			hover: false
		}
	},
	computed: {
		messageTime() {
			return OC.Util.formatDate(this.timestamp * 1000, 'LT')
		},
		isTemporary() {
			return this.timestamp === 0
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

.wrapper {
	width: 100%;
	padding: 0px 0 0px 0;
	&:focus {
		background-color: rgba(47, 47, 47, 0.068);
	}
}

.message {
    display: flex;
    max-width: 600px;
	padding: 8px 0 8px 0;
	margin: auto;
	&-avatar {
		width: 52px;
		min-width: 52px;
		padding: 4px 8px 0 8px;
	}
	&-right {
		display: flex;
		min-width: 110px;
		color: #989898;
		padding: 0px 8px 0 8px;
	}

.actions {
	position: absolute;
	margin: -14px 0 0 50px;
	padding:2px;
	}
}

</style>
