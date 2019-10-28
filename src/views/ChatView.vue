<template>
	<div class="chatview">
		<MessagesList :token="token" />
		<NewMessageForm />
	</div>
</template>

<script>
import MessagesList from '../components/MessagesList/MessagesList'
import NewMessageForm from '../components/NewMessageForm/NewMessageForm'

export default {
	name: 'ChatView',
	components: {
		MessagesList,
		NewMessageForm,
	},
	props: {
		token: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.chatview {
	height: 100%;
	display: flex;
	flex-grow: 1;
	flex-direction: column;
	align-content: space-between;
}
</style>
