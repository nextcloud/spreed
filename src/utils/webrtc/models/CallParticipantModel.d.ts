/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CallParticipantModel, CallParticipantModelOptions } from '../../../types/index.ts'

export declare const ConnectionState: {
	NEW: 'new'
	CHECKING: 'checking'
	CONNECTED: 'connected'
	COMPLETED: 'completed'
	DISCONNECTED: 'disconnected'
	DISCONNECTED_LONG: 'disconnected-long'
	FAILED: 'failed'
	FAILED_NO_RESTART: 'failed-no-restart'
	CLOSED: 'closed'
}

export declare const CallParticipantModel: new (options: CallParticipantModelOptions) => CallParticipantModel
export type { CallParticipantModelOptions }
