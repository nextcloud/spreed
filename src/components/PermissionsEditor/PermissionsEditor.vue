<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal size="small"
		:container="nestedContainer"
		:label-id="dialogHeaderId"
		v-on="$listeners">
		<div class="wrapper">
			<template v-if="!loading">
				<!-- eslint-disable-next-line vue/no-v-html -->
				<p :id="dialogHeaderId" class="title" v-html="modalTitle" />
				<form @submit.prevent="handleSubmitPermissions">
					<NcCheckboxRadioSwitch ref="callStart"
						v-model="callStart"
						class="checkbox">
						{{ t('spreed', 'Start a call') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch ref="lobbyIgnore"
						v-model="lobbyIgnore"
						class="checkbox">
						{{ t('spreed', 'Skip the lobby') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch ref="chatMessagesAndReactions"
						v-model="chatMessagesAndReactions"
						class="checkbox">
						{{ t('spreed', 'Can post messages and reactions') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch ref="publishAudio"
						v-model="publishAudio"
						class="checkbox">
						{{ t('spreed', 'Enable the microphone') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch ref="publishVideo"
						v-model="publishVideo"
						class="checkbox">
						{{ t('spreed', 'Enable the camera') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch ref="publishScreen"
						v-model="publishScreen"
						class="checkbox">
						{{ t('spreed', 'Share the screen') }}
					</NcCheckboxRadioSwitch>
					<NcButton ref="submit"
						native-type="submit"
						class="button-update-permission"
						type="primary"
						:disabled="submitButtonDisabled">
						{{ t('spreed', 'Update permissions') }}
					</NcButton>
				</form>
			</template>
			<div v-if="loading" class="loading-screen">
				<span class="icon-loading" />
				<p>{{ t('spreed', 'Updating permissions') }}</p>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { ref } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcModal from '@nextcloud/vue/components/NcModal'

import { useId } from '../../composables/useId.ts'
import { PARTICIPANT } from '../../constants.ts'

const PERMISSIONS = PARTICIPANT.PERMISSIONS

export default {
	name: 'PermissionsEditor',

	components: {
		NcCheckboxRadioSwitch,
		NcModal,
		NcButton,
	},

	props: {
		permissions: {
			type: Number,
			default: null,
		},

		/**
		 * The user's displayname. Don't provide this only when modifying the
		 * default conversation's permissions.
		 */
		displayName: {
			type: String,
			default: '',
		},

		nestedContainer: {
			type: String,
			default: undefined,
		},

		/**
		 * The conversation's name. Don't provide this when modifying
		 * participants' permissions.
		 */
		conversationName: {
			type: String,
			default: '',
		},

		/**
		 * Displays the loading state of this component
		 */
		loading: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['submit'],

	setup() {
		const dialogHeaderId = `permissions-editor-${useId()}`
		// Permission to start a call
		const callStart = ref(false)
		// Permission to bypass the lobby
		const lobbyIgnore = ref(false)
		// Permission to post messages and reactions
		const chatMessagesAndReactions = ref(false)
		// Permission to enable the microphone
		const publishAudio = ref(false)
		// Permission to enable the camera
		const publishVideo = ref(false)
		// Permission to start a screenshare
		const publishScreen = ref(false)

		return {
			dialogHeaderId,
			callStart,
			lobbyIgnore,
			chatMessagesAndReactions,
			publishAudio,
			publishVideo,
			publishScreen,
		}
	},

	computed: {
		modalTitle() {
			if (this.displayName) {
				return t('spreed', 'In this conversation <strong>{user}</strong> can:', {
					user: this.displayName,
				})
			} else if (this.conversationName) {
				return t('spreed', 'Edit default permissions for participants in <strong>{conversationName}</strong>', {
					conversationName: this.conversationName,
				})
			} else throw Error('you need to fill either the conversationName or the displayName props')
		},

		permissionsWithDefault() {
			if (this.permissions !== PERMISSIONS.DEFAULT) {
				return this.permissions
			}

			return loadState(
				'spreed',
				'default_permissions',
				PERMISSIONS.MAX_DEFAULT & ~PERMISSIONS.LOBBY_IGNORE,
			)
		},

		/**
		 * The number of the edited permissions during the editing of the form.
		 * We use this to compare it with the actual permissions of the
		 * participant in the store and enable or disable the submit button
		 * accordingly.
		 */
		formPermissions() {
			return (this.callStart ? PERMISSIONS.CALL_START : 0)
				| PERMISSIONS.CALL_JOIN // Currently not handled, just adding it, so that manually selecting all checkboxes goes to the "All" permissions state
				| (this.lobbyIgnore ? PERMISSIONS.LOBBY_IGNORE : 0)
				| (this.chatMessagesAndReactions ? PERMISSIONS.CHAT : 0)
				| (this.publishAudio ? PERMISSIONS.PUBLISH_AUDIO : 0)
				| (this.publishVideo ? PERMISSIONS.PUBLISH_VIDEO : 0)
				| (this.publishScreen ? PERMISSIONS.PUBLISH_SCREEN : 0)
				| PERMISSIONS.CUSTOM
		},

		/**
		 * If the permissions are not changed, the submit button will stay
		 * disabled.
		 */
		submitButtonDisabled() {
			return (!!(this.permissionsWithDefault & PERMISSIONS.CALL_START)) === this.callStart
				&& !!(this.permissionsWithDefault & PERMISSIONS.LOBBY_IGNORE) === this.lobbyIgnore
				&& !!(this.permissionsWithDefault & PERMISSIONS.CHAT) === this.chatMessagesAndReactions
				&& !!(this.permissionsWithDefault & PERMISSIONS.PUBLISH_AUDIO) === this.publishAudio
				&& !!(this.permissionsWithDefault & PERMISSIONS.PUBLISH_VIDEO) === this.publishVideo
				&& !!(this.permissionsWithDefault & PERMISSIONS.PUBLISH_SCREEN) === this.publishScreen
		},
	},

	mounted() {
		this.writePermissionsToComponent(this.permissionsWithDefault)
	},

	methods: {
		t,
		/**
		 * Takes the permissions from the store and writes them in the data of
		 * this component.
		 *
		 * @param {number} permissions - the permissions number.
		 */
		writePermissionsToComponent(permissions) {
			this.callStart = Boolean(permissions & PERMISSIONS.CALL_START)
			this.lobbyIgnore = Boolean(permissions & PERMISSIONS.LOBBY_IGNORE)
			this.chatMessagesAndReactions = Boolean(permissions & PERMISSIONS.CHAT)
			this.publishAudio = Boolean(permissions & PERMISSIONS.PUBLISH_AUDIO)
			this.publishVideo = Boolean(permissions & PERMISSIONS.PUBLISH_VIDEO)
			this.publishScreen = Boolean(permissions & PERMISSIONS.PUBLISH_SCREEN)
		},

		handleSubmitPermissions() {
			this.$emit('submit', this.formPermissions)
		},
	},
}
</script>

<style lang="scss" scoped>
.wrapper {
	padding: 0 24px 24px 24px;
}

.title {
	font-size: 16px;
	margin-bottom: 12px;
	padding-top: 24px;
}

.loading-screen {
	height: 200px;
	text-align: center;
	font-weight: bold;
	display: flex;
	flex-direction: column;
	justify-content: center;

	// Loader
	span {
		margin-bottom: 16px;
	}
}

.button-update-permission {
	margin: 0 auto;
}
</style>
