<!--
  - @copyright Copyright (c) 2023 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="background-editor">
		<button class="background-editor__element">
			no background
		</button>
		<button class="background-editor__element">
			blur
		</button>
		<button class="background-editor__element">
			custom
		</button>
	</div>
</template>

<script>
export default {
	name: 'VideoBackgroundEditor',
}
</script>

<style scoped lang="scss">
.background-editor {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	gap: calc(var(--default-grid-baseline) * 4);

	&__element {
		border-radius: var(--border-radius-large);
		background-color: transparent;
		border: none;
		margin: 0;
	 }
}
</style>
