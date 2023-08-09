<template>
	<div id="emptycontent">
		<div id="emptycontent-icon" class="icon-search" />
		<h2>{{ t('spreed', 'The app is too old and no longer supported by this server.') }}</h2>
		<p class="emptycontent-additional">
			{{ t('spreed', 'Update is required.') }}
		</p>

		<NcButton class="browser-button"
			type="primary"
			:href="browserLink"
			target="_blank">
			{{ t('spreed', 'Continue in Web-Browser') }}
		</NcButton>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'UpgradeRequiredView',

	components: {
		NcButton,
	},

	computed: {
		browserLink() {
			return generateUrl('apps/spreed')
		},
	},
}
</script>

<style lang="scss" scoped>
.browser-button {
	margin: var(--default-grid-baseline) auto 0;
}
</style>
