<!--
  - @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div>
		<h3>{{ t('spreed', 'Dial-in information') }}</h3>

		<p>{{ dialInInfo }}</p>
		<p>{{ meetingIdLabel }}</p>
		<p>{{ attendeePinLabel }}</p>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'SipSettings',

	props: {
		attendeePin: {
			type: String,
			required: true,
		},
		meetingId: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			dialInInfo: loadState('talk', 'sip_dialin_info'),
		}
	},

	computed: {
		meetingIdLabel() {
			return t('spreed', 'Meeting ID: {meetingId}', {
				meetingId: this.meetingId,
			})
		},
		attendeePinLabel() {
			return t('spreed', 'Your PIN: {attendeePin}', {
				attendeePin: this.attendeePin,
			})
		},
	},
}
</script>
