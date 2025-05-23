<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import { t } from '@nextcloud/l10n'
import Octagon from 'vue-material-design-icons/Octagon.vue'
import EmptyView from '../components/EmptyView.vue'
</script>

<template>
	<EmptyView :name="t('spreed', 'You do not have permissions to access this conversation.')"
		:description="t('spreed', 'Join a different conversation or start a new one.')">
		<template #icon>
			<Octagon />
		</template>
	</EmptyView>
</template>
