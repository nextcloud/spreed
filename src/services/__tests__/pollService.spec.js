/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { exportPoll } from '../pollService.ts'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('../../utils/fileDownload.ts', () => ({
	downloadBlob: vi.fn(),
}))

describe('pollService', () => {
	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('exportPoll', () => {
		it('calls the export endpoint with ods format', async () => {
			const blob = new Blob(['test'], { type: 'application/vnd.oasis.opendocument.spreadsheet' })
			axios.get.mockResolvedValue({
				data: blob,
				headers: { 'content-disposition': 'attachment; filename="room - Poll - test - 2026-04-09.ods"' },
			})

			await exportPoll('TOKEN123', 42, 'ods')

			expect(axios.get).toHaveBeenCalledWith(
				generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}/export/{format}', {
					token: 'TOKEN123',
					pollId: 42,
					format: 'ods',
				}),
				{ responseType: 'blob' },
			)
		})

		it('extracts filename from content-disposition header', async () => {
			const { downloadBlob } = await import('../../utils/fileDownload.ts')
			const blob = new Blob(['test'])
			axios.get.mockResolvedValue({
				data: blob,
				headers: { 'content-disposition': 'attachment; filename="My-Room - Poll - Question - 2026-04-09.ods"' },
			})

			await exportPoll('TOKEN123', 1, 'ods')

			expect(downloadBlob).toHaveBeenCalledWith(blob, 'My-Room - Poll - Question - 2026-04-09.ods')
		})

		it('calls the export endpoint with csv format', async () => {
			const blob = new Blob(['test'], { type: 'text/csv' })
			axios.get.mockResolvedValue({
				data: blob,
				headers: { 'content-disposition': 'attachment; filename="room - Poll - test - 2026-04-09.csv"' },
			})

			await exportPoll('TOKEN123', 42, 'csv')

			expect(axios.get).toHaveBeenCalledWith(
				generateOcsUrl('apps/spreed/api/v1/poll/{token}/{pollId}/export/{format}', {
					token: 'TOKEN123',
					pollId: 42,
					format: 'csv',
				}),
				{ responseType: 'blob' },
			)
		})

		it('falls back to default filename when no content-disposition', async () => {
			const { downloadBlob } = await import('../../utils/fileDownload.ts')
			const blob = new Blob(['test'])
			axios.get.mockResolvedValue({
				data: blob,
				headers: {},
			})

			await exportPoll('TOKEN123', 1, 'ods')

			expect(downloadBlob).toHaveBeenCalledWith(blob, 'poll.ods')
		})
	})
})
