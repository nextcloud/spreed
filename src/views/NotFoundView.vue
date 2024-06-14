<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<EmptyView :name="t('spreed', 'The conversation does not exist')"
		:description="t('spreed', 'Join a conversation or start a new one!')">
		<template #icon>
			<IconMagnify />
		</template>
	</EmptyView>
</template>

<script>
import IconMagnify from 'vue-material-design-icons/Magnify.vue'

import { t } from '@nextcloud/l10n'

import EmptyView from '../components/EmptyView.vue'

export default {
	name: 'NotFoundView',

	components: {
		EmptyView,
		IconMagnify,
	},

	methods: {
		t,
	},
}
</script>
