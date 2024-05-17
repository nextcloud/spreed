<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="contact-selection-bubble">
		<AvatarWrapper :id="participant.id"
			token="new"
			class="contact-selection-bubble__avatar"
			:name="participant.label"
			:source="participant.source"
			:size="AVATAR.SIZE.EXTRA_SMALL"
			disable-menu
			disable-tooltip />
		<span class="contact-selection-bubble__username">
			{{ displayName }}
		</span>
		<NcButton type="tertiary-no-background"
			:aria-label="removeLabel"
			@click="$emit('update', participant)">
			<template #icon>
				<Close :size="16" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import Close from 'vue-material-design-icons/Close.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../constants.js'

export default {
	name: 'ContactSelectionBubble',

	components: {
		AvatarWrapper,
		NcButton,
		Close,
	},

	props: {
		participant: {
			type: Object,
			required: true,
		},
	},

	emits: ['update'],

	setup() {
		return { AVATAR }
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
		t,
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
	background-color: var(--color-primary-element-light);
	border-radius: $bubble-height;
	height: $bubble-height;
	overflow: hidden;
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
