/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getGuestNickname, setGuestNickname } from '@nextcloud/auth'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { setGuestUserName } from '../services/participantsService.js'
import { useActorStore } from './actor.ts'

type AddGuestNamePayload = { token: string, actorId: string, actorDisplayName: string }

export const useGuestNameStore = defineStore('guestName', () => {
	const LOCALIZED_GUEST = t('spreed', 'Guest')

	/** A map of guest names per conversation token and actorId */
	const guestNames = ref<Record<string, Record<string, string>>>({})

	/** An own display name of a current guest-user */
	const guestUserName = ref(getGuestNickname() || '')

	/**
	 * Gets the participant display name
	 *
	 * @param token the conversation's token
	 * @param actorId the participant actorId
	 */
	function getGuestName(token: string, actorId: string): string {
		return guestNames.value[token]?.[actorId] ?? LOCALIZED_GUEST
	}

	/**
	 * Gets the participant display name with suffix
	 * if the display name is not default, gets localized 'Guest'
	 *
	 * @param token the conversation's token
	 * @param actorId the participant actorId
	 */
	function getGuestNameWithGuestSuffix(token: string, actorId: string): string {
		const guest = getGuestName(token, actorId)
		if (guest === LOCALIZED_GUEST) {
			return guest
		}
		return t('spreed', '{guest} (guest)', { guest })
	}

	/**
	 * Adds a guest name to the store
	 *
	 * @param payload the wrapping object
	 * @param payload.token the token of the conversation
	 * @param payload.actorId the guest id
	 * @param payload.actorDisplayName the display name to set
	 * @param options options
	 * @param options.noUpdate Override the display name or set it, if it is empty
	 */
	function addGuestName({ token, actorId, actorDisplayName }: AddGuestNamePayload, { noUpdate }: { noUpdate: boolean }) {
		if (!guestNames.value[token]) {
			guestNames.value[token] = {}
		}
		if (!guestNames.value[token][actorId] || actorDisplayName === '') {
			guestNames.value[token][actorId] = LOCALIZED_GUEST
		} else if (noUpdate) {
			return
		}

		if (actorDisplayName) {
			guestNames.value[token][actorId] = actorDisplayName
		}
	}

	/**
	 * Add the submitted guest name to the store
	 *
	 * @param token the token of the conversation
	 * @param name the new guest name
	 */
	async function submitGuestUsername(token: string, name: string) {
		if (!name) {
			return
		}
		const actorStore = useActorStore()
		const actorId = actorStore.actorId!
		const previousName = getGuestName(token, actorId)

		try {
			actorStore.setDisplayName(name)
			addGuestName({
				token,
				actorId,
				actorDisplayName: name,
			}, { noUpdate: false })

			await setGuestUserName(token, name)

			setGuestNickname(name)
		} catch (error) {
			actorStore.setDisplayName(previousName)
			addGuestName({
				token,
				actorId,
				actorDisplayName: previousName,
			}, { noUpdate: false })
			console.error(error)
		}
	}

	return {
		guestUserName,

		getGuestName,
		getGuestNameWithGuestSuffix,
		addGuestName,
		submitGuestUsername,
	}
})
