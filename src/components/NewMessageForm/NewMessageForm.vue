<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="new-message">
		<form class="new-message-form">
			<button class="new-message-form-button-attachfile" />
			<button class="new-message-form-button-attachfile" />
			<input v-model="message">
			<button class="new-message-form-button-attachfile" />
			<button type="submit" class="new-message-form-button-attachfile" />
		</form>
	</div>
</template>

<script>
export default {
	name: 'NewMessageForm',
	data: function() {
		return {
			message: ''
		}
	}
}
</script>

<style lang="scss" scoped>
.new-message {
    height: 60px;
    border-top: 1px solid lightgray;
    position: sticky;
    position: -webkit-sticky;
    width: 100%;
    bottom: 0;
    padding: 5px;
    background-color: white;
    &-form {
        display: flex;
        align-items: center;
        & input {
            flex-grow: 1;
        }
    }
}
</style>
