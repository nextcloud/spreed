<!--
  - @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
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

<!-- eslint-disable vue/singleline-html-element-content-newline -->
<template>
	<NcNoteCard type="info" class="absence-reminder">
		<template #icon>
			<AvatarWrapper :id="userAbsence.userId"
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
