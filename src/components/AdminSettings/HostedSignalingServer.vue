<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
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
	<div id="hosted_signaling_server" class="videocalls section">
		<h2>
			{{ t('spreed', 'Hosted signaling server') }}
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'Our partner Struktur AG provides a service where a hosted signaling server can be requested. For this you only need to fill out the form below and your Nextcloud will request it. Once the server is set up for you the credentials will be filled automatically.') }}
		</p>

		<div v-if="!trailAccount.status">
			<h4>{{ t('spreed', 'URL of this Nextcloud instance') }}</h4>
			<input
				v-model="hostedHPBNextcloudUrl"
				type="text"
				name="hosted_hpb_nextcloud_url"
				placeholder="https://cloud.example.org/"
				:disabled="loading"
				:aria-label="t('spreed', 'URL of this Nextcloud instance')">
			<h4>{{ t('spreed', 'Full name of the user requesting the trail') }}</h4>
			<input
				v-model="hostedHPBFullName"
				type="text"
				name="full_name"
				placeholder="Jane Doe"
				:disabled="loading"
				:aria-label="t('spreed', 'Name of the user requesting the trail')">
			<h4>{{ t('spreed', 'E-mail of the user') }}</h4>
			<input
				v-model="hostedHPBEmail"
				type="text"
				name="hosted_hpb_email"
				placeholder="jane@example.org"
				:disabled="loading"
				:aria-label="t('spreed', 'E-mail of the user')">
			<h4>{{ t('spreed', 'Language') }}</h4>
			<select
				v-model="hostedHPBLanguage"
				name="hosted_hpb_language"
				:placeholder="t('spreed', 'Language')"
				:disabled="loading"
				:aria-label="t('spreed', 'Language')">
				<option v-for="l in languages.commonlanguages" :key="l.code" :value="l.code">
					{{ l.name }}
				</option>
				<optgroup label="––––––––––" />
				<option v-for="l in languages.languages" :key="l.code" :value="l.code">
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
			<button class="button primary"
				:disabled="!hostedHPBFilled || loading"
				@click="requestHPBTrial">
				{{ t('spreed', 'Request signaling server trail') }}
			</button>
			<p v-if="requestError !== ''"
				class="warning">
				{{ requestError }}
			</p>

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
				<tr v-if="trailAccount.limits">
					<td>{{ t('spreed', 'Limits') }}</td>
					<td>{{ n('spreed', '%n user', '%n users', trailAccount.limits.users) }}</td>
				</tr>
			</table>
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
			trailAccount: [],
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
			return t('spreed', 'By clicking the button above we send the information in the form to the servers of Struktur AG. You can find further information at {linkstart}spreed.eu{linkend}.')
				.replace('{linkstart}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://www.spreed.eu/nextcloud-talk-high-performance-backend/">')
				.replace('{linkend}', ' ↗</a>')
		},
		translatedStatus() {
			switch (this.trailAccount.status) {
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
			return moment(this.trailAccount.expires).format('L')
		},
		createdDate() {
			return moment(this.trailAccount.created).format('L')
		},
	},

	beforeMount() {
		const state = loadState('talk', 'hosted_signaling_server_prefill')
		this.hostedHPBNextcloudUrl = state.url
		this.hostedHPBFullName = state.fullName
		this.hostedHPBEmail = state.email
		this.hostedHPBLanguage = state.language
		this.hostedHPBCountry = state.country

		this.trailAccount = loadState('talk', 'hosted_signaling_server_trial_data')

		const languagesAndCountries = loadState('talk', 'hosted_signaling_server_language_data')
		this.languages = languagesAndCountries['languages'] // two lists of {code: "es", name: "Español"} - one is in 'commonlanguages' and one in 'languages'
		this.countries = languagesAndCountries['countries'] // list of {code: "France", name: "France"}
	},

	methods: {
		async requestHPBTrial() {
			this.requestError = ''
			this.loading = true
			try {
				const res = await axios.post(generateOcsUrl('apps/spreed/api/v1/hostedsignalingserver', 2) + 'requesttrial', {
					url: this.hostedHPBNextcloudUrl,
					name: this.hostedHPBFullName,
					email: this.hostedHPBEmail,
					language: this.hostedHPBLanguage,
					country: this.hostedHPBCountry,
				})

				this.trailAccount = res.data.ocs.data
			} catch (err) {
				this.requestError = err?.response?.data?.ocs?.data?.message || t('spreed', 'The trial could not be requested. Please try again later.')
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

</style>
