<template>
	<EmptyView :name="t('spreed', 'The conversation does not exist')"
		:description="t('spreed', 'Join a conversation or start a new one!')">
		<template #icon>
			<IconMagnify />
		</template>
	</EmptyView>
</template>

<script>
import EmptyView from '../components/EmptyView.vue'
import IconMagnify from 'vue-material-design-icons/Magnify.vue'

export default {
	name: 'NotFoundView',

	components: {
		EmptyView,
		IconMagnify,
	},
}
</script>
