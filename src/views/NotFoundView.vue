<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>
import { t } from '@nextcloud/l10n'
import IconMagnify from 'vue-material-design-icons/Magnify.vue'
import EmptyView from '../components/EmptyView.vue'
</script>

<template>
	<EmptyView :name="t('spreed', 'The conversation does not exist')"
		:description="t('spreed', 'Join a conversation or start a new one!')">
		<template #icon>
			<IconMagnify />
		</template>
	</EmptyView>
</template>
