/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { canView, getViewer } from '@nextcloud/viewer'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { ref } from 'vue'
import { getFileEtag, useViewer } from '../useViewer.ts'

const mocks = vi.hoisted(() => ({
	isInCall: { current: null },
	callViewStore: { setIsViewerOverlay: vi.fn() },
	actorStore: { userId: 'alice' },
}))

vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	getBaseUrl: () => 'https://nextcloud.local',
	generateRemoteUrl: (service) => `https://nextcloud.local/remote.php/${service}`,
}))

vi.mock('../useIsInCall.js', () => ({
	useIsInCall: () => mocks.isInCall.current,
}))
vi.mock('../../stores/callView.ts', () => ({
	useCallViewStore: () => mocks.callViewStore,
}))
vi.mock('../../stores/actor.ts', () => ({
	useActorStore: () => mocks.actorStore,
}))

const talkFile = {
	type: 'file',
	id: '159',
	name: 'image.jpeg',
	size: '339068',
	path: 'Media/image.jpeg',
	link: 'https://nextcloud.local/index.php/f/159',
	etag: 'd831983d07efacb1b701417915e2ea94',
	permissions: '27',
	mimetype: 'image/jpeg',
	'preview-available': 'yes',
	'hide-download': 'no',
	width: '1920',
	height: '1080',
	blurhash: 'L00000fQfQfQfQfQfQfQfQfQfQfQ',
}

const guestTalkFile = {
	...talkFile,
	path: 'image.jpeg',
	link: 'https://nextcloud.local/index.php/s/AbCdEfGhIjKlMnO',
}

describe('useViewer', () => {
	beforeEach(() => {
		mocks.isInCall.current = ref(true)
		mocks.actorStore.userId = 'alice'
		vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.clearAllMocks()
		vi.restoreAllMocks()
	})

	test('getFileEtag returns the etag of a file saved in the Viewer', () => {
		expect(getFileEtag(talkFile.id, talkFile.etag)).toBe(talkFile.etag)

		emit('files:node:updated', { fileid: 159, attributes: { etag: 'etag-saved' } })

		expect(getFileEtag(talkFile.id, talkFile.etag)).toBe('etag-saved')
	})

	test('canOpenInViewer returns false when the Viewer object cannot be built', () => {
		const { canOpenInViewer } = useViewer('talk')

		// permissions above Permission.ALL fail the node validation
		expect(canOpenInViewer({ ...talkFile, permissions: '999' })).toBe(false)
		expect(canView).not.toHaveBeenCalled()
	})

	test.each([
		['user', 'alice', talkFile, 'https://nextcloud.local/remote.php/dav/files/alice/Media/image.jpeg'],
		['guest', null, guestTalkFile, 'https://nextcloud.local/public.php/dav/files/AbCdEfGhIjKlMnO'],
	])('openViewer opens a node with the %s WebDAV source and no owner', async (_, userId, file, source) => {
		mocks.actorStore.userId = userId
		const open = vi.fn().mockResolvedValue({ addEventListener: vi.fn() })
		getViewer.mockReturnValueOnce({ open })
		const { openViewer } = useViewer('talk')

		await openViewer([file])

		const node = open.mock.calls[0][1]
		expect(node.source).toBe(source)
		expect(node.owner).toBeNull()
		expect(node.mime).toBe('image/jpeg')
	})

	test('openViewer resets the overlay when the Viewer fails to open', async () => {
		getViewer.mockReturnValueOnce({ open: vi.fn().mockRejectedValue(new Error('failed')) })
		const { openViewer } = useViewer('talk')

		await openViewer([talkFile])

		expect(mocks.callViewStore.setIsViewerOverlay).toHaveBeenNthCalledWith(1, true)
		expect(mocks.callViewStore.setIsViewerOverlay).toHaveBeenNthCalledWith(2, false)
		expect(showError).toHaveBeenCalled()
	})
})
