/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Participant } from '../types/index.ts'

import { ATTENDEE } from '../constants.ts'

/**
 * Whether a participant is included in the participant count shown in the sidebar.
 *
 * @param participant participant to check
 */
export function isCountedParticipant(participant: Pick<Participant, 'actorType'>): boolean {
	return participant.actorType !== ATTENDEE.ACTOR_TYPE.GROUPS
		&& participant.actorType !== ATTENDEE.ACTOR_TYPE.CIRCLES
		&& participant.actorType !== ATTENDEE.ACTOR_TYPE.TEAMS
		&& participant.actorType !== ATTENDEE.ACTOR_TYPE.BOTS
		&& participant.actorType !== ATTENDEE.ACTOR_TYPE.BRIDGED
}
