<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import IconInformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import EmptyView from '../components/EmptyView.vue'
</script>

<template>
	<EmptyView
		:name="t('spreed', 'Duplicate session')"
		:description="t('spreed', 'This conversation was opened in another tab. The current server version does not support opening a conversation in multiple tabs.')">
		<template #icon>
			<!-- TODO: use information-slab-symbol after update to MDI 7 -->
			<IconInformationOutline />
		</template>
	</EmptyView>
</template>
