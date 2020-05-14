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

		<div>
			<h4>{{ t('spreed', 'URL of this Nextcloud instance') }}</h4>
			<input
				v-model="hostedHPBNextcloudUrl"
				type="text"
				name="hosted_hpb_nextcloud_url"
				placeholder="https://cloud.example.org/"
				:aria-label="t('spreed', 'URL of this Nextcloud instance')">
			<h4>{{ t('spreed', 'Full name of the user requesting the trail') }}</h4>
			<input
				v-model="hostedHPBFullName"
				type="text"
				name="full_name"
				placeholder="Jane Doe"
				:aria-label="t('spreed', 'Name of the user requesting the trail')">
			<h4>{{ t('spreed', 'E-mail of the user') }}</h4>
			<input
				v-model="hostedHPBEmail"
				type="text"
				name="hosted_hpb_email"
				placeholder="jane@example.org"
				:aria-label="t('spreed', 'E-mail of the user')">
			<h4>{{ t('spreed', 'Language') }}</h4>
			<input
				v-model="hostedHPBLanguage"
				type="text"
				name="hosted_hpb_language"
				placeholder="de"
				:aria-label="t('spreed', 'Language')">
			<h4>{{ t('spreed', 'Country') }}</h4>
			<input
				v-model="hostedHPBCountry"
				type="text"
				name="hosted_hpb_country"
				placeholder="US"
				:aria-label="t('spreed', 'Country')">
			<br>
			<button class="button primary"
				:disabled="!hostedHPBFilled"
				@click="requestHPBTrial">
				{{ t('spreed', 'Request signaling server trail') }}
			</button>
		</div>
		<p class="settings-hint additional-top-margin" v-html="disclaimerHint" />
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'HostedSignalingServer',

	data() {
		return {
			hostedHPBNextcloudUrl: '',
			hostedHPBFullName: '',
			hostedHPBEmail: '',
			hostedHPBLanguage: '',
			hostedHPBCountry: '',
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
	},

	beforeMount() {
		const state = loadState('talk', 'hosted_signaling_server_prefill')
		this.hostedHPBNextcloudUrl = state.url
		this.hostedHPBFullName = state.fullName
		this.hostedHPBEmail = state.email
		this.hostedHPBLanguage = state.language
		this.hostedHPBCountry = state.country
	},

	methods: {
		requestHPBTrail() {
			console.log('abc')
		},
	},
}
</script>

<style lang="scss" scoped>
.additional-top-margin {
	margin-top: 10px;
}

</style>
