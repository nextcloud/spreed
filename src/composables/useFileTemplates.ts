/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FileTemplate } from '../types/index.ts'

import { getCurrentUser } from '@nextcloud/auth'
import { ref } from 'vue'
import { getFileTemplates } from '../services/filesSharingServices.ts'

/**
 * Shared state for file templates
 */
let areFileTemplatesInitialised = false
let areFileTemplatesLoading = false
const fileTemplateOptions = ref<FileTemplate[]>([])

/**
 * Composable to get file templates
 */
export function useFileTemplates() {
	/**
	 * Fetch file templates from server and store them in the shared state
	 */
	async function fetchFileTemplates() {
		if (areFileTemplatesLoading) {
			// Already loading in parallel or loaded file templates, skipping
			return
		}

		if (!getCurrentUser()) {
			console.debug('Skip file templates setup for participants that are not logged in')
			areFileTemplatesInitialised = true
			return
		}

		try {
			areFileTemplatesLoading = true
			const response = await getFileTemplates()
			fileTemplateOptions.value = response.data.ocs.data
			areFileTemplatesInitialised = true
		} catch (error) {
			console.error('An error happened when trying to load the templates', error)
		} finally {
			areFileTemplatesLoading = false
		}
	}

	if (!areFileTemplatesInitialised) {
		fetchFileTemplates()
	}

	return fileTemplateOptions
}
