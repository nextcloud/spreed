<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section v-if="!hasSignalingServers || trialAccount.length !== 0"
		id="hosted_signaling_server"
		class="hosted-signaling section">
		<h2>
			{{ t('spreed', 'Hosted High-performance backend') }}
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'Our partner Struktur AG provides a service where a hosted signaling server can be requested. For this you only need to fill out the form below and your Nextcloud will request it. Once the server is set up for you the credentials will be filled automatically. This will overwrite the existing signaling server settings.') }}
		</p>

		<template v-if="!trialAccount.status">
			<NcTextField v-model="hostedHPBNextcloudUrl"
				class="form__textfield"
				name="hosted_hpb_nextcloud_url"
				placeholder="https://cloud.example.org/"
				:disabled="loading"
				:label="t('spreed', 'URL of this Nextcloud instance')"
				label-visible />

			<NcTextField v-model="hostedHPBFullName"
				class="form__textfield"
				name="full_name"
				placeholder="Jane Doe"
				:disabled="loading"
				:label="t('spreed', 'Full name of the user requesting the trial')"
				label-visible />

			<NcTextField v-model="hostedHPBEmail"
				class="form__textfield"
				name="hosted_hpb_email"
				placeholder="jane@example.org"
				:disabled="loading"
				:label="t('spreed', 'Email of the user')"
				label-visible />

			<NcSelect v-model="hostedHPBLanguage"
				input-id="hosted_hpb_language_input"
				:input-label=" t('spreed', 'Language')"
				class="form__select"
				name="hosted_hpb_language"
				:disabled="loading"
				:aria-label="t('spreed', 'Language')"
				:placeholder="t('spreed', 'Language')"
				:options="languages"
				:clearable="false"
				label="name"
				track-by="code"
				no-wrap />

			<NcSelect v-model="hostedHPBCountry"
				input-id="hosted_hpb_country_input"
				:input-label=" t('spreed', 'Country')"
				class="form__select"
				name="hosted_hpb_country"
				:disabled="loading"
				:aria-label="t('spreed', 'Country')"
				:placeholder="t('spreed', 'Country')"
				:options="countries"
				:clearable="false"
				label="name"
				track-by="code"
				no-wrap />

			<NcButton class="additional-top-margin"
				:disabled="!hostedHPBFilled || loading"
				@click="requestHPBTrial">
				{{ t('spreed', 'Request signaling server trial') }}
			</NcButton>

			<p v-if="requestError !== ''" class="warning">
				{{ requestError }}
			</p>

			<!-- eslint-disable-next-line vue/no-v-html -->
			<p class="settings-hint additional-top-margin" v-html="disclaimerHint" />
		</template>
		<template v-else>
			<p class="settings-hint additional-top-margin">
				{{ t('spreed', 'You can see the current status of your hosted signaling server in the following table.') }}
			</p>
			<table>
				<tr>
					<td>{{ t('spreed', 'Status') }}</td>
					<td>{{ translatedStatus }}</td>
				</tr>
				<tr>
					<td>{{ t('spreed', 'Created at') }}</td>
					<td>{{ createdDate }}</td>
				</tr>
				<tr>
					<td>{{ t('spreed', 'Expires at') }}</td>
					<td>{{ expiryDate }}</td>
				</tr>
				<tr v-if="trialAccount.limits">
					<td>{{ t('spreed', 'Limits') }}</td>
					<td>{{ n('spreed', '%n user', '%n users', trialAccount.limits.users) }}</td>
				</tr>
			</table>
			<p v-if="requestError !== ''"
				class="warning">
				{{ requestError }}
			</p>

			<NcButton type="error"
				class="additional-top-margin"
				:disabled="loading"
				@click="deleteAccount">
				{{ t('spreed', 'Delete the signaling server account') }}
			</NcButton>
		</template>
	</section>
</template>

<script>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t, n } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { formatDateTime } from '../../utils/formattedTime.ts'

