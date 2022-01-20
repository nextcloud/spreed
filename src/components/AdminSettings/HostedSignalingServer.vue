<!--
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<div
		v-if="showForm"
		id="hosted_signaling_server"
		class="videocalls section">
		<h2>
			{{ t('spreed', 'Hosted high-performance backend') }}
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'Our partner Struktur AG provides a service where a hosted signaling server can be requested. For this you only need to fill out the form below and your Nextcloud will request it. Once the server is set up for you the credentials will be filled automatically. This will overwrite the existing signaling server settings.') }}
		</p>

		<div v-if="!trialAccount.status">
			<h4>{{ t('spreed', 'URL of this Nextcloud instance') }}</h4>
			<input
				v-model="hostedHPBNextcloudUrl"
				type="text"
				name="hosted_hpb_nextcloud_url"
				placeholder="https://cloud.example.org/"
				:disabled="loading"
				:aria-label="t('spreed', 'URL of this Nextcloud instance')">
			<h4>{{ t('spreed', 'Full name of the user requesting the trial') }}</h4>
			<input
				v-model="hostedHPBFullName"
				type="text"
				name="full_name"
				placeholder="Jane Doe"
				:disabled="loading"
				:aria-label="t('spreed', 'Name of the user requesting the trial')">
			<h4>{{ t('spreed', 'Email of the user') }}</h4>
			<input
				v-model="hostedHPBEmail"
				type="text"
				name="hosted_hpb_email"
				placeholder="jane@example.org"
				:disabled="loading"
				:aria-label="t('spreed', 'Email of the user')">
			<h4>{{ t('spreed', 'Language') }}</h4>
			<select
				v-model="hostedHPBLanguage"
				name="hosted_hpb_language"
				:placeholder="t('spreed', 'Language')"
				:disabled="loading"
				:aria-label="t('spreed', 'Language')">
				<option v-for="l in languages.commonLanguages" :key="l.code" :value="l.code">
					{{ l.name }}
				</option>
				<optgroup label="––––––––––" />
				<option v-for="l in languages.otherLanguages" :key="l.code" :value="l.code">
					{{ l.name }}
				</option>
			</select>
			<h4>{{ t('spreed', 'Country') }}</h4>
			<select
				v-model="hostedHPBCountry"
				name="hosted_hpb_country"
				:placeholder="t('spreed', 'Country')"
				:disabled="loading"
				:aria-label="t('spreed', 'Country')">
				<option v-for="c in countries" :key="c.code" :value="c.code">
					{{ c.name }}
				</option>
			</select>
			<br>
			<button
				class="button primary"
				:disabled="!hostedHPBFilled || loading"
				@click="requestHPBTrial">
				{{ t('spreed', 'Request signaling server trial') }}
			</button>
			<p
				v-if="requestError !== ''"
				class="warning">
				{{ requestError }}
			</p>

			<!-- eslint-disable-next-line vue/no-v-html -->
			<p class="settings-hint additional-top-margin" v-html="disclaimerHint" />
		</div>
		<div v-else>
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
			<p
				v-if="requestError !== ''"
				class="warning">
				{{ requestError }}
			</p>
			<button
				class="button delete"
				:disabled="loading"
				@click="deleteAccount">
				{{ t('spreed', 'Delete the signaling server account') }}
			</button>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'

export default {
	name: 'HostedSignalingServer',

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
			return moment(this.trialAccount.expires).format('L')
		},
		createdDate() {
			return moment(this.trialAccount.created).format('L')
		},
	},

	beforeMount() {
		const state = loadState('spreed', 'hosted_signaling_server_prefill')
		this.hostedHPBNextcloudUrl = state.url
		this.hostedHPBFullName = state.fullName
		this.hostedHPBEmail = state.email
		this.hostedHPBLanguage = state.language
		this.hostedHPBCountry = state.country

		this.trialAccount = loadState('spreed', 'hosted_signaling_server_trial_data')

		const languagesAndCountries = loadState('spreed', 'hosted_signaling_server_language_data')
		this.languages = languagesAndCountries.languages // two lists of {code: "es", name: "Español"} - one is in 'commonLanguages' and one in 'otherLanguages'
		this.countries = languagesAndCountries.countries // list of {code: "France", name: "France"}

		const signaling = loadState('spreed', 'signaling_servers')
		this.showForm = this.trialAccount.length !== 0
			|| signaling.servers.length === 0
	},

	methods: {
		async requestHPBTrial() {
			this.requestError = ''
			this.loading = true
			try {
				const res = await axios.post(generateOcsUrl('apps/spreed/api/v1/hostedsignalingserver/requesttrial'), {
					url: this.hostedHPBNextcloudUrl,
					name: this.hostedHPBFullName,
					email: this.hostedHPBEmail,
					language: this.hostedHPBLanguage,
					country: this.hostedHPBCountry,
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
				this.deleteError = err?.response?.data?.ocs?.data?.message || t('spreed', 'The account could not be deleted. Please try again later.')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
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

.delete {
	background: var(--color-main-background);
	border-color: var(--color-error);
	color: var(--color-error);
}

.delete:hover,
.delete:active {
	background: var(--color-error);
	border-color: var(--color-error) !important;
	color: var(--color-main-background);
}

</style>
