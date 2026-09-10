<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="matrix-account">
		<template v-if="info?.account">
			<p>
				{{ t('spreed', 'Linked as {mxid}', { mxid: info.account.mxid }) }}
				<span class="matrix-account__muted">· {{ t('spreed', 'device {device}', { device: info.account.deviceId }) }}</span>
			</p>
			<p v-if="info.account.status === MATRIX_ACCOUNT_STATUS.TOKEN_INVALID" class="matrix-account__warning">
				{{ t('spreed', 'The homeserver rejected this session. Enter your Matrix password to log in again.') }}
			</p>
			<p v-else-if="info.account.lastError" class="matrix-account__warning">
				{{ t('spreed', 'Last sync error: {error}', { error: info.account.lastError }) }}
			</p>
			<p v-else-if="info.account.lastSync" class="matrix-account__muted">
				{{ t('spreed', 'Last synced {time}', { time: formatTime(info.account.lastSync) }) }}
			</p>

			<div v-if="info.account.status === MATRIX_ACCOUNT_STATUS.TOKEN_INVALID" class="matrix-account__form">
				<NcPasswordField
					v-model="password"
					:label="t('spreed', 'Matrix password')"
					:disabled="loading"
					@keydown.enter="relogin" />
				<NcButton variant="primary" :disabled="loading || !password" @click="relogin">
					{{ t('spreed', 'Log in again') }}
				</NcButton>
			</div>

			<div v-if="info.device && info.account.status === MATRIX_ACCOUNT_STATUS.ACTIVE" class="matrix-account__device">
				<h4>{{ t('spreed', 'Encryption') }}</h4>
				<p v-if="info.device.error" class="matrix-account__warning">
					{{ t('spreed', 'Encryption is not available: {error}', { error: info.device.error }) }}
				</p>
				<template v-else>
					<p>
						<span v-if="info.device.verified || info.device.crossSigned">✅ {{ t('spreed', 'This Talk device is verified. Other clients share encryption keys with it.') }}</span>
						<span v-else>⚠️ {{ t('spreed', 'This Talk device is not verified yet. Verify it from another of your Matrix clients (for example Element) so encrypted rooms can be read here.') }}</span>
					</p>
					<p class="matrix-account__muted">
						{{ t('spreed', 'Session key: {key}', { key: formatKey(info.device.ed25519) }) }}
					</p>

					<template v-if="verification && verification.state !== 'done' && verification.state !== 'cancelled'">
						<p v-if="!verification.emoji.length">
							{{ t('spreed', 'Verification requested. Accept it on your other Matrix client …') }}
							<span v-if="verification.theirDeviceId" class="matrix-account__muted">({{ verification.theirDeviceId }})</span>
						</p>
						<template v-else>
							<p>{{ t('spreed', 'Compare the emoji with your other client. They must appear in the same order.') }}</p>
							<ul class="matrix-account__emoji">
								<li v-for="entry in verification.emoji" :key="entry.name">
									<span class="matrix-account__emoji-symbol">{{ entry.emoji }}</span>
									<span class="matrix-account__emoji-name">{{ entry.name }}</span>
								</li>
							</ul>
							<div class="matrix-account__actions">
								<NcButton variant="primary" :disabled="loading || verification.state === 'mac_sent'" @click="confirmVerification(true)">
									{{ t('spreed', 'They match') }}
								</NcButton>
								<NcButton variant="error" :disabled="loading" @click="confirmVerification(false)">
									{{ t('spreed', 'They do not match') }}
								</NcButton>
							</div>
							<p v-if="verification.state === 'mac_sent'" class="matrix-account__muted">
								{{ t('spreed', 'Waiting for the other client to confirm …') }}
							</p>
						</template>
						<NcButton :disabled="loading" variant="tertiary" @click="cancelVerification">
							{{ t('spreed', 'Cancel verification') }}
						</NcButton>
					</template>
					<p v-else-if="verification && verification.state === 'cancelled'" class="matrix-account__warning">
						{{ t('spreed', 'Verification cancelled: {reason}', { reason: verification.reason || '' }) }}
					</p>
					<NcButton v-if="!verification || verification.state === 'done' || verification.state === 'cancelled'" :disabled="loading" @click="startVerification">
						{{ info.device.verified ? t('spreed', 'Verify again') : t('spreed', 'Verify this device') }}
					</NcButton>

					<h4>{{ t('spreed', 'Encrypted history') }}</h4>
					<p class="matrix-account__muted">
						{{ info.device.hasBackupKey
							? t('spreed', 'The key backup is available. Restore it to read encrypted messages from before this link.')
							: t('spreed', 'To read encrypted messages from before this link, restore the key backup: verify this device (the other client then shares the backup key) or enter your recovery key.') }}
						<span v-if="info.device.backupRestoredAt">{{ t('spreed', 'Last restored {time}.', { time: formatTime(info.device.backupRestoredAt) }) }}</span>
					</p>
					<div class="matrix-account__form">
						<NcPasswordField
							v-if="!info.device.hasBackupKey"
							v-model="recoveryKey"
							:label="t('spreed', 'Recovery key (EsTc …)')"
							:disabled="loading" />
						<NcButton :disabled="loading || (!info.device.hasBackupKey && !recoveryKey)" @click="restoreBackup">
							{{ t('spreed', 'Restore encrypted history') }}
						</NcButton>
					</div>
				</template>
			</div>

			<div class="matrix-account__actions">
				<NcButton :disabled="loading" @click="sync">
					{{ t('spreed', 'Sync now') }}
				</NcButton>
				<NcButton :disabled="loading" variant="tertiary" @click="unlink">
					{{ t('spreed', 'Unlink Matrix account') }}
				</NcButton>
			</div>
		</template>

		<template v-else-if="info?.canLink">
			<p class="matrix-account__hint">
				{{ t('spreed', 'Link your Matrix account to see and use your Matrix rooms in Talk. Your password is used once for the login and is not stored; Talk appears as a new device on your Matrix account.') }}
			</p>
			<div class="matrix-account__form">
				<NcSelect
					v-if="info.homeservers.length > 1"
					v-model="homeserver"
					:inputLabel="t('spreed', 'Homeserver')"
					:options="info.homeservers"
					label="name"
					trackBy="id"
					:clearable="false"
					:disabled="loading" />
				<NcTextField
					v-model="user"
					:label="t('spreed', 'Matrix username')"
					:placeholder="homeserver ? '@user:' + homeserver.serverName : ''"
					:disabled="loading" />
				<NcPasswordField
					v-model="password"
					:label="t('spreed', 'Matrix password')"
					:disabled="loading"
					@keydown.enter="link" />
				<NcButton variant="primary" :disabled="loading || !user || !password || !homeserver" @click="link">
					{{ t('spreed', 'Link account') }}
				</NcButton>
			</div>
		</template>

		<p v-else class="matrix-account__muted">
			{{ t('spreed', 'Linking Matrix accounts is not available for you.') }}
		</p>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {
	MATRIX_ACCOUNT_STATUS,
	cancelMatrixVerification,
	confirmMatrixVerification,
	getMatrixAccount,
	getMatrixVerification,
	restoreMatrixBackup,
	startMatrixVerification,
	linkMatrixAccount,
	reloginMatrixAccount,
	syncMatrixAccount,
	unlinkMatrixAccount,
} from '../../services/matrixService.ts'
import { formatDateTime } from '../../utils/formattedTime.ts'

