<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { INode } from '@nextcloud/files'

import { showWarning } from '@nextcloud/dialogs'
import { FileType, getSidebar } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { ShareType } from '@nextcloud/sharing'
import { ref, useTemplateRef, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconMessageOutline from 'vue-material-design-icons/MessageOutline.vue'
import IconShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import IconTalk from '../../../img/app-dark.svg?raw'
import { getFileConversation } from '../../services/filesIntegrationServices.ts'

const props = defineProps<{
	node: INode
	folder: Record<string, unknown>
	view: Record<string, unknown>
	active: boolean
}>()

const appContainer = useTemplateRef<HTMLDivElement>('appContainer')
const isTalkSidebarSupportedForFile = ref<boolean | undefined>(undefined)
const token = ref<string>()
/** Files posted in a team conversation are discussed in a thread there */
const threadId = ref<number>(0)
const isTalkSidebarMounted = ref<boolean>(false)

const isOtherTalkInstanceMounted = ref<boolean>(false)
checkOtherTalkInstanceMounted()

watch(() => props.active, (active) => {
	if (active) {
		setTalkSidebarSupportedForFile()
	} else if (isTalkSidebarMounted.value) {
		window.OCA.Talk.unmountInstance?.()
		isTalkSidebarMounted.value = false
	}
}, { immediate: true })

/**
 * Check if file is shared and Talk integration can be opened
 */
async function setTalkSidebarSupportedForFile() {
	isTalkSidebarSupportedForFile.value = undefined
	token.value = ''
	threadId.value = 0

	if (!props.node || !props.node.fileid) {
		isTalkSidebarSupportedForFile.value = false
		return
	}

	if (props.node.type === FileType.Folder) {
		isTalkSidebarSupportedForFile.value = false
		return
	}

	if (props.node.attributes?.['share-owner-id']) {
		// Shared with me
		isTalkSidebarSupportedForFile.value = true
		return
	}

	if (!props.node.attributes?.['share-types']) {
		try {
			await loadFileConversation(props.node.fileid)
			isTalkSidebarSupportedForFile.value = !!token.value
		} catch (error) {
			isTalkSidebarSupportedForFile.value = false
		}
		return
	}

	const shareTypes = Object.values(props.node.attributes?.['share-types'] || {}).flat().filter(function(shareType) {
		const type = parseInt(shareType as unknown as string)
		return type === ShareType.User
			|| type === ShareType.Group
			|| type === ShareType.Team
			|| type === ShareType.Room
			|| type === ShareType.Link
			|| type === ShareType.Email
	})

	if (shareTypes.length === 0) {
		try {
			await loadFileConversation(props.node.fileid)
			isTalkSidebarSupportedForFile.value = !!token.value
		} catch (error) {
			isTalkSidebarSupportedForFile.value = false
		}
		return
	}

	isTalkSidebarSupportedForFile.value = true
}

/**
 * Get the conversation of the file, or the team conversation and the
 * thread when the file was posted in a team conversation
 *
 * @param fileId - id of the file
 */
async function loadFileConversation(fileId: number) {
	const data = (await getFileConversation(fileId)).data.ocs.data
	token.value = data.token || ''
	threadId.value = data.threadId ?? 0
}

/**
 * Mount a Talk integration app
 */
async function joinConversation() {
	try {
		if (checkOtherTalkInstanceMounted()) {
			// Fallback, should be prevented by isOtherTalkInstanceMounted
			showWarning(t('spreed', 'Duplicate session'))
			return
		}

		if (!token.value) {
			await loadFileConversation(props.node.fileid!)
		}

		import('./mainFilesSidebar.js')
			.then((module) => {
				module.mountApp(appContainer.value, props, token.value, threadId.value)
				isTalkSidebarMounted.value = true
			})
	} catch (error) {
		console.error('Failed to load Talk integration:', error)
	}
}

/**
 * Check if other Talk instance is active on a page (e.g. floating call)
 */
function checkOtherTalkInstanceMounted() {
	isOtherTalkInstanceMounted.value = !!window.OCA.Talk
	return isOtherTalkInstanceMounted.value
}

/**
 * Open a Sidebar Sharing tab
 */
function openSharingTab() {
	getSidebar().setActiveTab('sharing')
	isTalkSidebarMounted.value = false
}
</script>

<template>
	<div class="talkChatTab">
		<NcEmptyContent
			v-if="!isTalkSidebarMounted"
			class="empty-content">
			<template #icon>
				<NcLoadingIcon
					v-if="isTalkSidebarSupportedForFile === undefined"
					class="empty-content__icon"
					:size="64" />
				<NcIconSvgWrapper
					v-else
					class="empty-content__icon"
					:svg="IconTalk"
					:size="64" />
			</template>
			<template v-if="isTalkSidebarSupportedForFile !== undefined" #name>
				<h4>{{ t('spreed', 'Discuss this file') }}</h4>
			</template>
			<template #description>
				<p v-if="isTalkSidebarSupportedForFile === undefined">
					{{ t('spreed', 'Loading …') }}
				</p>
				<p v-else-if="isTalkSidebarSupportedForFile === false">
					{{ t('spreed', 'Share this file with others to discuss it') }}
				</p>
			</template>
			<template #action>
				<NcButton
					v-if="isTalkSidebarSupportedForFile === true"
					variant="primary"
					:disabled="isOtherTalkInstanceMounted"
					@click="joinConversation">
					<template #icon>
						<IconMessageOutline :size="20" />
					</template>
					{{ t('spreed', 'Join conversation') }}
				</NcButton>
				<NcButton
					v-if="isTalkSidebarSupportedForFile === false"
					variant="primary"
					@click="openSharingTab">
					<template #icon>
						<IconShareVariant :size="20" />
					</template>
					{{ t('spreed', 'Share this file') }}
				</NcButton>
			</template>
		</NcEmptyContent>
		<!-- Full app mounted here after joining -->
		<div ref="appContainer" class="app-container" />
	</div>
</template>

<style scoped lang="scss">
.talkChatTab {
	height: 100%;

	display: flex;
	flex-grow: 1;
	flex-direction: column;
}

.empty-content {
	/* Override default top margin set in server and center vertically instead. */
	margin-top: unset;
	height: 100%;

	&__icon {
		opacity: 1;
	}
}

.app-container {
	display: contents;
}
</style>
