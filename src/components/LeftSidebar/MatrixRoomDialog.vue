<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		v-if="modal"
		:name="t('spreed', 'Matrix room')"
		size="normal"
		closeOnClickOutside
		@update:open="closeModal">
		<div class="matrix-dialog">
			<NcCheckboxRadioSwitch v-model="mode" value="create" name="matrix_mode" type="radio" buttonVariant buttonVariantGrouped="horizontal">
				{{ t('spreed', 'Create room') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="mode" value="direct" name="matrix_mode" type="radio" buttonVariant buttonVariantGrouped="horizontal">
				{{ t('spreed', 'Direct message') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="mode" value="join" name="matrix_mode" type="radio" buttonVariant buttonVariantGrouped="horizontal">
				{{ t('spreed', 'Join by address') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="mode" value="directory" name="matrix_mode" type="radio" buttonVariant buttonVariantGrouped="horizontal">
				{{ t('spreed', 'Public rooms') }}
			</NcCheckboxRadioSwitch>

			<template v-if="mode === 'create'">
				<NcTextField v-model="name" :label="t('spreed', 'Room name')" :disabled="loading" />
				<NcTextField v-model="topic" :label="t('spreed', 'Topic (optional)')" :disabled="loading" />
				<NcTextField
					v-model="invitees"
					:label="t('spreed', 'Invite Matrix users (comma separated, e.g. @alice:matrix.org)')"
					:disabled="loading" />
				<NcCheckboxRadioSwitch v-model="encrypted" type="switch" :disabled="loading">
					{{ t('spreed', 'End-to-end encrypted') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="isPublic" type="switch" :disabled="loading">
					{{ t('spreed', 'Public room (anyone can join, listed in the directory)') }}
				</NcCheckboxRadioSwitch>
			</template>

			<template v-else-if="mode === 'direct'">
				<NcTextField
					v-model="invitees"
					:label="t('spreed', 'Matrix user id, e.g. @alice:matrix.org')"
					:disabled="loading"
					@keydown.enter="submit" />
				<p class="matrix-dialog__hint">
					{{ t('spreed', 'Direct messages are end-to-end encrypted.') }}
				</p>
			</template>

			<template v-else-if="mode === 'join'">
				<NcTextField
					v-model="reference"
					:label="t('spreed', 'Room address: #room:server, !id:server or a matrix.to link')"
					:disabled="loading"
					@keydown.enter="submit" />
			</template>

			<template v-else>
				<NcTextField
					v-model="search"
					:label="t('spreed', 'Search public rooms on your homeserver')"
					:disabled="loading"
					@update:modelValue="debouncedSearch" />
				<ul class="matrix-dialog__directory">
					<li v-for="entry in directory" :key="entry.roomId" class="matrix-dialog__entry">
						<div class="matrix-dialog__entry-text">
							<strong>{{ entry.name }}</strong>
							<span class="matrix-dialog__hint">{{ entry.alias || entry.roomId }} · {{ n('spreed', '%n member', '%n members', entry.members) }}</span>
							<span v-if="entry.topic" class="matrix-dialog__hint">{{ entry.topic }}</span>
						</div>
						<NcButton :disabled="loading || entry.joined" @click="joinEntry(entry)">
							{{ entry.joined ? t('spreed', 'Joined') : t('spreed', 'Join') }}
						</NcButton>
					</li>
				</ul>
				<NcButton v-if="nextBatch" :disabled="loading" variant="tertiary" @click="loadDirectory(false)">
					{{ t('spreed', 'Load more') }}
				</NcButton>
			</template>

			<p v-if="error" class="matrix-dialog__error">{{ error }}</p>
		</div>

		<template v-if="mode !== 'directory'" #actions>
			<NcButton variant="primary" :disabled="loading || !canSubmit" @click="submit">
				{{ submitLabel }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import debounce from 'debounce'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createMatrixRoom, getMatrixDirectory, joinMatrixRoom } from '../../services/matrixService.ts'

export default {
	name: 'MatrixRoomDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcTextField,
	},

	setup() {
		return { t, n }
	},

	data() {
		return {
			modal: false,
			mode: 'create',
			loading: false,
			name: '',
			topic: '',
			invitees: '',
			encrypted: true,
			isPublic: false,
			reference: '',
			search: '',
			directory: [],
			nextBatch: null,
			error: '',
		}
	},

	computed: {
		canSubmit() {
			if (this.mode === 'create') {
				return this.name.trim() !== ''
			}
			if (this.mode === 'direct') {
				return this.invitees.trim() !== ''
			}
			return this.reference.trim() !== ''
		},

		submitLabel() {
			return {
				create: t('spreed', 'Create room'),
				direct: t('spreed', 'Start chat'),
				join: t('spreed', 'Join'),
			}[this.mode]
		},
	},

	watch: {
		mode(mode) {
			this.error = ''
			if (mode === 'directory' && this.directory.length === 0) {
				this.loadDirectory(true)
			}
		},
	},

	created() {
		this.debouncedSearch = debounce(() => this.loadDirectory(true), 400)
	},

	methods: {
		showModal() {
			this.modal = true
			this.error = ''
		},

		closeModal() {
			this.modal = false
		},

		errorText(error) {
			const code = error?.response?.data?.ocs?.data?.error
			const status = error?.response?.status
			if (status === 202 || code === 'knocked') {
				return t('spreed', 'This room needs an invitation – a request to join was sent.')
			}
			return {
				account: t('spreed', 'Link a Matrix account in the settings first.'),
				reference: t('spreed', 'This is not a Matrix room address.'),
				'not-found': t('spreed', 'Room not found.'),
				'e2ee-disabled': t('spreed', 'Encrypted rooms are not allowed on this homeserver.'),
				unreachable: t('spreed', 'The homeserver could not be reached.'),
				room: t('spreed', 'The room was created but did not show up yet – it will appear with the next sync.'),
			}[code] ?? (code?.startsWith?.('no-matrix-account:')
				? t('spreed', '{user} has no linked Matrix account.', { user: code.split(':')[1] })
				: t('spreed', 'Something went wrong: {reason}', { reason: code || status || 'unknown' }))
		},

		async submit() {
			if (!this.canSubmit) {
				return
			}
			this.loading = true
			this.error = ''
			try {
				let response
				if (this.mode === 'create' || this.mode === 'direct') {
					const ids = this.invitees.split(/[,;\s]+/).map((s) => s.trim()).filter(Boolean)
					response = await createMatrixRoom({
						name: this.mode === 'create' ? this.name : '',
						topic: this.mode === 'create' ? this.topic : '',
						encrypted: this.mode === 'create' ? this.encrypted : true,
						public: this.mode === 'create' ? this.isPublic : false,
						inviteMatrixIds: ids,
						direct: this.mode === 'direct',
					})
				} else {
					response = await joinMatrixRoom(this.reference.trim())
				}
				await this.openConversation(response.data.ocs.data)
			} catch (error) {
				console.error(error)
				this.error = this.errorText(error)
			} finally {
				this.loading = false
			}
		},

		async joinEntry(entry) {
			this.loading = true
			this.error = ''
			try {
				const response = await joinMatrixRoom(entry.alias || entry.roomId)
				await this.openConversation(response.data.ocs.data)
			} catch (error) {
				console.error(error)
				this.error = this.errorText(error)
			} finally {
				this.loading = false
			}
		},

		async loadDirectory(reset) {
			this.loading = true
			try {
				const response = await getMatrixDirectory(this.search, reset ? null : this.nextBatch)
				const data = response.data.ocs.data
				this.directory = reset ? data.chunk : [...this.directory, ...data.chunk]
				this.nextBatch = data.next_batch
			} catch (error) {
				console.error(error)
				this.error = this.errorText(error)
			} finally {
				this.loading = false
			}
		},

		async openConversation(conversation) {
			this.$store.dispatch('addConversation', conversation)
			showSuccess(t('spreed', 'Conversation ready'))
			this.closeModal()
			this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch((err) => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},
	},
}
</script>

<style lang="scss" scoped>
.matrix-dialog {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: var(--default-grid-baseline) 0;

	&__hint {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small);
	}

	&__error {
		color: var(--color-error-text);
	}

	&__directory {
		max-height: 320px;
		overflow-y: auto;
	}

	&__entry {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: var(--default-grid-baseline);
		padding: var(--default-grid-baseline) 0;
		border-bottom: 1px solid var(--color-border);
	}

	&__entry-text {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}
}
</style>
