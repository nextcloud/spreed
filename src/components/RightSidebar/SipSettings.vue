<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
import { t } from '@nextcloud/l10n'

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
		t,
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
