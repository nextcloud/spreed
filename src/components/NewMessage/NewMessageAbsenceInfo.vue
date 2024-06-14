<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- eslint-disable vue/singleline-html-element-content-newline -->
<template>
	<NcNoteCard type="info" class="absence-reminder">
		<template #icon>
			<AvatarWrapper :id="userAbsence.userId"
				:token="token"
				:name="displayName"
				source="users"
				disable-menu
				disable-tooltip />
		</template>
		<h4 class="absence-reminder__caption">{{ userAbsenceCaption }}</h4>
		<NcButton v-if="userAbsenceMessage && isTextMoreThanOneLine"
			class="absence-reminder__button"
			type="tertiary"
			@click="toggleCollapsed">
			<template #icon>
				<ChevronUp class="icon" :class="{'icon--reverted': !collapsed}" :size="20" />
			</template>
		</NcButton>
		<p ref="absenceMessage" class="absence-reminder__message" :class="{'absence-reminder__message--collapsed': collapsed}">{{ userAbsenceMessage }}</p>
	</NcNoteCard>
</template>

<script>
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../constants.js'

export default {
	name: 'NewMessageAbsenceInfo',

	components: {
		AvatarWrapper,
		ChevronUp,
		NcButton,
		NcNoteCard,
	},

	props: {
		userAbsence: {
			type: Object,
			required: true,
		},

		displayName: {
			type: String,
			required: true,
		},
	},

	setup() {
		return { AVATAR }
	},

	data() {
		return {
			collapsed: true,
			isTextMoreThanOneLine: false,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		userAbsenceCaption() {
			return t('spreed', '{user} is out of office and might not respond.', { user: this.displayName })
		},

		userAbsenceMessage() {
			return this.userAbsence.message || this.userAbsence.shortMessage
		},
	},

	watch: {
		userAbsenceMessage() {
			this.$nextTick(() => {
				this.setIsTextMoreThanOneLine()
			})
		},
	},

	mounted() {
		this.setIsTextMoreThanOneLine()
	},

	methods: {
		t,
		toggleCollapsed() {
			this.collapsed = !this.collapsed
		},

		setIsTextMoreThanOneLine() {
			this.isTextMoreThanOneLine = this.$refs.absenceMessage?.scrollHeight > this.$refs.absenceMessage?.clientHeight
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.absence-reminder {
	margin: 0 16px 12px;
	padding: 10px 10px 10px 6px;

	&__caption {
		font-weight: bold;
		margin: 5px 44px 5px 0;
	}

	&__message {
		white-space: pre-line;
		word-wrap: break-word;

		&--collapsed {
			text-overflow: ellipsis;
			overflow: hidden;
			display: -webkit-box;
			-webkit-line-clamp: 1;
			-webkit-box-orient: vertical;
		}
	}

	&__button {
		position: absolute !important;
		top: 4px;
		right: 20px;

		& .icon {
			transition: $transition;

			&--reverted {
				transform: rotate(180deg);
			}
		}
	}
}
</style>