export default {
	name: 'HostedSignalingServer',

	components: {
		NcButton,
		NcSelect,
		NcTextField,
	},

	props: {
		hasSignalingServers: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			hostedHPBNextcloudUrl: '',
			hostedHPBFullName: '',
			hostedHPBEmail: '',
			hostedHPBLanguage: '',
			hostedHPBCountry: '',
			requestError: '',
			loading: false,
			showForm: true,
			trialAccount: [],
			languages: [],
			countries: [],
		}
	},

	computed: {
		hostedHPBFilled() {
			return this.hostedHPBNextcloudUrl !== ''
				&& this.hostedHPBFullName !== ''
				&& this.hostedHPBEmail !== ''
				&& this.hostedHPBLanguage !== ''
				&& this.hostedHPBCountry !== ''
		},
		disclaimerHint() {
			return t('spreed', 'By clicking the button above the information in the form is sent to the servers of Struktur AG. You can find further information at {linkstart}spreed.eu{linkend}.')
				.replace('{linkstart}', '<a target="_blank" rel="noreferrer nofollow" class="external" href="https://www.spreed.eu/nextcloud-talk-high-performance-backend/">')
				.replace('{linkend}', ' ↗</a>')
		},
		translatedStatus() {
			switch (this.trialAccount.status) {
			case 'pending':
				return t('spreed', 'Pending')
			case 'error':
				return t('spreed', 'Error')
			case 'blocked':
				return t('spreed', 'Blocked')
			case 'active':
				return t('spreed', 'Active')
			case 'expired':
				return t('spreed', 'Expired')
			}

			return ''
		},
		expiryDate() {
			return formatDateTime(this.trialAccount.expires, 'L')
		},
		createdDate() {
			return formatDateTime(this.trialAccount.created, 'L')
		},
	},

	beforeMount() {
		const state = loadState('spreed', 'hosted_signaling_server_prefill')
		this.hostedHPBNextcloudUrl = state.url
		this.hostedHPBFullName = state.fullName
		this.hostedHPBEmail = state.email

		this.trialAccount = loadState('spreed', 'hosted_signaling_server_trial_data')

		const languagesAndCountries = loadState('spreed', 'hosted_signaling_server_language_data')
		// two lists of {code: "es", name: "Español"} - one is in 'commonLanguages' and one in 'otherLanguages'
		this.languages = [...languagesAndCountries.languages.commonLanguages, ...languagesAndCountries.languages.otherLanguages]
		// list of {code: "France", name: "France"}
		this.countries = languagesAndCountries.countries

		this.hostedHPBLanguage = this.languages.find(language => language.code === state.language) ?? this.languages[0]
		this.hostedHPBCountry = this.countries.find(country => country.code === state.country) ?? this.countries[0]
	},

	methods: {
		t,
		n,
		async requestHPBTrial() {
			this.requestError = ''
			this.loading = true
			try {
				const res = await axios.post(generateOcsUrl('apps/spreed/api/v1/hostedsignalingserver/requesttrial'), {
					url: this.hostedHPBNextcloudUrl,
					name: this.hostedHPBFullName,
					email: this.hostedHPBEmail,
					language: this.hostedHPBLanguage.code,
					country: this.hostedHPBCountry.code,
				})

				this.trialAccount = res.data.ocs.data
			} catch (err) {
				this.requestError = err?.response?.data?.ocs?.data?.message || t('spreed', 'The trial could not be requested. Please try again later.')
			} finally {
				this.loading = false
			}
		},

		async deleteAccount() {
			this.requestError = ''
			this.loading = true

			try {
				await axios.delete(generateOcsUrl('apps/spreed/api/v1/hostedsignalingserver/delete'))

				this.trialAccount = []
			} catch (err) {
				this.requestError = err?.response?.data?.ocs?.data?.message || t('spreed', 'The account could not be deleted. Please try again later.')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.hosted-signaling {
	.form {
		&__textfield {
			width: 300px;
			margin-top: 12px;
		}

		&__select {
			min-width: 300px !important;
		}
	}
}

.additional-top-margin {
	margin-top: 10px;
}

td {
	padding: 5px;
	border-bottom: 1px solid var(--color-border);
}

tr:last-child td {
	border-bottom: none;
}

tr :first-child {
	opacity: .5;
}

</style>
