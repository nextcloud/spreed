<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>
import type { FileStat, ResponseDataDetailed, SearchResult } from 'webdav'

import { defaultRootPath, getClient, resultToNode } from '@nextcloud/files/dav'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { ref, watch } from 'vue'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconFolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import { getTeamFolder } from '../../../services/teamsService.ts'
import { generatePreviewUrl, generateRecentFilesInFolderSearch } from '../../../utils/davUtils.ts'

type TeamFolderFile = {
	id: number
	name: string
	path: string
	previewUrl: string
	link: string
}

const props = defineProps<{
	teamId: string
	active: boolean
}>()

const emit = defineEmits<{
	/** Whether the team has a folder the user can access, so the section is shown */
	available: [value: boolean]
}>()

/** Files shown in the sidebar */
const LIMIT = 5
/** Files posted in the conversation are shown as shared items already, so more are fetched to fill up the list */
const SEARCH_LIMIT = 20

const folderPath = ref<string | null>(null)
const files = ref<TeamFolderFile[]>([])

watch([() => props.teamId, () => props.active], ([teamId, active]) => {
	if (teamId && active) {
		loadTeamFolder(teamId)
	}
}, { immediate: true })

/**
 * Load the folder of the team and its most recently modified files,
 * without the files posted in the conversation (its attachment folder)
 *
 * @param teamId - the team id
 */
async function loadTeamFolder(teamId: string) {
	let attachmentFolder: string
	try {
		const response = await getTeamFolder(teamId)
		folderPath.value = response.data.ocs.data.path
		attachmentFolder = response.data.ocs.data.attachmentFolder
	} catch {
		// No team folder, or no access to it
		folderPath.value = null
		files.value = []
		emit('available', false)
		return
	}
	emit('available', true)

	try {
		const client = getClient()
		const result = await client.search('/', {
			details: true,
			data: generateRecentFilesInFolderSearch(folderPath.value, SEARCH_LIMIT),
		}) as ResponseDataDetailed<SearchResult>

		const attachmentPath = '/' + folderPath.value + '/' + attachmentFolder + '/'
		files.value = result.data.results
			.map((stat: FileStat) => resultToNode(stat, defaultRootPath))
			.filter((node) => !node.path.startsWith(attachmentPath))
			.slice(0, LIMIT)
			.map((node) => ({
				id: node.fileid as number,
				name: node.basename,
				path: node.dirname,
				previewUrl: generatePreviewUrl(node.fileid as number, { x: 64, y: 64, etag: node.attributes.etag }),
				link: generateUrl('/f/{fileId}', { fileId: node.fileid as number }),
			}))
	} catch (error) {
		console.error('Error while searching the team folder', error)
		files.value = []
	}
}

/**
 * Link to the team folder in the Files app
 */
function folderLink() {
	return generateUrl('/apps/files/files') + '?dir=' + encodeURIComponent('/' + folderPath.value)
}
</script>

<template>
	<div v-if="folderPath !== null" class="team-folder">
		<NcAppNavigationCaption :name="t('spreed', 'Team folder')" />
		<ul v-if="files.length">
			<NcListItem
				v-for="file in files"
				:key="file.id"
				:name="file.name"
				:href="file.link"
				target="_blank"
				compact>
				<template #icon>
					<img class="team-folder__preview" :src="file.previewUrl" alt="">
				</template>
				<template #subname>
					{{ file.path }}
				</template>
			</NcListItem>
		</ul>
		<NcButton
			variant="tertiary"
			class="team-folder__open"
			:href="folderLink()"
			target="_blank">
			<template #icon>
				<IconFolderOutline :size="20" />
			</template>
			{{ t('spreed', 'Go to folder') }}
		</NcButton>
	</div>
</template>

<style lang="scss" scoped>
.team-folder {
	&__preview {
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		border-radius: var(--border-radius);
		object-fit: cover;
	}

	&__open {
		margin-block: calc(var(--default-grid-baseline) * 2);
	}
}
</style>
