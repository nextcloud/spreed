<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { MatrixDirectoryRoom, MatrixDirectoryUser } from '../../types/index.ts'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createMatrixRoom, joinMatrixRoom, searchMatrixRooms, searchMatrixUsers } from '../../services/matrixService.ts'

const emit = defineEmits<{
	(event: 'close'): void
}>()

const ERRORS: Record<string, string> = {
	account: t('spreed', 'Link a Matrix account in the Talk settings first'),
	invite: t('spreed', 'Enter Matrix user IDs like @alice:example.org'),
	name: t('spreed', 'Enter a name for the room'),
	reference: t('spreed', 'Enter a room address like #room:example.org'),
	room: t('spreed', 'The Matrix room does not exist or can not be joined'),
}
const UNREACHABLE = t('spreed', 'The Matrix homeserver could not be reached')
const RATE_LIMITED = t('spreed', 'Too many searches, try again in a moment')
const ROOMS_PER_PAGE = 20
// Distance in pixels from the end of the list at which the next page is loaded
const LOAD_MORE_THRESHOLD = 100

const USER_ID = /^@[^:\s]+:\S+$/
const ROOM_REFERENCE = /^(?:[#!][^:\s]+:\S+|https:\/\/matrix\.to\/#\/\S+)$/

const router = useRouter()

const mode = ref<'room' | 'direct' | 'join'>('room')
const name = ref('')
const topic = ref('')
const invitees = ref<string[]>([])
const loading = ref(false)

const userSearch = ref('')
const users = ref<MatrixDirectoryUser[]>([])
const usersSearched = ref(false)
const usersLimited = ref(false)
const usersLoading = ref(false)
const usersError = ref('')
let usersAbort: AbortController | null = null

const roomSearch = ref('')
const rooms = ref<MatrixDirectoryRoom[]>([])
// Search term of the shown rooms, the next page has to use the same one
const roomsTerm = ref('')
const nextBatch = ref<string | null>(null)
const totalRooms = ref<number | null>(null)
const roomsSearched = ref(false)
const roomsLoading = ref(false)
// Whether the running request loads the next page, not a new search
const roomsPaging = ref(false)
const roomsError = ref('')
let roomsAbort: AbortController | null = null

const typedUserId = computed(() => {
	const value = userSearch.value.trim()
	return USER_ID.test(value) && !users.value.some((user) => user.mxid === value) ? value : null
})

const typedReference = computed(() => {
	const value = roomSearch.value.trim()
	return ROOM_REFERENCE.test(value) ? value : null
})

const userActionLabel = computed(() => mode.value === 'direct' ? t('spreed', 'Start chat') : t('spreed', 'Add'))

/**
 * Message for a failed request
 *
 * @param error The error of the request
 */
function errorMessage(error: unknown): string {
	// @ts-expect-error Vue: Object is of type unknown
	if (error?.response?.status === 429) {
		// Rate limited by Nextcloud or by the homeserver
		return RATE_LIMITED
	}
	// @ts-expect-error Vue: Object is of type unknown
	const reason = error?.response?.data?.ocs?.data?.error
	return ERRORS[reason] ?? UNREACHABLE
}

/**
 * Search the user directory of the homeserver for the current search term
 */
async function searchUsers() {
	usersAbort?.abort()
	const term = userSearch.value.trim()
	if (term === '') {
		users.value = []
		usersSearched.value = false
		usersLimited.value = false
		usersError.value = ''
		usersLoading.value = false
		return
	}

	const controller = new AbortController()
	usersAbort = controller
	usersLoading.value = true
	try {
		const response = await searchMatrixUsers({ search: term, limit: 10 }, { signal: controller.signal })
		users.value = response.data.ocs.data.users
		usersLimited.value = response.data.ocs.data.limited
		usersError.value = ''
	} catch (error) {
		if (controller.signal.aborted) {
			return
		}
		users.value = []
		usersLimited.value = false
		usersError.value = errorMessage(error)
	}
	usersSearched.value = true
	usersLoading.value = false
}

/**
 * Search the room directory of the homeserver, or load its next page
 *
 * @param more Whether to load the next page of the shown rooms
 */
async function searchRooms(more = false) {
	if (more && (roomsLoading.value || !nextBatch.value)) {
		return
	}
	roomsAbort?.abort()
	if (typedReference.value) {
		rooms.value = []
		nextBatch.value = null
		totalRooms.value = null
		roomsSearched.value = false
		roomsError.value = ''
		roomsLoading.value = false
		return
	}

	const term = more ? roomsTerm.value : roomSearch.value.trim()
	const controller = new AbortController()
	roomsAbort = controller
	roomsLoading.value = true
	roomsPaging.value = more
	try {
		const response = await searchMatrixRooms({
			search: term,
			limit: ROOMS_PER_PAGE,
			since: more && nextBatch.value ? nextBatch.value : '',
		}, { signal: controller.signal })
		const data = response.data.ocs.data
		if (more) {
			// Member counts can change between pages, so a room can come again
			const shown = new Set(rooms.value.map((room) => room.roomId))
			rooms.value = [...rooms.value, ...data.rooms.filter((room) => !shown.has(room.roomId))]
		} else {
			rooms.value = data.rooms
		}
		roomsTerm.value = term
		nextBatch.value = data.nextBatch
		totalRooms.value = data.total
		roomsError.value = ''
	} catch (error) {
		if (controller.signal.aborted) {
			return
		}
		if (!more) {
			rooms.value = []
			nextBatch.value = null
			totalRooms.value = null
		}
		roomsError.value = errorMessage(error)
	}
	roomsSearched.value = true
	roomsLoading.value = false
}

/**
 * Load the next page when the list is scrolled to its end
 *
 * @param event The scroll event of the list
 */
function onRoomsScroll(event: Event) {
	const list = event.target as HTMLElement
	// After an error only the button loads again, so scrolling does not repeat a failing request
	if (roomsError.value === '' && list.scrollHeight - list.scrollTop - list.clientHeight < LOAD_MORE_THRESHOLD) {
		searchRooms(true)
	}
}

const roomsSummary = computed(() => {
	const shown = rooms.value.length
	if (shown === 0) {
		return ''
	}
	if (nextBatch.value === null) {
		return n('spreed', '%n room', '%n rooms', shown)
	}
	// The estimate of the homeserver is for the whole directory, not for the search term
	if (roomsTerm.value === '' && totalRooms.value !== null && totalRooms.value > shown) {
		return n('spreed', '{shown} of about %n room', '{shown} of about %n rooms', totalRooms.value, { shown })
	}
	return n('spreed', '%n room shown, scroll for more', '%n rooms shown, scroll for more', shown)
})

const debouncedSearchUsers = debounce(searchUsers, 300)
const debouncedSearchRooms = debounce(() => searchRooms(), 300)

watch(userSearch, () => debouncedSearchUsers())
watch(roomSearch, () => debouncedSearchRooms())
watch(mode, (value) => {
	if (value === 'join' && !roomsSearched.value && !roomsLoading.value) {
		// Show the first page of the directory, so rooms can be found without a search term
		searchRooms()
	}
})

onBeforeUnmount(() => {
	debouncedSearchUsers.clear()
	debouncedSearchRooms.clear()
	usersAbort?.abort()
	roomsAbort?.abort()
})

/**
 * Title of a room of the directory
 *
 * @param room The room
 */
function roomTitle(room: MatrixDirectoryRoom): string {
	return room.name || room.alias || room.roomId
}

/**
 * Address, members and topic of a room of the directory in one line
 *
 * @param room The room
 */
function roomDetails(room: MatrixDirectoryRoom): string {
	return [
		room.name && room.alias ? room.alias : '',
		n('spreed', '%n member', '%n members', room.members),
		room.topic,
	].filter((part) => part !== '').join(' · ')
}

/**
 * Open the conversation, or say that the next sync creates it
 *
 * @param token Token of the conversation, null when it does not exist yet
 */
function openConversation(token: string | null | undefined) {
	if (token) {
		router.push({ name: 'conversation', params: { token } })
	} else {
		showSuccess(t('spreed', 'The conversation appears with the next sync'))
	}
	emit('close')
}

/**
 * Join a room by its address, room id or link
 *
 * @param reference The room alias, id or link
 */
async function joinRoom(reference: string) {
	loading.value = true
	try {
		const response = await joinMatrixRoom({ reference })
		openConversation(response.data.ocs.data.token)
	} catch (error) {
		console.error(error)
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}

/**
 * Start a direct chat, or add the user to the invites of the new room
 *
 * @param mxid Matrix user id
 */
async function selectUser(mxid: string) {
	if (mode.value === 'room') {
		if (!invitees.value.includes(mxid)) {
			invitees.value.push(mxid)
		}
		userSearch.value = ''
		return
	}

	loading.value = true
	try {
		const response = await createMatrixRoom({ name: '', topic: '', invites: [mxid], direct: true })
		openConversation(response.data.ocs.data.token)
	} catch (error) {
		console.error(error)
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}

/**
 * Remove a user from the invites of the new room
 *
 * @param mxid Matrix user id
 */
function removeInvitee(mxid: string) {
	invitees.value = invitees.value.filter((invitee) => invitee !== mxid)
}

/**
 * Create the room with the invited users and open the conversation
 */
async function createRoom() {
	loading.value = true
	try {
		const response = await createMatrixRoom({
			name: name.value.trim(),
			topic: topic.value.trim(),
			invites: invitees.value,
			direct: false,
		})
		openConversation(response.data.ocs.data.token)
	} catch (error) {
		console.error(error)
		showError(errorMessage(error))
	} finally {
		loading.value = false
	}
}

/**
 * Enter in the user search selects a typed Matrix user id
 */
function onUserSearchEnter() {
	if (typedUserId.value) {
		selectUser(typedUserId.value)
	}
}

/**
 * Enter in the room search joins a typed room address
 */
function onRoomSearchEnter() {
	if (typedReference.value) {
		joinRoom(typedReference.value)
	}
}
</script>

<template>
	<NcDialog
		:name="t('spreed', 'Matrix room')"
		size="normal"
		@update:open="emit('close')">
		<div class="matrix-room">
			<NcRadioGroup v-model="mode" :label="t('spreed', 'Matrix room')" hideLabel>
				<NcRadioGroupButton :label="t('spreed', 'New room')" value="room" />
				<NcRadioGroupButton :label="t('spreed', 'Direct chat')" value="direct" />
				<NcRadioGroupButton :label="t('spreed', 'Join room')" value="join" />
			</NcRadioGroup>

			<template v-if="mode === 'room'">
				<NcTextField v-model="name" :label="t('spreed', 'Name')" :disabled="loading" />
				<NcTextField v-model="topic" :label="t('spreed', 'Topic')" :disabled="loading" />
				<div v-if="invitees.length > 0" class="matrix-room__chips">
					<NcChip
						v-for="mxid in invitees"
						:key="mxid"
						variant="tertiary"
						:text="mxid"
						@close="removeInvitee(mxid)" />
				</div>
			</template>

			<template v-if="mode !== 'join'">
				<NcTextField
					v-model="userSearch"
					:label="mode === 'room' ? t('spreed', 'Invite Matrix users') : t('spreed', 'Matrix user')"
					:placeholder="t('spreed', 'Search by name or enter @alice:example.org')"
					:disabled="loading"
					@keydown.enter.prevent="onUserSearchEnter" />
				<p class="matrix-room__hint">
					{{ t('spreed', 'Your homeserver decides which users you can find. A full Matrix user ID always works.') }}
				</p>
				<ul v-if="typedUserId || users.length > 0" class="matrix-room__results">
					<li v-if="typedUserId" class="matrix-room__result">
						<span class="matrix-room__result-text">
							<span class="matrix-room__result-title">{{ typedUserId }}</span>
						</span>
						<NcButton :disabled="loading" @click="selectUser(typedUserId)">
							{{ userActionLabel }}
						</NcButton>
					</li>
					<li v-for="user in users" :key="user.mxid" class="matrix-room__result">
						<span class="matrix-room__result-text">
							<span class="matrix-room__result-title">{{ user.displayName || user.mxid }}</span>
							<span v-if="user.displayName" class="matrix-room__result-details">{{ user.mxid }}</span>
						</span>
						<NcButton :disabled="loading || invitees.includes(user.mxid)" @click="selectUser(user.mxid)">
							{{ userActionLabel }}
						</NcButton>
					</li>
				</ul>
				<NcLoadingIcon v-else-if="usersLoading" />
				<p v-else-if="usersError" class="matrix-room__error">
					{{ usersError }}
				</p>
				<p v-else-if="usersSearched" class="matrix-room__hint">
					{{ t('spreed', 'No users found') }}
				</p>
				<p v-if="usersLimited && users.length > 0" class="matrix-room__hint">
					{{ t('spreed', 'More users match, type more of the name') }}
				</p>
			</template>

			<template v-else>
				<NcTextField
					v-model="roomSearch"
					:label="t('spreed', 'Search rooms or enter a room address')"
					placeholder="#room:example.org"
					:disabled="loading"
					@keydown.enter.prevent="onRoomSearchEnter" />
				<p v-if="roomsSummary && !typedReference" class="matrix-room__hint" aria-live="polite">
					{{ roomsSummary }}
				</p>
				<ul
					v-if="typedReference || rooms.length > 0"
					class="matrix-room__results"
					:class="{ 'matrix-room__results--loading': roomsLoading && !roomsPaging }"
					:aria-busy="roomsLoading"
					@scroll.passive="onRoomsScroll">
					<li v-if="typedReference" class="matrix-room__result">
						<span class="matrix-room__result-text">
							<span class="matrix-room__result-title">{{ typedReference }}</span>
						</span>
						<NcButton :disabled="loading" @click="joinRoom(typedReference)">
							{{ t('spreed', 'Join') }}
						</NcButton>
					</li>
					<li v-for="room in rooms" :key="room.roomId" class="matrix-room__result">
						<span class="matrix-room__result-text">
							<span class="matrix-room__result-title">{{ roomTitle(room) }}</span>
							<span class="matrix-room__result-details">{{ roomDetails(room) }}</span>
						</span>
						<NcButton v-if="room.token" :disabled="loading" @click="openConversation(room.token)">
							{{ t('spreed', 'Open') }}
						</NcButton>
						<NcButton v-else-if="room.joinRule === 'public'" :disabled="loading" @click="joinRoom(room.alias || room.roomId)">
							{{ t('spreed', 'Join') }}
						</NcButton>
						<span v-else class="matrix-room__result-state">
							{{ t('spreed', 'Invite only') }}
						</span>
					</li>
					<li v-if="nextBatch && !typedReference" class="matrix-room__more">
						<NcLoadingIcon v-if="roomsLoading && roomsPaging" />
						<NcButton
							v-else
							variant="tertiary"
							:disabled="roomsLoading"
							@click="searchRooms(true)">
							{{ t('spreed', 'Show more rooms') }}
						</NcButton>
					</li>
				</ul>
				<NcLoadingIcon v-else-if="roomsLoading" />
				<p v-if="roomsError" class="matrix-room__error">
					{{ roomsError }}
				</p>
				<p v-else-if="roomsSearched && !roomsLoading && rooms.length === 0 && !typedReference" class="matrix-room__hint">
					{{ t('spreed', 'No rooms found. The room directory only lists rooms that are published in it, rooms you are invited to appear in the conversation list.') }}
				</p>
			</template>

			<NcButton
				v-if="mode === 'room'"
				variant="primary"
				:disabled="loading || name.trim() === ''"
				@click="createRoom">
				{{ t('spreed', 'Create') }}
			</NcButton>
		</div>
	</NcDialog>
</template>

<style lang="scss" scoped>
.matrix-room {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	padding-bottom: calc(var(--default-grid-baseline) * 2);

	&__chips {
		display: flex;
		flex-wrap: wrap;
		gap: var(--default-grid-baseline);
	}

	&__hint {
		color: var(--color-text-maxcontrast);
	}

	&__error {
		color: var(--color-text-error);
	}

	&__results {
		display: flex;
		flex-direction: column;
		max-height: min(50vh, calc(var(--default-clickable-area) * 10));
		overflow-y: auto;
	}

	&__result {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
		padding: var(--default-grid-baseline) 0;
	}

	&__result-text {
		display: flex;
		flex: 1;
		flex-direction: column;
		min-width: 0;
	}

	&__result-title,
	&__result-details {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__result-details,
	&__result-state {
		color: var(--color-text-maxcontrast);
	}

	&__results--loading {
		opacity: .5;
	}

	&__more {
		display: flex;
		justify-content: center;
		padding: var(--default-grid-baseline) 0;
	}
}
</style>
