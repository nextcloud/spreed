<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="chatView">
		<MessagesList :token="token" />
		<NewMessageForm />
	</div>
</template>

<script>
import MessagesList from './MessagesList/MessagesList'
import NewMessageForm from './NewMessageForm/NewMessageForm'

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
.chatView {
	height: 100%;

	display: flex;
	flex-direction: column;
	flex-grow: 1;
}
</style>
