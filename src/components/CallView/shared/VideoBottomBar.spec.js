/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex, { Store } from 'vuex'

import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'

import VideoBottomBar from './VideoBottomBar.vue'

import { CONVERSATION, PARTICIPANT } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useCallViewStore } from '../../../stores/callView.ts'
import { findNcButton } from '../../../test-helpers.js'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel.js'

jest.mock('@nextcloud/event-bus', () => ({
	emit: jest.fn(),
	subscribe: jest.fn(),
}))

describe('VideoBottomBar.vue', () => {
	const TOKEN = 'XXTOKENXX'
	const PARTICIPANT_NAME = 'John Doe'
	const PEER_ID = 'peer-id'
	const USER_ID = 'user-id-1'
	let localVue
	let store
	let callViewStore
	let testStoreConfig
	let componentProps
	let conversationProps

	const audioIndicatorAriaLabels = [t('spreed', 'Mute'), t('spreed', 'Muted')]
	const videoIndicatorAriaLabels = [t('spreed', 'Disable video'), t('spreed', 'Enable video')]
	const screenSharingAriaLabel = t('spreed', 'Show screen')
	const followingButtonAriaLabel = t('spreed', 'Stop following')

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())
		callViewStore = useCallViewStore()

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
				forceMute: jest.fn(),
			},
			participantName: PARTICIPANT_NAME,
			sharedData: {
				remoteVideoBlocker: {
					isVideoEnabled: jest.fn().mockReturnValue(true),
					setVideoEnabled: jest.fn(),
				},
			},
		}

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = jest.fn().mockReturnValue((token) => conversationProps)
		testStoreConfig.modules.actorStore.getters.getUserId = jest.fn().mockReturnValue(() => USER_ID)
		store = new Store(testStoreConfig)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('unit tests', () => {
		describe('render component', () => {
			test('component renders properly', async () => {
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})
				expect(wrapper.exists()).toBeTruthy()
				expect(wrapper.classes('wrapper')).toBeDefined()
			})

			test('component has class "wrapper--big" for main view', async () => {
				componentProps.isBig = true
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})
				expect(wrapper.exists()).toBeTruthy()
				expect(wrapper.classes('wrapper--big')).toBeDefined()
			})

			test('component renders all indicators by default', async () => {
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators).toHaveLength(3)
			})

			test('component does not render indicators for Screen.vue component', async () => {
				componentProps.isScreen = true
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators).toHaveLength(0)
			})

			test('component does not show indicators after video overlay is off', async () => {
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				componentProps.showVideoOverlay = false
				await wrapper.setProps(cloneDeep(componentProps))

				const indicators = wrapper.findAllComponents(NcButton)
				indicators.wrappers.forEach(indicator => {
					expect(indicator.isVisible()).toBeFalsy()
				})
			})

			test('component does not render anything when used in sidebar', async () => {
				componentProps.isSidebar = true
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const participantName = wrapper.find('.participant-name')
				expect(participantName.exists()).toBeFalsy()

				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators).toHaveLength(0)
			})
		})

		describe('render participant name', () => {
			test('name is shown by default', () => {
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const participantName = wrapper.find('.participant-name')
				expect(participantName.isVisible()).toBeTruthy()
				expect(participantName.text()).toBe(PARTICIPANT_NAME)
			})

			test('name is not shown if all checks are falsy', () => {
				componentProps.showVideoOverlay = false
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const participantName = wrapper.find('.participant-name')
				expect(participantName.isVisible()).toBeFalsy()
			})
		})

		describe('render indicators', () => {
			describe('connection failed indicator', () => {
				test('indicator is not shown by default, other indicators are visible', () => {
					componentProps.model.attributes.raisedHand.state = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const iceFailedIndicator = wrapper.findComponent(AlertCircle)
					expect(iceFailedIndicator.exists()).toBeFalsy()

					const raiseHandIndicator = wrapper.findComponent(HandBackLeft)
					expect(raiseHandIndicator.exists()).toBeTruthy()

					const indicators = wrapper.findAllComponents(NcButton)
					indicators.wrappers.forEach(indicator => {
						expect(indicator.isVisible()).toBeTruthy()
					})
				})

				test('indicator is shown when model prop is true, other indicators are hidden', () => {
					componentProps.model.attributes.raisedHand.state = true
					componentProps.model.attributes.connectionState = ConnectionState.FAILED_NO_RESTART
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const iceFailedIndicator = wrapper.findComponent(AlertCircle)
					expect(iceFailedIndicator.exists()).toBeTruthy()

					const raiseHandIndicator = wrapper.findComponent(HandBackLeft)
					expect(raiseHandIndicator.exists()).toBeFalsy()

					const indicators = wrapper.findAllComponents(NcButton)
					indicators.wrappers.forEach(indicator => {
						expect(indicator.isVisible()).toBeFalsy()
					})
				})
			})

			describe('raise hand indicator', () => {
				test('indicator is not shown by default', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const raiseHandIndicator = wrapper.findComponent(HandBackLeft)
					expect(raiseHandIndicator.exists()).toBeFalsy()
				})

				test('indicator is shown when model prop is true', () => {
					componentProps.model.attributes.raisedHand.state = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const raiseHandIndicator = wrapper.findComponent(HandBackLeft)
					expect(raiseHandIndicator.exists()).toBeTruthy()
				})
			})
		})

		describe('render buttons', () => {
			describe('audio indicator', () => {
				test('button is rendered properly', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.exists()).toBeTruthy()
				})

				test('button is visible for moderators when audio is available', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.isVisible()).toBeTruthy()
				})

				test('button is not rendered for non-moderators when audio is available', () => {
					conversationProps.participantType = PARTICIPANT.TYPE.USER
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.exists()).toBeFalsy()
				})

				test('button is visible for everyone when audio is unavailable', () => {
					conversationProps.participantType = PARTICIPANT.TYPE.USER
					componentProps.model.attributes.audioAvailable = false
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.isVisible()).toBeTruthy()
				})

				test('button is enabled for moderators', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.attributes('disabled')).toBeFalsy()
				})

				test('button is disabled when audio is unavailable', () => {
					componentProps.model.attributes.audioAvailable = false
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})
					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					expect(audioIndicator.attributes('disabled')).toBeTruthy()
				})

				test('method is called after click', async () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						stubs: {
							NcButton,
						},
						propsData: componentProps,
					})

					const audioIndicator = findNcButton(wrapper, audioIndicatorAriaLabels)
					await audioIndicator.trigger('click')

					expect(wrapper.vm.$props.model.forceMute).toHaveBeenCalled()
				})
			})

			describe('video indicator', () => {
				test('button is rendered properly', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})
					const videoIndicator = findNcButton(wrapper, videoIndicatorAriaLabels)
					expect(videoIndicator.exists()).toBeTruthy()
				})

				test('button is visible when video is available', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const videoIndicator = findNcButton(wrapper, videoIndicatorAriaLabels)
					expect(videoIndicator.isVisible()).toBeTruthy()
				})

				test('button is not rendered when video is unavailable', () => {
					componentProps.model.attributes.videoAvailable = false
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const videoIndicator = findNcButton(wrapper, videoIndicatorAriaLabels)
					expect(videoIndicator.exists()).toBeFalsy()
				})

				test('button shows proper icon if video is enabled', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						stubs: {
							NcButton,
						},
						propsData: componentProps,
					})

					const videoOnIcon = wrapper.findComponent(VideoIcon)
					expect(videoOnIcon.exists()).toBeTruthy()
				})

				test('button shows proper icon if video is blocked', () => {
					componentProps.sharedData.remoteVideoBlocker.isVideoEnabled.mockReturnValue(false)
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						stubs: {
							NcButton,
						},
						propsData: componentProps,
					})

					const videoOffIcon = wrapper.findComponent(VideoOff)
					expect(videoOffIcon.exists()).toBeTruthy()
				})

				test('method is called after click', async () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						stubs: {
							NcButton,
						},
						propsData: componentProps,
					})

					const videoIndicator = findNcButton(wrapper, videoIndicatorAriaLabels)
					await videoIndicator.trigger('click')

					expect(wrapper.vm.$props.sharedData.remoteVideoBlocker.setVideoEnabled).toHaveBeenCalled()
					expect(wrapper.vm.$props.sharedData.remoteVideoBlocker.setVideoEnabled).toHaveBeenCalledWith(false)
				})
			})

			describe('screen sharing indicator', () => {
				test('button is rendered properly', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const screenSharingIndicator = findNcButton(wrapper, screenSharingAriaLabel)
					expect(screenSharingIndicator.exists()).toBeTruthy()
				})

				test('button is visible when screen is available', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const screenSharingIndicator = findNcButton(wrapper, screenSharingAriaLabel)
					expect(screenSharingIndicator.isVisible()).toBeTruthy()
				})

				test('button is not rendered when screen is unavailable', () => {
					componentProps.model.attributes.screen = false
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const screenSharingIndicator = findNcButton(wrapper, screenSharingAriaLabel)
					expect(screenSharingIndicator.exists()).toBeFalsy()
				})

				test('component emits peer id after click', async () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						stubs: {
							NcButton,
						},
						propsData: componentProps,
					})

					const screenSharingIndicator = findNcButton(wrapper, screenSharingAriaLabel)
					await screenSharingIndicator.trigger('click')

					expect(emit).toHaveBeenCalledWith('switch-screen-to-id', PEER_ID)
				})
			})

			describe('following button', () => {
				test('button is not rendered for participants by default', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})
					const followingButton = findNcButton(wrapper, followingButtonAriaLabel)
					expect(followingButton.exists()).toBeFalsy()
				})

				test('button is not rendered for main speaker by default', () => {
					componentProps.isBig = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})
					const followingButton = findNcButton(wrapper, followingButtonAriaLabel)
					expect(followingButton.exists()).toBeFalsy()
				})

				test('button is rendered when source is selected', () => {
					callViewStore.setSelectedVideoPeerId(PEER_ID)
					componentProps.isBig = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const followingButton = findNcButton(wrapper, followingButtonAriaLabel)
					expect(followingButton.exists()).toBeTruthy()
				})

				test('method is called after click', async () => {
					callViewStore.setSelectedVideoPeerId(PEER_ID)
					callViewStore.startPresentation(TOKEN)
					expect(callViewStore.selectedVideoPeerId).toBe(PEER_ID)
					expect(callViewStore.presentationStarted).toBeTruthy()

					componentProps.isBig = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						stubs: {
							NcButton,
						},
						propsData: componentProps,
					})

					const followingButton = findNcButton(wrapper, followingButtonAriaLabel)
					await followingButton.trigger('click')

					expect(callViewStore.selectedVideoPeerId).toBe(null)
					expect(callViewStore.presentationStarted).toBeFalsy()
				})
			})
		})
	})
})
