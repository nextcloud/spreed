<!--
  - @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  -
  - @license AGPL-3.0-or-later
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
	<div class="sip-settings">
		<h3>{{ t('spreed', 'Dial-in information') }}</h3>
		<p>{{ dialInInfo }}</p>

		<h3>{{ t('spreed', 'Meeting ID') }}</h3>
		<p>{{ readableNumber(meetingId) }}</p>

		<h3>{{ t('spreed', 'Your PIN') }}</h3>
		<p>{{ readableNumber(attendeePin) }}</p>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'

import { readableNumber } from '../../utils/readableNumber.ts'

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
			dialInInfo: loadState('spreed', 'sip_dialin_info'),
		}
	},

	methods: {
		readableNumber,
	}
}
</script>

<style lang="scss" scoped>
.sip-settings {
	h3 {
		margin-bottom: 6px;
		font-weight: bold;
	}

	p {
		white-space: pre-line;
	}
}
</style>
