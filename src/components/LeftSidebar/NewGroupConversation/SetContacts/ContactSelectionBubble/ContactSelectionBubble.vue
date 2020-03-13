<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="contact-selection-bubble">
		<Avatar
			class="contact-selection-bubble__avatar"
			:user="participant.userId"
			:display-name="participant.label"
			:disable-menu="true"
			:disable-tooltip="true"
			:size="24" />
		<label class="contact-selection-bubble__username">
			{{ trimmedName }}
		</label>
		<button
			class="icon-close contact-selection-bubble__remove"
			@click="removeParticipantFromSelection(participant)" />
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
export default {
	name: 'ContactSelectionBubble',

	components: {
		Avatar,
	},

	props: {
		participant: {
			type: Object,
			required: true,
		},
	},

	computed: {
		// First word of the  string
		trimmedName() {
			return this.participant.label.match(/^([\w]+)/)[0]
		},
	},

	methods: {
		removeParticipantFromSelection(participant) {
			this.$store.dispatch('updateSelectedParticipants', participant)
		},
	},
}
</script>

<style lang="scss" scoped>

.contact-selection-bubble {
	display: flex;
	align-items: center;
    margin: 4px;
    background-color: var(--color-primary-light);
    border-radius: 24px;
	&__avatar {
		margin-right: 4px;
	}
	// Limit the length of the username
	&__username {
		max-width: 80px;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	&__remove {
		margin-left: 4px;
		border: none;
		background-color: transparent;
	}
}

</style>
