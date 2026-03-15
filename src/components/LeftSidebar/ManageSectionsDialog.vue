<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('spreed', 'Manage sections')"
		size="small"
		@closing="$emit('close')">
		<div class="manage-sections">
			<div class="manage-sections__add">
				<NcTextField
					v-model="newSectionName"
					:label="t('spreed', 'New section name')"
					:showTrailingButton="newSectionName !== ''"
					trailingButtonIcon="arrowEnd"
					@trailingButtonClick="addSection"
					@keyup.enter="addSection" />
			</div>

			<ul class="manage-sections__list">
				<NcListItem
					v-for="(section, index) in orderedSections"
					:key="section.id"
					:name="section.name"
					:class="{ 'manage-sections__item--drag-over': dragOverIndex === index }"
					class="manage-sections__item"
					draggable="true"
					forceMenu
					:actionsAriaLabel="t('spreed', 'Section actions')"
					@dragstart="onDragStart(index)"
					@dragover.prevent="onDragOver(index)"
					@dragleave="onDragLeave"
					@drop.prevent="onDrop(index)"
					@dragend="onDragEnd">
					<template #icon>
						<IconDragVertical class="manage-sections__drag-handle" :size="20" />
					</template>
					<template #actions>
						<NcActionButton closeAfterClick @click="startRename(section)">
							<template #icon>
								<IconPencilOutline :size="20" />
							</template>
							{{ t('spreed', 'Rename') }}
						</NcActionButton>
						<NcActionButton class="critical" closeAfterClick @click="removeSection(section.id)">
							<template #icon>
								<IconTrashCanOutline :size="20" />
							</template>
							{{ t('spreed', 'Delete') }}
						</NcActionButton>
					</template>
				</NcListItem>
			</ul>

			<NcDialog
				v-if="renamingSection"
				:name="t('spreed', 'Rename section')"
				size="small"
				@closing="renamingSection = null">
				<NcTextField
					v-model="renameValue"
					:label="t('spreed', 'Section name')"
					@keyup.enter="confirmRename" />
				<template #actions>
					<NcButton variant="primary" @click="confirmRename">
						{{ t('spreed', 'Rename') }}
					</NcButton>
				</template>
			</NcDialog>
		</div>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconDragVertical from 'vue-material-design-icons/DragVertical.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconTrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { useConversationSectionsStore } from '../../stores/conversationSections.ts'

export default {
	name: 'ManageSectionsDialog',

	components: {
		NcActionButton,
		NcButton,
		NcDialog,
		NcListItem,
		NcTextField,
		IconDragVertical,
		IconPencilOutline,
		IconTrashCanOutline,
	},

	emits: ['close'],

	setup() {
		return {
			sectionsStore: useConversationSectionsStore(),
		}
	},

	data() {
		return {
			newSectionName: '',
			renamingSection: null,
			renameValue: '',
			dragIndex: null,
			dragOverIndex: null,
		}
	},

	computed: {
		orderedSections() {
			return [...this.sectionsStore.sortedSections]
		},
	},

	methods: {
		t,

		async addSection() {
			const name = this.newSectionName.trim()
			if (!name) {
				return
			}
			await this.sectionsStore.createSection(name)
			this.newSectionName = ''
		},

		startRename(section) {
			this.renamingSection = section
			this.renameValue = section.name
		},

		async confirmRename() {
			const name = this.renameValue.trim()
			if (!name || !this.renamingSection) {
				return
			}
			await this.sectionsStore.updateSectionName(this.renamingSection.id, name)
			this.renamingSection = null
		},

		async removeSection(sectionId) {
			await this.sectionsStore.removeSection(sectionId)
		},

		onDragStart(index) {
			this.dragIndex = index
		},

		onDragOver(index) {
			this.dragOverIndex = index
		},

		onDragLeave() {
			this.dragOverIndex = null
		},

		async onDrop(index) {
			this.dragOverIndex = null
			if (this.dragIndex === null || this.dragIndex === index) {
				return
			}
			const sections = [...this.orderedSections]
			const [moved] = sections.splice(this.dragIndex, 1)
			sections.splice(index, 0, moved)
			const orderedIds = sections.map((s) => s.id)
			await this.sectionsStore.reorderSections(orderedIds)
			this.dragIndex = null
		},

		onDragEnd() {
			this.dragIndex = null
			this.dragOverIndex = null
		},
	},
}
</script>

<style lang="scss" scoped>
.manage-sections {
	padding: var(--default-grid-baseline);

	&__add {
		margin-bottom: calc(var(--default-grid-baseline) * 4);
	}

	&__list {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	&__item {
		cursor: grab;

		&--drag-over {
			border-top: 2px solid var(--color-primary-element);
		}
	}

	&__drag-handle {
		cursor: grab;
		color: var(--color-text-maxcontrast);
	}
}
</style>
