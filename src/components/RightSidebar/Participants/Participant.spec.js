/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import Microphone from 'vue-material-design-icons/Microphone.vue'
import Phone from 'vue-material-design-icons/Phone.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionText from '@nextcloud/vue/dist/Components/NcActionText.js'

import Participant from './Participant.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'

import { ATTENDEE, PARTICIPANT } from '../../../constants.js'
import storeConfig from '../../../store/storeConfig.js'
import { findNcActionButton } from '../../../test-helpers.js'

describe('Participant.vue', () => {
	let conversation
	let participant
	let store
	let localVue
	let testStoreConfig
	let tooltipMock

	/**
	 * @param {object} wrapper Wrapper where the tooltip is mounted in
	 * @param {HTMLElement} htmlEl Tooltip to find
	 */
	async function getLastTooltipValue(wrapper, htmlEl) {
		tooltipMock.mockClear()
		await wrapper.vm.forceEnableTooltips()

		const filteredCalls = tooltipMock.mock.calls.filter((call) => {
			// only keep calls on wanted node
			return call[0] === htmlEl
		})

		if (filteredCalls.length) {
			return filteredCalls.at(-1)[1].value
		}

		return null
	}

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		tooltipMock = jest.fn()

		participant = {
			displayName: 'Alice',
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			actorId: 'alice-actor-id',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			participantType: PARTICIPANT.TYPE.USER,
			permissions: PARTICIPANT.PERMISSIONS.CALL_START
				| PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
				| PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO,
			attendeeId: 'alice-attendee-id',
			status: '',
			statusIcon: '🌧️',
			statusMessage: 'rainy',
			sessionIds: [
				'session-id-alice',
			],
		}

		conversation = {
			token: 'current-token',
			participantType: PARTICIPANT.TYPE.USER,
		}

		const conversationGetterMock = jest.fn().mockReturnValue(conversation)

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.tokenStore.getters.getToken = () => () => 'current-token'
		testStoreConfig.modules.conversationsStore.getters.conversation = () => conversationGetterMock
		store = new Vuex.Store(testStoreConfig)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	/**
	 * @param {object} participant Participant with optional user status data
	 * @param {boolean} showUserStatus Whether or not the user status should be shown
	 */
	function mountParticipant(participant, showUserStatus = false) {
		return shallowMount(Participant, {
			localVue,
			store,
			props: {
				participant,
				showUserStatus,
			},
			stubs: {
				NcActionButton,
			},
			directives: {
				tooltip: tooltipMock,
			},
			mixins: [{
				// force tooltip display for testing
				methods: {
					forceEnableTooltips() {
						this.isUserNameTooltipVisible = true
						this.isStatusTooltipVisible = true
					},
				},
			}],
		})
	}

	describe('avatar', () => {
		test('renders avatar', () => {
			const wrapper = mountParticipant(participant)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('id')).toBe('alice-actor-id')
			expect(avatarEl.props('disableTooltip')).toBe(true)
			expect(avatarEl.props('disableMenu')).toBe(false)
			expect(avatarEl.props('showUserStatus')).toBe(false)
			expect(avatarEl.props('preloadedUserStatus')).toStrictEqual({
				icon: '🌧️',
				message: 'rainy',
				status: null,
			})
			expect(avatarEl.props('name')).toBe('Alice')
			expect(avatarEl.props('source')).toBe(ATTENDEE.ACTOR_TYPE.USERS)
			expect(avatarEl.props('offline')).toBe(false)
		})

		test('renders avatar with enabled status', () => {
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('showUserStatus')).toBe(true)
		})

		test('renders avatar with guest name when empty', () => {
			participant.displayName = ''
			participant.participantType = PARTICIPANT.TYPE.GUEST
			const wrapper = mountParticipant(participant)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('name')).toBe('Guest')
		})

		test('renders avatar with unknown name when empty', () => {
			participant.displayName = ''
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('name')).toBe('Deleted user')
		})

		test('renders offline avatar when no sessions exist', () => {
			participant.sessionIds = []
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('offline')).toBe(true)
		})

		test('renders avatar from search result', () => {
			participant.label = 'Name from label'
			participant.source = 'source-from-search'
			participant.id = 'id-from-search'
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('id')).toBe('id-from-search')
			expect(avatarEl.props('name')).toBe('Name from label')
			expect(avatarEl.props('source')).toBe('source-from-search')
		})
	})

	describe('user name', () => {
		/**
		 * @param {object} wrapper Wrapper where the tooltip is mounted in
		 */
		async function getUserTooltip(wrapper) {
			const tooltipEl = wrapper.find('.participant-row__user-name').element
			return getLastTooltipValue(wrapper, tooltipEl)
		}

		beforeEach(() => {
			participant.statusIcon = ''
			participant.statusMessage = ''
		})

		test('renders plain user name for regular user', async () => {
			const wrapper = mountParticipant(participant)
			expect(wrapper.text()).toBe('Alice')
			expect(await getUserTooltip(wrapper)).toBe('Alice')
		})

		test('renders guest suffix for guests', async () => {
			participant.participantType = PARTICIPANT.TYPE.GUEST
			const wrapper = mountParticipant(participant)
			expect(wrapper.text()).toStrictEqual(expect.stringMatching(/^Alice\s+\(guest\)$/))
			expect(await getUserTooltip(wrapper)).toBe('Alice (guest)')
		})

		test('renders moderator suffix for moderators', async () => {
			participant.participantType = PARTICIPANT.TYPE.MODERATOR
			const wrapper = mountParticipant(participant)
			expect(wrapper.text()).toStrictEqual(expect.stringMatching(/^Alice\s+\(moderator\)$/))
			expect(await getUserTooltip(wrapper)).toBe('Alice (moderator)')
		})

		test('renders guest moderator suffix for guest moderators', async () => {
			participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
			const wrapper = mountParticipant(participant)
			expect(wrapper.text()).toStrictEqual(expect.stringMatching(/^Alice\s+\(moderator\)\s+\(guest\)$/))
			expect(await getUserTooltip(wrapper)).toBe('Alice (moderator) (guest)')
		})

		test('renders bot suffix for bots', async () => {
			participant.actorType = ATTENDEE.ACTOR_TYPE.USERS
			participant.actorId = ATTENDEE.BRIDGE_BOT_ID
			const wrapper = mountParticipant(participant)
			expect(wrapper.text()).toStrictEqual(expect.stringMatching(/^Alice\s+\(bot\)$/))
			expect(await getUserTooltip(wrapper)).toBe('Alice (bot)')
		})
	})

	describe('user status', () => {
		/**
		 * @param {object} wrapper Wrapper where the tooltip is mounted in
		 */
		async function getStatusTooltip(wrapper) {
			const tooltipEl = wrapper.find('.participant-row__status>span').element
			return getLastTooltipValue(wrapper, tooltipEl)
		}

		test('renders user status', async () => {
			const wrapper = mountParticipant(participant)
			expect(wrapper.find('.participant-row__status').text()).toBe('🌧️ rainy')
			expect(await getStatusTooltip(wrapper)).toBe('🌧️ rainy')
		})

		test('does not render user status when not set', () => {
			participant.statusIcon = ''
			participant.statusMessage = ''
			const wrapper = mountParticipant(participant)
			expect(wrapper.find('.participant-row__status').exists()).toBe(false)
		})

		test('renders dnd status', async () => {
			participant.statusMessage = ''
			participant.status = 'dnd'
			const wrapper = mountParticipant(participant)
			expect(wrapper.find('.participant-row__status').text()).toBe('🌧️ Do not disturb')
			expect(await getStatusTooltip(wrapper)).toBe('🌧️ Do not disturb')
		})

		test('renders away status', async () => {
			participant.statusMessage = ''
			participant.status = 'away'
			const wrapper = mountParticipant(participant)
			expect(wrapper.find('.participant-row__status').text()).toBe('🌧️ Away')
			expect(await getStatusTooltip(wrapper)).toBe('🌧️ Away')
		})
	})

	describe('call icons', () => {
		let getParticipantRaisedHandMock

		/**
		 * @param {object} wrapper Wrapper where the tooltip is mounted in
		 */
		async function getCallIconTooltip(wrapper) {
			const tooltipEl = wrapper.find('.participant-row__callstate-icon').element
			return getLastTooltipValue(wrapper, tooltipEl)
		}

		beforeEach(() => {
			getParticipantRaisedHandMock = jest.fn().mockReturnValue({ state: false })

			testStoreConfig = cloneDeep(storeConfig)
			testStoreConfig.modules.callViewStore.getters.getParticipantRaisedHand = () => getParticipantRaisedHandMock
			store = new Vuex.Store(testStoreConfig)
		})
		test('does not renders call icon when disconnected', () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.DISCONNECTED
			const wrapper = mountParticipant(participant)
			expect(wrapper.findComponent(VideoIcon).exists()).toBe(false)
			expect(wrapper.findComponent(Phone).exists()).toBe(false)
			expect(wrapper.findComponent(Microphone).exists()).toBe(false)
			expect(wrapper.findComponent(HandBackLeft).exists()).toBe(false)
		})
		test('renders video call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO
			const wrapper = mountParticipant(participant)
			expect(wrapper.findComponent(VideoIcon).exists()).toBe(true)
			expect(wrapper.findComponent(Phone).exists()).toBe(false)
			expect(wrapper.findComponent(Microphone).exists()).toBe(false)
			expect(wrapper.findComponent(HandBackLeft).exists()).toBe(false)

			expect(await getCallIconTooltip(wrapper)).toBe('Joined with video')
		})
		test('renders audio call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_AUDIO
			const wrapper = mountParticipant(participant)
			expect(wrapper.findComponent(VideoIcon).exists()).toBe(false)
			expect(wrapper.findComponent(Phone).exists()).toBe(false)
			expect(wrapper.findComponent(Microphone).exists()).toBe(true)
			expect(wrapper.findComponent(HandBackLeft).exists()).toBe(false)

			expect(await getCallIconTooltip(wrapper)).toBe('Joined with audio')
		})
		test('renders phone call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_PHONE
			const wrapper = mountParticipant(participant)
			expect(wrapper.findComponent(VideoIcon).exists()).toBe(false)
			expect(wrapper.findComponent(Phone).exists()).toBe(true)
			expect(wrapper.findComponent(Microphone).exists()).toBe(false)
			expect(wrapper.findComponent(HandBackLeft).exists()).toBe(false)

			expect(await getCallIconTooltip(wrapper)).toBe('Joined via phone')
		})
		test('renders hand raised icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO
			getParticipantRaisedHandMock = jest.fn().mockReturnValue({ state: true })

			const wrapper = mountParticipant(participant)
			expect(wrapper.findComponent(VideoIcon).exists()).toBe(false)
			expect(wrapper.findComponent(Phone).exists()).toBe(false)
			expect(wrapper.findComponent(Microphone).exists()).toBe(false)
			expect(wrapper.findComponent(HandBackLeft).exists()).toBe(true)

			expect(getParticipantRaisedHandMock).toHaveBeenCalledWith(['session-id-alice'])

			expect(await getCallIconTooltip(wrapper)).toBe('Raised their hand')
		})
		test('renders video call icon when joined with multiple', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO | PARTICIPANT.CALL_FLAG.WITH_PHONE
			const wrapper = mountParticipant(participant)
			expect(wrapper.findComponent(VideoIcon).exists()).toBe(true)
			expect(wrapper.findComponent(Phone).exists()).toBe(false)
			expect(wrapper.findComponent(Microphone).exists()).toBe(false)
			expect(wrapper.findComponent(HandBackLeft).exists()).toBe(false)

			expect(await getCallIconTooltip(wrapper)).toBe('Joined with video')
		})
		test('does not render hand raised icon when disconnected', () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.DISCONNECTED
			getParticipantRaisedHandMock = jest.fn().mockReturnValue({ state: true })

			const wrapper = mountParticipant(participant)
			expect(wrapper.findComponent(HandBackLeft).exists()).toBe(false)

			expect(getParticipantRaisedHandMock).not.toHaveBeenCalled()
		})

		test('does not render hand raised icon when searched', () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO
			participant.label = 'searched result'
			getParticipantRaisedHandMock = jest.fn().mockReturnValue({ state: true })

			const wrapper = mountParticipant(participant)
			expect(wrapper.findComponent(HandBackLeft).exists()).toBe(false)

			expect(getParticipantRaisedHandMock).not.toHaveBeenCalled()
		})
	})

	describe('actions', () => {
		describe('demoting participant', () => {
			let demoteFromModeratorAction

			beforeEach(() => {
				demoteFromModeratorAction = jest.fn()

				testStoreConfig.modules.participantsStore.actions.demoteFromModerator = demoteFromModeratorAction
				store = new Vuex.Store(testStoreConfig)
			})

			/**
			 *
			 */
			async function testCanDemote() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Demote from moderator')
				expect(actionButton.exists()).toBe(true)

				await actionButton.find('button').trigger('click')

				expect(demoteFromModeratorAction).toHaveBeenCalledWith(expect.anything(), {
					token: 'current-token',
					attendeeId: 'alice-attendee-id',
				})
			}

			/**
			 *
			 */
			async function testCannotDemote() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Demote to moderator')
				expect(actionButton.exists()).toBe(false)
			}

			test('allows a moderator to demote a moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				await testCanDemote()
			})

			test('allows a moderator to demote a guest moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				await testCanDemote()
			})

			test('allows a guest moderator to demote a moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				await testCanDemote()
			})

			test('allows a guest moderator to demote a guest moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				await testCanDemote()
			})

			test('does not allow to demote an owner', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.OWNER
				await testCannotDemote()
			})

			test('does not allow demoting groups', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.GROUPS
				await testCannotDemote()
			})

			test('does not allow demoting self', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				conversation.sessionId = 'current-session-id'
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.sessionIds = ['current-session-id', 'another-session-id']
				await testCannotDemote()
			})

			test('does not allow demoting self as guest', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				conversation.sessionId = 'current-session-id'
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.sessionIds = ['current-session-id']
				await testCannotDemote()
			})

			test('does not allow a non-moderator to demote', async () => {
				conversation.participantType = PARTICIPANT.TYPE.USER
				await testCannotDemote()
			})
		})
		describe('promoting participant', () => {
			let promoteToModeratorAction

			beforeEach(() => {
				promoteToModeratorAction = jest.fn()

				testStoreConfig.modules.participantsStore.actions.promoteToModerator = promoteToModeratorAction
				store = new Vuex.Store(testStoreConfig)
			})

			/**
			 *
			 */
			async function testCanPromote() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Promote to moderator')
				expect(actionButton.exists()).toBe(true)

				await actionButton.find('button').trigger('click')

				expect(promoteToModeratorAction).toHaveBeenCalledWith(expect.anything(), {
					token: 'current-token',
					attendeeId: 'alice-attendee-id',
				})
			}

			/**
			 *
			 */
			async function testCannotPromote() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Promote to moderator')
				expect(actionButton.exists()).toBe(false)
			}

			test('allows a moderator to promote a user to moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				await testCanPromote()
			})

			test('allows a moderator to promote a self-joined user to moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.USER_SELF_JOINED
				await testCanPromote()
			})

			test('allows a moderator to promote a guest to moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST
				await testCanPromote()
			})

			test('allows a guest moderator to promote a user to moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				await testCanPromote()
			})

			test('allows a guest moderator to promote a guest to moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST
				await testCanPromote()
			})

			test('does not allow to promote a moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				await testCannotPromote()
			})

			test('does not allow to promote a guest moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				await testCannotPromote()
			})

			test('does not allow promoting groups', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.GROUPS
				await testCannotPromote()
			})

			test('does not allow promoting the bridge bot', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.USERS
				participant.actorId = ATTENDEE.BRIDGE_BOT_ID
				await testCannotPromote()
			})

			test('does not allow a non-moderator to promote', async () => {
				conversation.participantType = PARTICIPANT.TYPE.USER
				await testCannotPromote()
			})
		})
		describe('resending invitations', () => {
			let resendInvitationsAction

			beforeEach(() => {
				resendInvitationsAction = jest.fn()

				testStoreConfig.modules.participantsStore.actions.resendInvitations = resendInvitationsAction
				store = new Vuex.Store(testStoreConfig)
			})

			test('allows moderators to resend invitations for email participants', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.EMAILS
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Resend invitation')
				expect(actionButton.exists()).toBe(true)

				await actionButton.find('button').trigger('click')

				expect(resendInvitationsAction).toHaveBeenCalledWith(expect.anything(), {
					token: 'current-token',
					attendeeId: 'alice-attendee-id',
				})
			})

			test('does not allow non-moderators to resend invitations', async () => {
				participant.actorType = ATTENDEE.ACTOR_TYPE.EMAILS
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Resend invitation')
				expect(actionButton.exists()).toBe(false)
			})

			test('does not display resend invitations action when not an email actor', async () => {
				participant.actorType = ATTENDEE.ACTOR_TYPE.USERS
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Resend invitation')
				expect(actionButton.exists()).toBe(false)
			})
		})
		describe('removing participant', () => {
			let removeAction

			beforeEach(() => {
				removeAction = jest.fn()

				testStoreConfig.modules.participantsStore.actions.removeParticipant = removeAction
				store = new Vuex.Store(testStoreConfig)
			})

			/**
			 * @param {string} buttonText Label of the remove action to find
			 */
			async function testCanRemove(buttonText = 'Remove participant') {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, buttonText)
				expect(actionButton.exists()).toBe(true)

				await actionButton.find('button').trigger('click')

				expect(removeAction).toHaveBeenCalledWith(expect.anything(), {
					token: 'current-token',
					attendeeId: 'alice-attendee-id',
				})
			}

			/**
			 *
			 */
			async function testCannotRemove() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Remove participant')
				expect(actionButton.exists()).toBe(false)
			}

			test('allows a moderator to remove a moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				await testCanRemove()
			})

			test('allows a moderator to remove a guest moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				await testCanRemove()
			})

			test('allows a guest moderator to remove a moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				await testCanRemove()
			})

			test('allows a guest moderator to remove a guest moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				await testCanRemove()
			})

			test('allows a moderator to remove groups', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.GROUPS
				await testCanRemove('Remove group and members')
			})

			test('does not allow to remove an owner', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.OWNER
				await testCannotRemove()
			})

			test('does not allow removing self', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				conversation.sessionId = 'current-session-id'
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.sessionIds = ['current-session-id']
				await testCannotRemove()
			})

			test('does not allow removing self as guest', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				conversation.sessionId = 'current-session-id'
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.sessionIds = ['current-session-id']
				await testCannotRemove()
			})

			test('does not allow a non-moderator to remove', async () => {
				conversation.participantType = PARTICIPANT.TYPE.USER
				await testCannotRemove()
			})
		})
		describe('dial-in PIN', () => {
			/**
			 *
			 */
			function testPinVisible() {
				const wrapper = mountParticipant(participant)
				let actionTexts = wrapper.findAllComponents(NcActionText)
				actionTexts = actionTexts.filter((actionText) => {
					return actionText.props('name').includes('PIN')
				})

				expect(actionTexts.exists()).toBe(true)
				expect(actionTexts.at(0).text()).toBe('123 456 78')
			}

			test('allows moderators to see dial-in PIN when available', () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.attendeePin = '12345678'
				testPinVisible()
			})

			test('allows guest moderators to see dial-in PIN when available', () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.attendeePin = '12345678'
				testPinVisible()
			})

			test('does not allow non-moderators to see dial-in PIN', () => {
				conversation.participantType = PARTICIPANT.TYPE.USER
				participant.attendeePin = '12345678'
				const wrapper = mountParticipant(participant)
				let actionTexts = wrapper.findAllComponents(NcActionText)
				actionTexts = actionTexts.filter((actionText) => {
					return actionText.props('title').includes('PIN')
				})

				expect(actionTexts.exists()).toBe(false)
			})

			test('does not show PIN field when not set', () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.attendeePin = ''
				const wrapper = mountParticipant(participant)
				let actionTexts = wrapper.findAllComponents(NcActionText)
				actionTexts = actionTexts.filter((actionText) => {
					return actionText.props('title').includes('PIN')
				})

				expect(actionTexts.exists()).toBe(false)
			})
		})
	})

	describe('as search result', () => {
		beforeEach(() => {
			participant.label = 'Alice Search'
			participant.source = 'users'
		})

		test('does not show actions for search results', () => {
			const wrapper = mountParticipant(participant)

			// no actions
			expect(wrapper.findAllComponents(NcActionButton).exists()).toBe(false)
		})

		test('triggers event when clicking', async () => {
			const wrapper = mountParticipant(participant)
			await wrapper.find('li').trigger('click')

			expect(wrapper.emitted().clickParticipant).toStrictEqual([[participant]])
		})

		test('does not trigger click event when not a search result', async () => {
			delete participant.label
			delete participant.source
			const wrapper = mountParticipant(participant)

			await wrapper.find('li').trigger('click')

			expect(wrapper.emitted().clickParticipant).not.toBeDefined()
		})
	})

})
