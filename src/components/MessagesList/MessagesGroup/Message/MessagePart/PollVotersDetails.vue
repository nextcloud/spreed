`<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
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
	<NcPopover trigger="hover">
		<button slot="trigger"
			tabindex="0"
			class="poll-voters-details">
			<AvatarWrapper v-for="(item, index) in details"
				:id="item.actorId"
				:key="index"
				:source="item.actorType"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:name="item.actorDisplayName"
				:condensed="true"
				:size="24" />
		</button>
		<div class="poll-voters-details__popover" tabindex="0">
			<div v-for="(item, index) in details"
				:key="index"
				class="poll-voters-details__list-item">
				<AvatarWrapper :id="item.actorId"
					:key="index"
					:source="item.actorType"
					:disable-menu="true"
					:show-user-status="false"
					:name="item.actorDisplayName"
					:condensed="true"
					:size="24" />
				<p class="poll-voters-details__display-name">
					{{ item.actorDisplayName }}
				</p>
			</div>
		</div>
	</NcPopover>
</template>

<script>
import AvatarWrapper from '../../../../AvatarWrapper/AvatarWrapper.vue'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

export default {

	name: 'PollVotersDetails',

	components: {
		AvatarWrapper,
		NcPopover,
	},

	props: {
		details: {
			type: Array,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>

.poll-voters-details {
	display: flex;
	background: none;
	border: none;
	padding: 0;
	margin-right: 8px;

	&__popover{
		padding: 8px;
		max-height: 400px;
		overflow-y: scroll;
	}

	&__display-name {
			margin-left: 4px;
		}

	&__list-item {
		display: flex;
		align-items: center;
		height: 32px;
		margin-bottom: var(--margin-small);
		min-width: 150px;
		justify-content: flex-start;
		align-items: center;
	}
}

</style>
