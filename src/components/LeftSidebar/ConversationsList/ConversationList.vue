<template>
	<ul>
		<!-- TODO: Vue3: remove redundant ul wrapper here -->
		<Conversation v-for="item in conversations"
			:key="item.id"
			:ref="`conversation-${item.token}`"
			:item="item"
			:is-search-result="isSearchResult" />
	</ul>
</template>

<script>
import Conversation from './Conversation.vue'

export default {
	name: 'ConversationList',

	components: { Conversation },

	props: {
		conversations: {
			type: Array,
			required: true,
		},

		isSearchResult: {
			type: Boolean,
			required: false,
			default: false,
		},
	},
}
</script>

<style scoped lang="scss">

</style>
