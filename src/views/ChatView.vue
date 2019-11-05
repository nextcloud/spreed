<template>
	<div class="chatview">
		<TopBar />
		<MessagesList :token="token" />
		<NewMessageForm />
	</div>
</template>

<script>
import MessagesList from '../components/MessagesList/MessagesList'
import NewMessageForm from '../components/NewMessageForm/NewMessageForm'
import TopBar from '../components/TopBar/TopBar'
export default {
	name: 'ChatView',
	components: {
		MessagesList,
		NewMessageForm,
		TopBar
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
