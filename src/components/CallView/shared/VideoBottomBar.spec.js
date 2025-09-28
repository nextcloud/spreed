/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconHandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'
import IconVideoOffOutline from 'vue-material-design-icons/VideoOffOutline.vue'
import VideoBottomBar from './VideoBottomBar.vue'
import { CONVERSATION, PARTICIPANT } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useActorStore } from '../../../stores/actor.ts'
import { useCallViewStore } from '../../../stores/callView.ts'
import { findNcButton } from '../../../test-helpers.js'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel.js'

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
}))

describe('VideoBottomBar.vue', () => {
	const TOKEN = 'XXTOKENXX'
	const PARTICIPANT_NAME = 'John Doe'
	const PEER_ID = 'peer-id'
	const USER_ID = 'user-id-1'
	let store
	let callViewStore
	let testStoreConfig
	let componentProps
	let conversationProps
	let actorStore

	const audioIndicatorAriaLabels = [t('spreed', 'Mute'), t('spreed', 'Muted')]
	const videoIndicatorAriaLabels = [t('spreed', 'Disable video'), t('spreed', 'Enable video')]
	const screenSharingAriaLabel = t('spreed', 'Show screen')
	const followingButtonAriaLabel = t('spreed', 'Stop following')

	beforeEach(() => {
		setActivePinia(createPinia())
		callViewStore = useCallViewStore()
		actorStore = useActorStore()

		conversationProps = {
			token: TOKEN,
			lastCommonReadMessage: 0,
			type: CONVERSATION.TYPE.GROUP,
			participantType: PARTICIPANT.TYPE.OWNER,
			readOnly: CONVERSATION.STATE.READ_WRITE,
		}

		componentProps = {
			token: TOKEN,
			model: {
				attributes: {
					connectionState: ConnectionState.CONNECTED,
					raisedHand: {
						state: false,
					},
					audioAvailable: true,
					videoAvailable: true,
					screen: true,
					speaking: false,
					peerId: PEER_ID,
				},
				forceMute: vi.fn(),
			},
			participantName: PARTICIPANT_NAME,
			sharedData: {
				remoteVideoBlocker: {
					isVideoEnabled: vi.fn().mockReturnValue(true),
					setVideoEnabled: vi.fn(),
				},
			},
		}

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = vi.fn().mockReturnValue((token) => conversationProps)
		actorStore.userId = USER_ID
		store = createStore(testStoreConfig)
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	/**
	 * Shared function to mount component
	 */
	function mountVideoBottomBar(props) {
		return mount(VideoBottomBar, {
			global: {
				plugins: [store],
			},
			props,
		})
	}

	describe('unit tests', () => {
		describe('render component', () => {
			test('component renders properly', async () => {
				const wrapper = mountVideoBottomBar(componentProps)
				expect(wrapper.exists()).toBeTruthy()
				expect(wrapper.classes('wrapper')).toBeDefined()
			})

			test('component has class "wrapper--big" for main view', async () => {
				componentProps.isBig = true
				const wrapper = mountVideoBottomBar(componentProps)
				expect(wrapper.exists()).toBeTruthy()
				expect(wrapper.classes('wrapper--big')).toBeDefined()
			})

			test('component renders all indicators by default', async () => {
				const wrapper = mountVideoBottomBar(componentProps)
				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators).toHaveLength(3)
			})

			test('component does not render indicators for ScreenShare.vue component', async () => {
				componentProps.isScreen = true
				const wrapper = mountVideoBottomBar(componentProps)
				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators).toHaveLength(0)
			})

			test('component does not show indicators after video overlay is off', async () => {
				const wrapper = mountVideoBottomBar(componentProps)
				componentProps.showVideoOverlay = false
				await wrapper.setProps(cloneDeep(componentProps))

				const indicators = wrapper.findAllComponents(NcButton)
				indicators.forEach((indicator) => {
					expect(indicator.isVisible()).toBeFalsy()
				})
			})

			test('component does not render anything when used in sidebar', async () => {
				componentProps.isSidebar = true
				const wrapper = mountVideoBottomBar(componentProps)
				const participantName = wrapper.find('.participant-name')
				expect(participantName.exists()).toBeFalsy()

				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators).toHaveLength(0)
			})
		})

		describe('render participant name', () => {
			test('name is shown by default', () => {
				const wrapper = mountVideoBottomBar(componentProps)
				const participantName = wrapper.find('.participant-name')
				expect(participantName.isVisible()).toBeTruthy()
				expect(participantName.text()).toBe(PARTICIPANT_NAME)
			})

			test('name is not shown if all checks are falsy', () => {
				componentProps.showVideoOverlay = false
				const wrapper = mountVideoBottomBar(componentProps)
				const participantName = wrapper.find('.participant-name')
				expect(participantName.isVisible()).toBeFalsy()
			})
		})

		describe('render indicators', () => {
			describe('connection failed indicator', () => {
				test('indicator is not shown by default, other indicators are visible', () => {
					componentProps.model.attributes.raisedHand.state = true
					const wrapper = mountVideoBottomBar(componentProps)

					const iceFailedIndicator = wrapper.findComponent(IconAlertCircleOutline)
					expect(iceFailedIndicator.exists()).toBeFalsy()

					const raiseHandIndicator = wrapper.findComponent(IconHandBackLeft)
					expect(raiseHandIndicator.exists()).toBeTruthy()

					const indicators = wrapper.findAllComponents(NcButton)
					indicators.forEach((indicator) => {
						expect(indicator.isVisible()).toBeTruthy()
					})
				})

				test('indicator is shown when model prop is true, other indicators are hidden', () => {
					componentProps.model.attributes.raisedHand.state = true
					componentProps.model.attributes.connectionState = ConnectionState.FAILED_NO_RESTART
					const wrapper = mountVideoBottomBar(componentProps)

					const iceFailedIndicator = wrapper.findComponent(IconAlertCircleOutline)
					expect(iceFailedIndicator.exists()).toBeTruthy()

					const raiseHandIndicator = wrapper.findComponent(IconHandBackLeft)
					expect(raiseHandIndicator.exists()).toBeFalsy()

					const indicators = wrapper.findAllComponents(NcButton)
					indicators.forEach((indicator) => {
						expect(indicator.isVisible()).toBeFalsy()
					})
				})
			})

			describe('raise hand indicator', () => {
				test('indicator is not shown by default', () => {
					const wrapper = mountVideoBottomBar(componentProps)

					const raiseHandIndicator = wrapper.findComponent(IconHandBackLeft)
					expect(raiseHandIndicator.exists()).toBeFalsy()
				})

				test('indicator is shown when model prop is true', () => {
					componentProps.model.attributes.raisedHand.state = true
					const wrapper = mountVideoBottomBar(componentProps)

					const raiseHandIndicator = wrapper.findComponent(IconHandBackLeft)
					expect(raiseHandIndicator.exists()).toBeTruthy()
				})
			})
		})

		describe('render buttons', () => {
			describe('audio indicator', () => {
				test('button is rendered properly', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.exists()).toBeTruthy()
				})

				test('button is visible for moderators when audio is available', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.isVisible()).toBeTruthy()
				})

				test('button is not rendered for non-moderators when audio is available', () => {
					conversationProps.participantType = PARTICIPANT.TYPE.USER
					const wrapper = mountVideoBottomBar(componentProps)
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.exists()).toBeFalsy()
				})

				test('button is visible for everyone when audio is unavailable', () => {
					conversationProps.participantType = PARTICIPANT.TYPE.USER
					componentProps.model.attributes.audioAvailable = false
					const wrapper = mountVideoBottomBar(componentProps)
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.isVisible()).toBeTruthy()
				})

				test('button is enabled for moderators', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.attributes('disabled')).toBeUndefined()
				})

				test('button is disabled when audio is unavailable', () => {
					componentProps.model.attributes.audioAvailable = false
					const wrapper = mountVideoBottomBar(componentProps)
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.attributes('disabled')).toBeDefined()
				})

				test('method is called after click', async () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					await audioIndicator.trigger('click')

					expect(wrapper.vm.$props.model.forceMute).toHaveBeenCalled()
				})
			})

			describe('video indicator', () => {
				test('button is rendered properly', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const videoIndicator = findNcButton(wrapper, videoIndicatorAriaLabels)
					expect(videoIndicator.exists()).toBeTruthy()
				})

				test('button is visible when video is available', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const videoIndicator = findNcButton(wrapper, videoIndicatorAriaLabels)
					expect(videoIndicator.isVisible()).toBeTruthy()
				})

				test('button is not rendered when video is unavailable', () => {
					componentProps.model.attributes.videoAvailable = false
					const wrapper = mountVideoBottomBar(componentProps)
					const videoIndicator = findNcButton(wrapper, videoIndicatorAriaLabels)
					expect(videoIndicator.exists()).toBeFalsy()
				})

				test('button shows proper icon if video is enabled', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const videoOnIcon = wrapper.findComponent(IconVideo)
					expect(videoOnIcon.exists()).toBeTruthy()
				})

				test('button shows proper icon if video is blocked', () => {
					componentProps.sharedData.remoteVideoBlocker.isVideoEnabled.mockReturnValue(false)
					const wrapper = mountVideoBottomBar(componentProps)
					const videoOffIcon = wrapper.findComponent(IconVideoOffOutline)
					expect(videoOffIcon.exists()).toBeTruthy()
				})

				test('method is called after click', async () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const videoIndicator = findNcButton(wrapper, videoIndicatorAriaLabels)
					await videoIndicator.trigger('click')

					expect(wrapper.vm.$props.sharedData.remoteVideoBlocker.setVideoEnabled).toHaveBeenCalled()
					expect(wrapper.vm.$props.sharedData.remoteVideoBlocker.setVideoEnabled).toHaveBeenCalledWith(false)
				})
			})

			describe('screen sharing indicator', () => {
				test('button is rendered properly', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const screenSharingIndicator = findNcButton(wrapper, screenSharingAriaLabel)
					expect(screenSharingIndicator.exists()).toBeTruthy()
				})

				test('button is visible when screen is available', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const screenSharingIndicator = findNcButton(wrapper, screenSharingAriaLabel)
					expect(screenSharingIndicator.isVisible()).toBeTruthy()
				})

				test('button is not rendered when screen is unavailable', () => {
					componentProps.model.attributes.screen = false
					const wrapper = mountVideoBottomBar(componentProps)
					const screenSharingIndicator = findNcButton(wrapper, screenSharingAriaLabel)
					expect(screenSharingIndicator.exists()).toBeFalsy()
				})

				test('component emits peer id after click', async () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const screenSharingIndicator = findNcButton(wrapper, screenSharingAriaLabel)
					await screenSharingIndicator.trigger('click')

					expect(emit).toHaveBeenCalledWith('switch-screen-to-id', PEER_ID)
				})
			})

			describe('following button', () => {
				test('button is not rendered for participants by default', () => {
					const wrapper = mountVideoBottomBar(componentProps)
					const followingButton = findNcButton(wrapper, followingButtonAriaLabel)
					expect(followingButton.exists()).toBeFalsy()
				})

				test('button is not rendered for main speaker by default', () => {
					componentProps.isBig = true
					const wrapper = mountVideoBottomBar(componentProps)
					const followingButton = findNcButton(wrapper, followingButtonAriaLabel)
					expect(followingButton.exists()).toBeFalsy()
				})

				test('button is rendered when source is selected', () => {
					callViewStore.setSelectedVideoPeerId(PEER_ID)
					componentProps.isBig = true
					const wrapper = mountVideoBottomBar(componentProps)
					const followingButton = findNcButton(wrapper, followingButtonAriaLabel)
					expect(followingButton.exists()).toBeTruthy()
				})

				test('method is called after click', async () => {
					callViewStore.setSelectedVideoPeerId(PEER_ID)
					callViewStore.startPresentation(TOKEN)
					expect(callViewStore.selectedVideoPeerId).toBe(PEER_ID)
					expect(callViewStore.presentationStarted).toBeTruthy()

					componentProps.isBig = true
					const wrapper = mountVideoBottomBar(componentProps)

					const followingButton = findNcButton(wrapper, followingButtonAriaLabel)
					await followingButton.trigger('click')

					expect(callViewStore.selectedVideoPeerId).toBe(null)
					expect(callViewStore.presentationStarted).toBeFalsy()
				})
			})
		})
	})
})
