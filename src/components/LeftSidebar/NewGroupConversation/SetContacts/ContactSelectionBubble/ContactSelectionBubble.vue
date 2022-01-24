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
		<AvatarWrapperSmall :id="participant.id"
			class="contact-selection-bubble__avatar"
			:name="participant.label"
			:source="participant.source"
			:show-user-status="false"
			:disable-menu="true"
			:disable-tooltip="true" />
		<span class="contact-selection-bubble__username">
			{{ displayName }}
		</span>
		<button class="icon-close contact-selection-bubble__remove"
			@click="removeParticipantFromSelection(participant)" />
	</div>
</template>

<script>
import AvatarWrapperSmall from '../../../../AvatarWrapper/AvatarWrapperSmall'
export default {
	name: 'ContactSelectionBubble',

	components: {
		AvatarWrapperSmall,
	},

	props: {
		participant: {
			type: Object,
			required: true,
		},
	},

	computed: {
		displayName() {
			// Used to be the group of characters before the first space in the name.
			// But it causes weird scenarios in formal companies or when people have titles.
			return this.participant.label
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

// Component Variables
$bubble-height: 24px;

.contact-selection-bubble {
	display: flex;
	align-items: center;
	margin: 4px;
	background-color: var(--color-primary-light);
	border-radius: $bubble-height;
	height: $bubble-height;
	&__avatar {
		margin-right: 4px;
	}
	// Limit the length of the username
	&__username {
		max-width: 190px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	&__remove {
		margin: 0 0 0 4px;
		border: none;
		border-radius: $bubble-height;
		height: $bubble-height;
		width: $bubble-height;
		background-color: var(--color-primary-active);
		&:active,
		&:focus {
			background-color: transparent !important;
		}
	}
}

</style>
