/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { createStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionText from '@nextcloud/vue/components/NcActionText'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import Microphone from 'vue-material-design-icons/Microphone.vue'
import Phone from 'vue-material-design-icons/Phone.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import Participant from './Participant.vue'
import { ATTENDEE, PARTICIPANT, WEBINAR } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useActorStore } from '../../../stores/actor.ts'
import { useTokenStore } from '../../../stores/token.ts'
import { findNcActionButton, findNcButton } from '../../../test-helpers.js'

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

		const conversationGetterMock = jest.fn().mockReturnValue(conversation)

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = () => conversationGetterMock
		store = createStore(testStoreConfig)
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
			global: {
				plugins: [store],
				stubs: {
					NcActionButton,
					NcButton,
					NcCheckboxRadioSwitch,
					NcDialog,
					NcInputField,
					NcListItem,
					NcTextArea,
				},
			},
			props: {
				participant,
				showUserStatus,
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
			participant.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
			const wrapper = mountParticipant(participant)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('name')).toBe('')
		})

		test('renders avatar with unknown name when empty', () => {
			participant.displayName = ''
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('name')).toBe('')
		})

		test('renders offline avatar when no sessions exist', () => {
			participant.sessionIds = []
			const wrapper = mountParticipant(participant, true)
			const avatarEl = wrapper.findComponent(AvatarWrapper)
			expect(avatarEl.exists()).toBe(true)

			expect(avatarEl.props('offline')).toBe(true)
		})
	})

	describe('user name', () => {
		/**
		 * Check which text is currently rendered as a name
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
			['Alice', 'alice', ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.USER, /^Alice$/],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST, /^Alice\s+\(guest\)$/],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.EMAILS, PARTICIPANT.TYPE.GUEST, /^Alice\s+\(guest\)$/],
			['', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST, /^Guest\s+\(guest\)$/],
			['Alice', 'alice', ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.MODERATOR, /^Alice\s+\(moderator\)$/],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST_MODERATOR, /^Alice\s+\(moderator\)\s+\(guest\)$/],
			['Bot', ATTENDEE.BRIDGE_BOT_ID, ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.USER, /^Bot\s+\(bot\)$/],
		]

		const testLobbyCases = [
			['Alice', 'alice', ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.USER, /^Alice\s+\(in the lobby\)$/],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST, /^Alice\s+\(guest\)\s+\(in the lobby\)$/],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.EMAILS, PARTICIPANT.TYPE.GUEST, /^Alice\s+\(guest\)\s+\(in the lobby\)$/],
			['', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST, /^Guest\s+\(guest\)\s+\(in the lobby\)$/],
			['Alice', 'alice', ATTENDEE.ACTOR_TYPE.USERS, PARTICIPANT.TYPE.MODERATOR, /^Alice\s+\(moderator\)$/],
			['Alice', 'guest-id', ATTENDEE.ACTOR_TYPE.GUESTS, PARTICIPANT.TYPE.GUEST_MODERATOR, /^Alice\s+\(moderator\)\s+\(guest\)$/],
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
		const components = [VideoIcon, Phone, Microphone, HandBackLeft]

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
			getParticipantRaisedHandMock = jest.fn().mockReturnValue({ state: false })

			testStoreConfig = cloneDeep(storeConfig)
			testStoreConfig.modules.participantsStore.getters.getParticipantRaisedHand = () => getParticipantRaisedHandMock
			store = createStore(testStoreConfig)
		})

		test('does not renders call icon and hand raised icon when disconnected', () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.DISCONNECTED
			getParticipantRaisedHandMock = jest.fn().mockReturnValue({ state: true })

			checkStateIconsRendered(participant, null)
			expect(getParticipantRaisedHandMock).not.toHaveBeenCalled()
		})
		test('renders video call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO
			checkStateIconsRendered(participant, VideoIcon)
		})
		test('renders audio call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_AUDIO
			checkStateIconsRendered(participant, Microphone)
		})
		test('renders phone call icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_PHONE
			checkStateIconsRendered(participant, Phone)
		})
		test('renders hand raised icon', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO
			getParticipantRaisedHandMock = jest.fn().mockReturnValue({ state: true })

			checkStateIconsRendered(participant, HandBackLeft)
			expect(getParticipantRaisedHandMock).toHaveBeenCalledWith(['session-id-alice'])
		})
		test('renders video call icon when joined with multiple', async () => {
			participant.inCall = PARTICIPANT.CALL_FLAG.WITH_VIDEO | PARTICIPANT.CALL_FLAG.WITH_PHONE
			checkStateIconsRendered(participant, VideoIcon)
		})
	})

	describe('actions', () => {
		describe('demoting participant', () => {
			let demoteFromModeratorAction

			beforeEach(() => {
				demoteFromModeratorAction = jest.fn()

				testStoreConfig.modules.participantsStore.actions.demoteFromModerator = demoteFromModeratorAction
				store = createStore(testStoreConfig)
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
				promoteToModeratorAction = jest.fn()

				testStoreConfig.modules.participantsStore.actions.promoteToModerator = promoteToModeratorAction
				store = createStore(testStoreConfig)
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
				store = createStore(testStoreConfig)
			})

			test('allows moderators to resend invitations for email participants', async () => {
				conversation.participantType = PARTICIPANT.TYPE.MODERATOR
				participant.actorType = ATTENDEE.ACTOR_TYPE.EMAILS
				participant.invitedActorId = 'alice@mail.com'
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, 'Resend invitation')
				expect(actionButton.exists()).toBe(true)

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
				store = createStore(testStoreConfig)
			})

			/**
			 * @param {string} buttonText Label of the remove action to find
			 */
			async function testCanRemove(buttonText = 'Remove participant') {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, buttonText)
				expect(actionButton.exists()).toBe(true)

				await actionButton.find('button').trigger('click')

				const dialog = wrapper.findComponent(NcDialog)
				expect(dialog.exists()).toBeTruthy()

				const button = findNcButton(dialog, 'Remove')
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
				expect(actionButton.exists()).toBe(false)
			}

			/**
			 * @param {string} buttonText Label of the remove action to find
			 * @param {string} internalNote text of provided note
			 */
			async function testCanBan(buttonText = 'Remove participant', internalNote = 'test note') {
				const wrapper = mountParticipant(participant)
				const actionButton = findNcActionButton(wrapper, buttonText)
				expect(actionButton.exists()).toBe(true)

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
				expect(actionButton.exists()).toBe(true)

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
})