export default {
	name: 'MatrixAccountSettings',

	components: {
		NcButton,
		NcPasswordField,
		NcSelect,
		NcTextField,
	},

	setup() {
		return { t, MATRIX_ACCOUNT_STATUS }
	},

	data() {
		return {
			loading: false,
			info: null,
			homeserver: null,
			user: '',
			password: '',
			verification: null,
			pollTimer: null,
			recoveryKey: '',
		}
	},

	async mounted() {
		await this.load()
	},

	beforeUnmount() {
		this.stopPolling()
	},

	methods: {
		formatTime(timestamp) {
			return formatDateTime(timestamp * 1000, 'shortWeekdayWithTime')
		},

		formatKey(key) {
			if (!key) {
				return ''
			}
			return key.match(/.{1,4}/g).join(' ')
		},

		async startVerification() {
			this.loading = true
			try {
				const response = await startMatrixVerification()
				this.verification = response.data.ocs.data
				this.startPolling()
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not start the verification'))
			} finally {
				this.loading = false
			}
		},

		async pollVerification() {
			try {
				const response = await getMatrixVerification()
				this.verification = response.data.ocs.data
				if (!this.verification || this.verification.state === 'done' || this.verification.state === 'cancelled') {
					this.stopPolling()
					if (this.verification?.state === 'done') {
						showSuccess(t('spreed', 'Device verified'))
					}
					await this.load()
				}
			} catch (error) {
				console.error(error)
				this.stopPolling()
			}
		},

		startPolling() {
			this.stopPolling()
			this.pollTimer = setInterval(() => this.pollVerification(), 3000)
		},

		stopPolling() {
			if (this.pollTimer) {
				clearInterval(this.pollTimer)
				this.pollTimer = null
			}
		},

		async confirmVerification(matches) {
			this.loading = true
			try {
				const response = await confirmMatrixVerification(matches)
				this.verification = response.data.ocs.data
				if (!matches) {
					this.stopPolling()
				}
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not confirm the verification'))
			} finally {
				this.loading = false
			}
		},

		async restoreBackup() {
			this.loading = true
			try {
				const response = await restoreMatrixBackup(this.recoveryKey)
				const result = response.data.ocs.data
				this.recoveryKey = ''
				showSuccess(t('spreed', 'Imported {imported} of {sessions} room keys, {decrypted} messages decrypted', result))
				await this.load()
			} catch (error) {
				console.error(error)
				const code = error?.response?.data?.ocs?.data?.error
				showError({
					'no-backup': t('spreed', 'Your Matrix account has no key backup'),
					'no-key': t('spreed', 'No backup key available yet – verify this device or enter the recovery key'),
					'recovery-key': t('spreed', 'This is not a valid recovery key'),
					'key-mismatch': t('spreed', 'The recovery key does not match the backup'),
				}[code] ?? t('spreed', 'Could not restore the key backup'))
			} finally {
				this.loading = false
			}
		},

		async cancelVerification() {
			this.loading = true
			try {
				await cancelMatrixVerification()
				this.verification = null
				this.stopPolling()
			} catch (error) {
				console.error(error)
			} finally {
				this.loading = false
			}
		},

		async load() {
			this.loading = true
			try {
				const response = await getMatrixAccount()
				this.info = response.data.ocs.data
				this.verification = this.info.verification
				if (this.verification && this.verification.state !== 'done' && this.verification.state !== 'cancelled') {
					this.startPolling()
				}
				if (!this.homeserver && this.info.homeservers.length) {
					this.homeserver = this.info.homeservers[0]
				}
			} catch (error) {
				console.error(error)
			} finally {
				this.loading = false
			}
		},

		async link() {
			this.loading = true
			try {
				await linkMatrixAccount(this.homeserver.id, this.user, this.password)
				this.password = ''
				showSuccess(t('spreed', 'Matrix account linked. Your rooms are being imported.'))
				await this.load()
			} catch (error) {
				console.error(error)
				const reason = error?.response?.data?.ocs?.data?.error
				showError({
					credentials: t('spreed', 'Wrong Matrix username or password'),
					unreachable: t('spreed', 'The homeserver could not be reached'),
					'already-linked': t('spreed', 'An account is already linked'),
					'not-allowed': t('spreed', 'You are not allowed to link a Matrix account'),
				}[reason] ?? t('spreed', 'Could not link the Matrix account'))
			} finally {
				this.loading = false
			}
		},

		async relogin() {
			this.loading = true
			try {
				await reloginMatrixAccount(this.password)
				this.password = ''
				showSuccess(t('spreed', 'Logged in again'))
				await this.load()
			} catch (error) {
				console.error(error)
				showError(error?.response?.status === 401
					? t('spreed', 'Wrong Matrix password')
					: t('spreed', 'Could not log in again'))
			} finally {
				this.loading = false
			}
		},

		async sync() {
			this.loading = true
			try {
				await syncMatrixAccount()
				showSuccess(t('spreed', 'Matrix rooms synced'))
				await this.load()
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not sync'))
			} finally {
				this.loading = false
			}
		},

		async unlink() {
			this.loading = true
			try {
				await unlinkMatrixAccount()
				showSuccess(t('spreed', 'Matrix account unlinked'))
				await this.load()
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not unlink the Matrix account'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.matrix-account {
	&__form {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
		max-width: 400px;
		margin: calc(var(--default-grid-baseline) * 2) 0;
	}

	&__actions {
		display: flex;
		gap: var(--default-grid-baseline);
	}

	&__muted {
		color: var(--color-text-maxcontrast);
	}

	&__warning {
		color: var(--color-error-text);
	}

	&__hint {
		margin-bottom: calc(var(--default-grid-baseline) * 2);
	}

	&__device {
		margin: calc(var(--default-grid-baseline) * 3) 0;

		h4 {
			font-weight: bold;
			margin-bottom: var(--default-grid-baseline);
		}
	}

	&__emoji {
		display: flex;
		flex-wrap: wrap;
		gap: calc(var(--default-grid-baseline) * 2);
		margin: calc(var(--default-grid-baseline) * 2) 0;

		li {
			display: flex;
			flex-direction: column;
			align-items: center;
			width: 72px;
		}
	}

	&__emoji-symbol {
		font-size: 32px;
		line-height: 1.2;
	}

	&__emoji-name {
		font-size: var(--font-size-small);
		color: var(--color-text-maxcontrast);
	}
}
</style>
