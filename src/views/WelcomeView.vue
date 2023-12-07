<template>
	<EmptyView :name="t('spreed', 'Join a conversation or start a new one')"
		:description="t('spreed', 'Say hi to your friends and colleagues!')">
		<template #icon>
			<NcIconSvgWrapper :svg="IconTalk" />
		</template>
	</EmptyView>
</template>

<script>
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'

import EmptyView from '../components/EmptyView.vue'

import IconTalk from '../../img/app-dark.svg?raw'

export default {
	name: 'WelcomeView',

	components: {
		EmptyView,
		NcIconSvgWrapper
	},
	setup() {
		return {
			IconTalk,
		}
	}
}
</script>
