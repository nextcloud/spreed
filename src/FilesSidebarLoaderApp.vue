<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { type INode } from '@nextcloud/files'
import { FileType, getSidebar } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { ShareType } from '@nextcloud/sharing'
import { ref, useTemplateRef, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { getFileConversation } from './services/filesIntegrationServices.ts'

const props = defineProps<{
	node: INode
	folder: Record<string, unknown>
	view: Record<string, unknown>
	active: boolean
}>()

const appContainer = useTemplateRef<HTMLDivElement>('appContainer')
const isTalkSidebarSupportedForFile = ref<boolean | undefined>(undefined)
const token = ref<string>()
const isTalkSidebarMounted = ref<boolean>(false)

watch(() => props.active, (active) => {
	if (active) {
		setTalkSidebarSupportedForFile()
	} else {
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
			token.value = (await getFileConversation(props.node.fileid)).data.ocs.data.token || ''
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
			token.value = (await getFileConversation(props.node.fileid)).data.ocs.data.token || ''
			isTalkSidebarSupportedForFile.value = !!token.value
		} catch (error) {
			isTalkSidebarSupportedForFile.value = false
		}
		return
	}

	isTalkSidebarSupportedForFile.value = true
}

/**
 * Mount a Talk integration app
 */
async function joinConversation() {
	try {
		if (!token.value) {
			token.value = (await getFileConversation(props.node.fileid!)).data.ocs.data.token || ''
		}

		import('./mainFilesSidebar.js')
			.then((module) => {
				module.mountApp(appContainer.value, props, token.value)
				isTalkSidebarMounted.value = true
			})
	} catch (error) {
		console.error('Failed to load Talk integration:', error)
	}
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
		<div v-if="isTalkSidebarSupportedForFile === undefined" class="emptycontent ui-not-ready-placeholder">
			<div class="icon icon-loading" />
		</div>
		<div v-else-if="!isTalkSidebarSupportedForFile" class="emptycontent file-not-shared">
			<div class="icon icon-talk" />
			<h2>{{ t('spreed', 'Discuss this file') }}</h2>
			<p>{{ t('spreed', 'Share this file with others to discuss it') }}</p>
			<NcButton variant="primary" @click="openSharingTab">
				{{ t('spreed', 'Share this file') }}
			</NcButton>
		</div>
		<div v-else-if="isTalkSidebarSupportedForFile && !isTalkSidebarMounted" class="emptycontent room-not-joined">
			<div class="icon icon-talk" />
			<h2>{{ t('spreed', 'Discuss this file') }}</h2>
			<NcButton variant="primary" @click="joinConversation">
				{{ t('spreed', 'Join conversation') }}
			</NcButton>
		</div>
		<!-- Full app mounted here after joining -->
		<div ref="appContainer" class="app-container" />
	</div>
</template>

<style scoped>
.talkChatTab {
	height: 100%;

	display: flex;
	flex-grow: 1;
	flex-direction: column;
}

.emptycontent {
	/* Override default top margin set in server and center vertically
	 * instead. */
	margin-top: unset;

	height: 100%;

	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}

.app-container {
	display: contents;
}
</style>
