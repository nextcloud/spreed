/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import IconEmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import IconHandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import ReactionMenu from './ReactionMenu.vue'
import { PARTICIPANT } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../stores/actor.ts'

vi.mock('../../services/CapabilitiesManager.ts', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		getTalkConfig: vi.fn(),
	}
})

describe('ReactionMenu.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let actorStore
	let componentProps
	let wrapper

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()
		actorStore.sessionId = 'session-id-1'

		getTalkConfig.mockImplementation((token, key1, key2) => key2 === 'supported-reactions' ? ['👍', '❤️'] : null)

		store = createStore({
			getters: {
				conversation: () => () => ({ participantType: PARTICIPANT.TYPE.USER, objectType: '' }),
				dummyConversation: () => ({}),
			},
		})

		componentProps = {
			token: TOKEN,
			localMediaModel: {
				attributes: {
					raisedHand: { state: false },
				},
				toggleHandRaised: vi.fn(),
			},
			localCallParticipantModel: {
				sendReaction: vi.fn(),
			},
		}
	})

	afterEach(() => {
		wrapper?.unmount()
		vi.clearAllMocks()
	})

	/**
	 * Shared function to mount component
	 */
	function mountReactionMenu(props) {
		wrapper = mount(ReactionMenu, {
			attachTo: document.body,
			global: {
				plugins: [store],
			},
			props,
		})
		return wrapper
	}

	test('shows the default reaction icon when the hand is not raised', () => {
		mountReactionMenu(componentProps)

		expect(wrapper.findComponent(IconEmoticonOutline).exists()).toBeTruthy()
		expect(wrapper.findComponent(IconHandBackLeft).exists()).toBeFalsy()
	})

	test('shows the raised hand icon on the trigger when the hand is raised', () => {
		componentProps.localMediaModel.attributes.raisedHand.state = true
		mountReactionMenu(componentProps)

		expect(wrapper.findComponent(IconHandBackLeft).exists()).toBeTruthy()
		expect(wrapper.findComponent(IconEmoticonOutline).exists()).toBeFalsy()
	})

	test('toggles the hand raised state when clicking the raise hand action', async () => {
		mountReactionMenu(componentProps)

		// The actions menu content is teleported to the document body,
		// and the popover positioning resolves asynchronously
		await wrapper.find('button').trigger('click')
		await new Promise((resolve) => setTimeout(resolve))
		const raiseHandButton = document.body.querySelector('.raise-hand__button .action-button')
		raiseHandButton.click()

		expect(componentProps.localMediaModel.toggleHandRaised).toHaveBeenCalledWith(true)
	})
})
