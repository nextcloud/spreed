import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex, { Store } from 'vuex'

import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { emit } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import VideoBottomBar from './VideoBottomBar.vue'

import { CONVERSATION, PARTICIPANT } from '../../../constants.js'
import storeConfig from '../../../store/storeConfig.js'
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
	let testStoreConfig
	let componentProps
	let conversationProps
	let selectedVideoPeerIdMock

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

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

		selectedVideoPeerIdMock = jest.fn().mockReturnValue(() => null)
		testStoreConfig.modules.callViewStore.getters.selectedVideoPeerId = selectedVideoPeerIdMock()
		testStoreConfig.modules.callViewStore.actions.stopPresentation = jest.fn()
		testStoreConfig.modules.callViewStore.actions.selectedVideoPeerId = jest.fn()

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
				expect(wrapper.exists()).toBe(true)
				expect(wrapper.classes()).toContain('wrapper')
			})

			test('component has class "wrapper--big" for main view', async () => {
				componentProps.isBig = true
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})
				expect(wrapper.exists()).toBe(true)
				expect(wrapper.classes()).toContain('wrapper--big')
			})

			test('component renders all indicators by default', async () => {
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators.length).toBe(3)
			})

			test('component does not render indicators for Screen.vue component', async () => {
				componentProps.isScreen = true
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators.length).toBe(0)
			})

			test('component does not render indicators after video overlay is off', async () => {
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				componentProps.showVideoOverlay = false
				await wrapper.setProps(cloneDeep(componentProps))

				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators.length).toBe(0)
			})

			test('component does not render anything when used in sidebar', async () => {
				componentProps.isSidebar = true
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const participantName = wrapper.findComponent('.participant-name')
				expect(participantName.exists()).toBe(false)

				const indicators = wrapper.findAllComponents(NcButton)
				expect(indicators.length).toBe(0)
			})
		})

		describe('render participant name', () => {
			test('name is shown by default', () => {
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const participantName = wrapper.findComponent('.participant-name')
				expect(participantName.isVisible()).toBe(true)
				expect(participantName.text()).toBe(PARTICIPANT_NAME)
			})

			test('name is not shown if all checks are falsy', () => {
				componentProps.showVideoOverlay = false
				const wrapper = shallowMount(VideoBottomBar, {
					localVue,
					store,
					propsData: componentProps,
				})

				const participantName = wrapper.findComponent('.participant-name')
				expect(participantName.isVisible()).toBe(false)
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

					const iceFailedIndicator = wrapper.findComponent('.iceFailedIndicator')
					expect(iceFailedIndicator.exists()).toBe(false)

					const raiseHandIndicator = wrapper.findComponent('.raiseHandIndicator')
					expect(raiseHandIndicator.exists()).toBe(true)

					const indicators = wrapper.findAllComponents(NcButton)
					indicators.wrappers.forEach(indicator => {
						expect(indicator.isVisible()).toBe(true)
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

					const iceFailedIndicator = wrapper.findComponent('.iceFailedIndicator')
					expect(iceFailedIndicator.exists()).toBe(true)

					const raiseHandIndicator = wrapper.findComponent('.raiseHandIndicator')
					expect(raiseHandIndicator.exists()).toBe(false)

					const indicators = wrapper.findAllComponents(NcButton)
					indicators.wrappers.forEach(indicator => {
						expect(indicator.isVisible()).toBe(false)
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

					const raiseHandIndicator = wrapper.findComponent('.raiseHandIndicator')
					expect(raiseHandIndicator.exists()).toBe(false)
				})

				test('indicator is shown when model prop is true', () => {
					componentProps.model.attributes.raisedHand.state = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const raiseHandIndicator = wrapper.findComponent('.raiseHandIndicator')
					expect(raiseHandIndicator.exists()).toBe(true)
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

					const audioIndicator = wrapper.findComponent('.audioIndicator')
					expect(audioIndicator.exists()).toBe(true)
				})

				test('button is visible for moderators when audio is available', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const audioIndicator = wrapper.findComponent('.audioIndicator')
					expect(audioIndicator.isVisible()).toBe(true)
				})

				test('button is invisible for non-moderators when audio is available', () => {
					conversationProps.participantType = PARTICIPANT.TYPE.USER
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const audioIndicator = wrapper.findComponent('.audioIndicator')
					expect(audioIndicator.isVisible()).toBe(false)
				})

				test('button is visible for everyone when audio is unavailable', () => {
					conversationProps.participantType = PARTICIPANT.TYPE.USER
					componentProps.model.attributes.audioAvailable = false
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const audioIndicator = wrapper.findComponent('.audioIndicator')
					expect(audioIndicator.isVisible()).toBe(true)
				})

				test('button is enabled for moderators', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const audioIndicator = wrapper.findComponent('.audioIndicator')
					expect(audioIndicator.attributes()).not.toHaveProperty('disabled')
				})

				test('button is disabled for moderators when audio is unavailable', () => {
					componentProps.model.attributes.audioAvailable = false
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})
					const audioIndicator = wrapper.findComponent('.audioIndicator')
					expect(audioIndicator.attributes()).toHaveProperty('disabled')
				})

				test('button is disabled for non-moderators', () => {
					conversationProps.participantType = PARTICIPANT.TYPE.USER
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})
					const audioIndicator = wrapper.findComponent('.audioIndicator')
					expect(audioIndicator.attributes()).toHaveProperty('disabled')
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

					const audioIndicator = wrapper.findComponent('.audioIndicator')
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

					const videoIndicator = wrapper.findComponent('.videoIndicator')
					expect(videoIndicator.exists()).toBe(true)
				})

				test('button is visible when video is available', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const videoIndicator = wrapper.findComponent('.videoIndicator')
					expect(videoIndicator.isVisible()).toBe(true)
				})

				test('button is invisible when video is unavailable', () => {
					componentProps.model.attributes.videoAvailable = false
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const videoIndicator = wrapper.findComponent('.videoIndicator')
					expect(videoIndicator.isVisible()).toBe(false)
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
					expect(videoOnIcon.exists()).toBe(true)
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
					expect(videoOffIcon.exists()).toBe(true)
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

					const videoIndicator = wrapper.findComponent('.videoIndicator')
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

					const screenSharingIndicator = wrapper.findComponent('.screenSharingIndicator')
					expect(screenSharingIndicator.exists()).toBe(true)
				})

				test('button is visible when screen is available', () => {
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const screenSharingIndicator = wrapper.findComponent('.screenSharingIndicator')
					expect(screenSharingIndicator.isVisible()).toBe(true)
				})

				test('button is invisible when screen is unavailable', () => {
					componentProps.model.attributes.screen = false
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const screenSharingIndicator = wrapper.findComponent('.screenSharingIndicator')
					expect(screenSharingIndicator.isVisible()).toBe(false)
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

					const screenSharingIndicator = wrapper.findComponent('.screenSharingIndicator')
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
					const followingButton = wrapper.findComponent('.following-button')
					expect(followingButton.exists()).toBe(false)
				})

				test('button is not rendered for main speaker by default', () => {
					componentProps.isBig = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})
					const followingButton = wrapper.findComponent('.following-button')
					expect(followingButton.exists()).toBe(false)
				})

				test('button is rendered when source is selected', () => {
					selectedVideoPeerIdMock = jest.fn().mockReturnValue(() => PEER_ID)
					testStoreConfig.modules.callViewStore.getters.selectedVideoPeerId = selectedVideoPeerIdMock()
					store = new Store(testStoreConfig)

					componentProps.isBig = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						propsData: componentProps,
					})

					const followingButton = wrapper.findComponent('.following-button')
					expect(followingButton.exists()).toBe(true)
				})

				test('method is called after click', async () => {
					selectedVideoPeerIdMock = jest.fn().mockReturnValue(() => PEER_ID)
					testStoreConfig.modules.callViewStore.getters.selectedVideoPeerId = selectedVideoPeerIdMock()
					store = new Store(testStoreConfig)

					componentProps.isBig = true
					const wrapper = shallowMount(VideoBottomBar, {
						localVue,
						store,
						stubs: {
							NcButton,
						},
						propsData: componentProps,
					})

					const followingButton = wrapper.findComponent('.following-button')
					await followingButton.trigger('click')

					expect(testStoreConfig.modules.callViewStore.actions.stopPresentation).toHaveBeenCalled()
					expect(testStoreConfig.modules.callViewStore.actions.selectedVideoPeerId).toHaveBeenCalled()
					expect(testStoreConfig.modules.callViewStore.actions.selectedVideoPeerId).toHaveBeenCalledWith(expect.anything(), null)
				})
			})
		})
	})
})
