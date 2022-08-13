<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
		<ButtonVue type="tertiary-no-background"
			:aria-label="removeLabel"
			@click="removeParticipantFromSelection(participant)">
			<template #icon>
				<Close :size="16" />
			</template>
		</ButtonVue>
	</div>
</template>

<script>
import ButtonVue from '@nextcloud/vue/dist/Components/ButtonVue'
import Close from 'vue-material-design-icons/Close.vue'
import AvatarWrapperSmall from '../../../../AvatarWrapper/AvatarWrapperSmall.vue'
export default {
	name: 'ContactSelectionBubble',

	components: {
		AvatarWrapperSmall,
		ButtonVue,
		Close,
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

		removeLabel() {
			return t('spreed', 'Remove participant {name}', { name: this.displayName })
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
}

</style>
