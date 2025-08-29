/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, test, vi } from 'vitest'
import { createStore } from 'vuex'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import IconHandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import IconPhoneDialOutline from 'vue-material-design-icons/PhoneDialOutline.vue'
import IconVideoOutline from 'vue-material-design-icons/VideoOutline.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import Participant from './Participant.vue'
import router from '../../../__mocks__/router.js'
import { ATTENDEE, PARTICIPANT, WEBINAR } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useActorStore } from '../../../stores/actor.ts'
import { useTokenStore } from '../../../stores/token.ts'
import { findNcActionButton, findNcActionText, findNcButton } from '../../../test-helpers.js'

describe('Participant.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let conversation
	let participant
	let store
	let testStoreConfig

	let actorStore
	let tokenStore

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()
		tokenStore = useTokenStore()

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
			token: TOKEN,
			participantType: PARTICIPANT.TYPE.USER,
			lobbyState: WEBINAR.LOBBY.NONE,
		}

		actorStore.actorId = 'user-actor-id'
		actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS
		tokenStore.token = TOKEN

		const conversationGetterMock = vi.fn().mockReturnValue(conversation)

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = () => conversationGetterMock
		store = createStore(testStoreConfig)

		router.push({ name: 'conversation', params: { token: TOKEN } })
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	const ComponentStub = {
		template: '<div><slot /></div>',
	}

	/**
	 * @param {object} participant Participant with optional user status data
	 * @param {boolean} showUserStatus Whether or not the user status should be shown
	 */
	function mountParticipant(participant, showUserStatus = false) {
		return mount(Participant, {
			global: {
				plugins: [router, store],
				stubs: {
					NcModal: ComponentStub,
					NcPopover: ComponentStub,
				},
			},
			props: {
				participant,
				showUserStatus,
			},
		})
	}

	describe('avatar', () => {
		test('renders avatar', () => {
			const wrapper = mountParticipant(participant)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBeTruthy()

			expect(avatarEl.props('id')).toBe('alice-actor-id')
			expect(avatarEl.props('disableTooltip')).toBeTruthy()
			expect(avatarEl.props('disableMenu')).toBeFalsy()
			expect(avatarEl.props('showUserStatus')).toBeFalsy()
			expect(avatarEl.props('preloadedUserStatus')).toStrictEqual({
				icon: '🌧️',
				message: 'rainy',
				status: null,
			})
			expect(avatarEl.props('name')).toBe('Alice')
			expect(avatarEl.props('source')).toBe(ATTENDEE.ACTOR_TYPE.USERS)
			expect(avatarEl.props('offline')).toBeFalsy()
		})

		test('renders avatar with enabled status', () => {
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBeTruthy()

			expect(avatarEl.props('showUserStatus')).toBeTruthy()
		})

		test('renders avatar with guest name when empty', () => {
			participant.displayName = ''
			participant.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
			const wrapper = mountParticipant(participant)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBeTruthy()

			expect(avatarEl.props('name')).toBe('')
		})

		test('renders avatar with unknown name when empty', () => {
			participant.displayName = ''
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBeTruthy()

			expect(avatarEl.props('name')).toBe('')
		})

		test('renders offline avatar when no sessions exist', () => {
			participant.sessionIds = []
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBeTruthy()

			expect(avatarEl.props('offline')).toBeTruthy()
		})
	})

	describe('user name', () => {
		/**
		 * Check which text is currently rendered as a name
		 * (text in user badges has a padding to separate words visually)
		 * @param {object} participant participant object
		 * @param {RegExp} regexp regex pattern which expected to be rendered
		 */
		function checkUserNameRendered(participant, regexp) {
			const wrapper = mountParticipant(participant)
			const username = wrapper.find('.participant__user')
			expect(username.exists()).toBeTruthy()
			expect(username.text()).toMatch(regexp)
		}

		const testCases = [
			['Alice', 'alice', ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.USER, 'Alice'],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST, 'Alice(guest)'],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.EMAILS, PARTICIPANT.TYPE.GUEST, 'Alice(guest)'],
			['', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST, 'Guest(guest)'],
			['Alice', 'alice', ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.MODERATOR, 'Alice(moderator)'],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST_MODERATOR, 'Alice(moderator)(guest)'],
			['Bot', ATTENDEE.BRIDGE_BOT_ID, ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.USER, 'Bot(bot)'],
		]

		const testLobbyCases = [
			['Alice', 'alice', ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.USER, 'Alice(in the lobby)'],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST, 'Alice(guest)(in the lobby)'],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.EMAILS, PARTICIPANT.TYPE.GUEST, 'Alice(guest)(in the lobby)'],
			['', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST, 'Guest(guest)(in the lobby)'],
			['Alice', 'alice', ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.MODERATOR, 'Alice(moderator)'],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST_MODERATOR, 'Alice(moderator)(guest)'],
		]

		it.each(testCases)(
			'renders name and badges for participant \'%s\' - \'%s\' - \'%s\' - \'%d\'',
			(displayName, actorId, actorType, participantType, regexp) => {
				checkUserNameRendered({
					...participant,
					actorId,
					actorType,
					participantType,
					displayName,
				}, regexp)
			},
		)

		it.each(testLobbyCases)(
			'renders name and badges for participant \'%s\' - \'%s\' - \'%s\' - \'%d\' with lobby enabled',
			(displayName, actorId, actorType, participantType, regexp) => {
				conversation.lobbyState = WEBINAR.LOBBY.NON_MODERATORS
				checkUserNameRendered({
					...participant,
					actorId,
					actorType,
					participantType,
					displayName,
				}, regexp)
			},
		)
	})

	describe('user status', () => {
		/**
		 * Check which status is currently rendered
		 * @param {object} participant participant object
		 * @param {string} [status] status which expected to be rendered
		 */
		async function checkUserSubnameRendered(participant, status) {
			const wrapper = mountParticipant(participant)
			await flushPromises()
			const userSubname = wrapper.find('.participant__status')
			if (status) {
				expect(userSubname.exists()).toBeTruthy()
				expect(userSubname.text()).toBe(status)
			} else {
				expect(userSubname.exists()).toBeFalsy()
			}
		}

		const testCases = [
			['online', '', '', undefined],
			['online', '🌧️', 'Rainy', '🌧️ Rainy'],
			['dnd', '🌧️', 'Rainy', '🌧️ Rainy'],
			['dnd', '🌧️', '', '🌧️ Do not disturb'],
			['away', '🌧️', '', '🌧️ Away'],
		]

		it.each(testCases)(
			'renders status for participant \'%s\', \'%s\', \'%s\' - \'%s\'',
			(status, statusIcon, statusMessage, result) => {
				checkUserSubnameRendered({
					...participant,
					status,
					statusIcon,
					statusMessage,
				}, result)
			},
		)

		it('renders email as status for email guest', async () => {
			participant.actorType = ATTENDEE.ACTOR_TYPE.EMAILS
			participant.participantType = PARTICIPANT.TYPE.GUEST
			participant.invitedActorId = 'test@mail.com'
			await checkUserSubnameRendered(participant, 'test@mail.com')
		})
	})

	describe('call icons', () => {
		let getParticipantRaisedHandMock
		const components = [IconVideoOutline, IconPhoneDialOutline, IconMicrophoneOutline, IconHandBackLeft]

		/**
		 * Check which icons are currently rendered
		 * @param {object} participant participant object
		 * @param {object} icon icon which expected to be rendered
		 */
		function checkStateIconsRendered(participant, icon) {
			const wrapper = mountParticipant(participant)
			if (icon) {
				expect(wrapper.findComponent(icon).exists()).toBeTruthy()
			} else {
				components.forEach((component) => {
					expect(wrapper.findComponent(component).exists()).toBeFalsy()
				})
			}
		}

		beforeEach(() => {
			getParticipantRaisedHandMock = vi.fn().mockReturnValue({ state: false })

			testStoreConfig = cloneDeep(storeConfig)
			testStoreConfig.modules.participantsStore.getters.getParticipantRaisedHand = () => getParticipantRaisedHandMock
			store = createStore(testStoreConfig)
		})

		test('does not renders call icon and hand raised icon when disconnected', () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.DISCONNECTED
			getParticipantRaisedHandMock = vi.fn().mockReturnValue({ state: true })

			checkStateIconsRendered(participant, null)
			expect(getParticipantRaisedHandMock).not.toHaveBeenCalled()
		})
		test('renders video call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO
			checkStateIconsRendered(participant, IconVideoOutline)
		})
		test('renders audio call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_AUDIO
			checkStateIconsRendered(participant, IconMicrophoneOutline)
		})
		test('renders phone call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_PHONE
			checkStateIconsRendered(participant, IconPhoneDialOutline)
		})
		test('renders hand raised icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO
			getParticipantRaisedHandMock = vi.fn().mockReturnValue({ state: true })

			checkStateIconsRendered(participant, IconHandBackLeft)
			expect(getParticipantRaisedHandMock).toHaveBeenCalledWith(['session-id-alice'])
		})
		test('renders video call icon when joined with multiple', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO | PARTICIPANT.CALL_FLAG.WITH_PHONE
			checkStateIconsRendered(participant, IconVideoOutline)
		})
	})

	describe('actions', () => {
		describe('demoting participant', () => {
			let demoteFromModeratorAction

			beforeEach(() => {
				demoteFromModeratorAction = vi.fn()

				testStoreConfig.modules.participantsStore.actions.demoteFromModerator = demoteFromModeratorAction
				store = createStore(testStoreConfig)
			})

			/**
			 *
			 */
			async function testCanDemote() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Demote from moderator')
				expect(actionButton.exists()).toBeTruthy()

				await actionButton.find('button').trigger('click')

				expect(demoteFromModeratorAction).toHaveBeenCalledWith(expect.anything(), {
					token: TOKEN,
					attendeeId: 'alice-attendee-id',
				})
			}

			/**
			 *
			 */
			async function testCannotDemote() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Demote to moderator')
				expect(actionButton.exists()).toBeFalsy()
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
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorId = 'user-actor-id'
				participant.actorType = ATTENDEE.ACTOR_TYPE.USERS
				await testCannotDemote()
			})

			test('does not allow demoting self as guest', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.actorId = 'user-actor-id'
				participant.actorType = ATTENDEE.ACTOR_TYPE.USERS
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
				promoteToModeratorAction = vi.fn()

				testStoreConfig.modules.participantsStore.actions.promoteToModerator = promoteToModeratorAction
				store = createStore(testStoreConfig)
			})

			/**
			 *
			 */
			async function testCanPromote() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Promote to moderator')
				expect(actionButton.exists()).toBeTruthy()

				await actionButton.find('button').trigger('click')

				expect(promoteToModeratorAction).toHaveBeenCalledWith(expect.anything(), {
					token: TOKEN,
					attendeeId: 'alice-attendee-id',
				})
			}

			/**
			 *
			 */
			async function testCannotPromote() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Promote to moderator')
				expect(actionButton.exists()).toBeFalsy()
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
				resendInvitationsAction = vi.fn()

				testStoreConfig.modules.participantsStore.actions.resendInvitations = resendInvitationsAction
				store = createStore(testStoreConfig)
			})

			test('allows moderators to resend invitations for email participants', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.EMAILS
				participant.invitedActorId = 'alice@mail.com'
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Resend invitation')
				expect(actionButton.exists()).toBeTruthy()

				await actionButton.find('button').trigger('click')

				expect(resendInvitationsAction).toHaveBeenCalledWith(expect.anything(), {
					token: TOKEN,
					attendeeId: 'alice-attendee-id',
					actorId: 'alice@mail.com',
				})
			})

			test('does not allow non-moderators to resend invitations', async () => {
				participant.actorType = ATTENDEE.ACTOR_TYPE.EMAILS
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Resend invitation')
				expect(actionButton.exists()).toBeFalsy()
			})

			test('does not display resend invitations action when not an email actor', async () => {
				participant.actorType = ATTENDEE.ACTOR_TYPE.USERS
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Resend invitation')
				expect(actionButton.exists()).toBeFalsy()
			})
		})
		describe('removing participant', () => {
			let removeAction

			beforeEach(() => {
				removeAction = vi.fn()

				testStoreConfig.modules.participantsStore.actions.removeParticipant = removeAction
				store = createStore(testStoreConfig)
			})

			/**
			 * @param {string} buttonText Label of the remove action to find
			 */
			async function testCanRemove(buttonText = 'Remove participant') {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, buttonText)
				expect(actionButton.exists()).toBeTruthy()

				await actionButton.find('button').trigger('click')

				const dialog = wrapper.findComponent(NcDialog)
				expect(dialog.exists()).toBeTruthy()

				const button = findNcButton(dialog, 'Remove')
				expect(button.exists()).toBeTruthy()
				await button.find('button').trigger('click')

				expect(removeAction).toHaveBeenCalledWith(expect.anything(), {
					token: TOKEN,
					attendeeId: 'alice-attendee-id',
					banParticipant: false,
					internalNote: '',
				})
			}

			/**
			 *
			 */
			async function testCannotRemove() {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Remove participant')
				expect(actionButton.exists()).toBeFalsy()
			}

			/**
			 * @param {string} buttonText Label of the remove action to find
			 * @param {string} internalNote text of provided note
			 */
			async function testCanBan(buttonText = 'Remove participant', internalNote = 'test note') {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, buttonText)
				expect(actionButton.exists()).toBeTruthy()

				await actionButton.find('button').trigger('click')

				const dialog = wrapper.findComponent(NcDialog)
				expect(dialog.exists()).toBeTruthy()

				const checkbox = dialog.findComponent(NcCheckboxRadioSwitch)
				await checkbox.find('input').trigger('change')

				const textarea = dialog.findComponent(NcTextArea)
				expect(textarea.exists()).toBeTruthy()
				textarea.find('textarea').setValue(internalNote)
				await textarea.find('textarea').trigger('change')

				const button = findNcButton(dialog, 'Remove')
				await button.find('button').trigger('click')

				expect(removeAction).toHaveBeenCalledWith(expect.anything(), {
					token: TOKEN,
					attendeeId: 'alice-attendee-id',
					banParticipant: true,
					internalNote,
				})
			}

			/**
			 * @param {string} buttonText Label of the remove action to find
			 */
			async function testCannotBan(buttonText = 'Remove participant') {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, buttonText)
				expect(actionButton.exists()).toBeTruthy()

				await actionButton.find('button').trigger('click')

				const dialog = wrapper.findComponent(NcDialog)
				expect(dialog.exists()).toBeTruthy()

				const checkbox = dialog.findComponent(NcCheckboxRadioSwitch)
				expect(checkbox.exists()).toBeFalsy()
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
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorId = 'user-actor-id'
				participant.actorType = ATTENDEE.ACTOR_TYPE.USERS
				await testCannotRemove()
			})

			test('does not allow removing self as guest', async () => {
				conversation.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST_MODERATOR
				participant.actorId = 'user-actor-id'
				participant.actorType = ATTENDEE.ACTOR_TYPE.USERS
				await testCannotRemove()
			})

			test('does not allow a non-moderator to remove', async () => {
				conversation.participantType = PARTICIPANT.TYPE.USER
				await testCannotRemove()
			})

			test('allows a moderator to ban a user', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.USER
				await testCanBan()
			})

			test('doesn not allow a moderator to ban a federated user', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
				participant.participantType = PARTICIPANT.TYPE.USER
				await testCannotBan()
			})

			test('allows a moderator to ban a guest', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.GUEST
				await testCanBan()
			})

			test('does not allow a moderator to ban a moderator', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.participantType = PARTICIPANT.TYPE.MODERATOR
				await testCannotBan()
			})

			test('does not allow a moderator to ban a group', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.GROUPS
				await testCannotBan('Remove group and members')
			})
		})
		describe('dial-in PIN', () => {
			/**
			 *
			 */
			function testPinVisible() {
				const wrapper = mountParticipant(participant)
				const actionText = findNcActionText(wrapper, 'Dial-in PIN')
				expect(actionText.exists()).toBeTruthy()
				expect(actionText.text()).toContain('123 456 78')
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
				const actionText = findNcActionText(wrapper, 'Dial-in PIN')
				expect(actionText.exists()).toBeFalsy()
			})

			test('does not show PIN field when not set', () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.attendeePin = ''
				const wrapper = mountParticipant(participant)
				const actionText = findNcActionText(wrapper, 'Dial-in PIN')
				expect(actionText.exists()).toBeFalsy()
			})
		})
	})
})
