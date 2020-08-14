<!--
  - @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author Julien Veyssier <eneiluj@posteo.net>
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
	<div>
		<div v-if="loading" class="loading" />
		<div v-show="!loading">
			<div class="basic-settings">
				<ActionCheckbox
					:token="token"
					:checked="enabled"
					@update:checked="onEnabled">
					{{ t('spreed', 'Enabled') }}
				</ActionCheckbox>
				<Multiselect
					ref="partMultiselect"
					v-model="selectedType"
					label="displayName"
					track-by="type"
					:placeholder="newPartPlaceholder"
					:options="formatedTypes"
					:internal-search="true"
					@input="clickAddPart" />
				<ActionButton
					icon="icon-checkmark"
					@click="onSave">
					{{ t('spreed', 'Save') }}
				</ActionButton>
			</div>
			<ul>
				<li>
					<hr>
					<TalkBridgePart v-if="myPart"
						:num="0"
						:part="myPart" />
				</li>
				<li v-for="(part, i) in editableParts" :key="i">
					<hr>
					<TalkBridgePart v-if="part.type === 'nctalk'"
						:num="i+1"
						:part="part"
						@deletePart="onDelete(i)" />
					<MattermostBridgePart v-if="part.type === 'mattermost'"
						:num="i+1"
						:part="part"
						@deletePart="onDelete(i)" />
					<MatrixBridgePart v-if="part.type === 'matrix'"
						:num="i+1"
						:part="part"
						@deletePart="onDelete(i)" />
				</li>
			</ul>
		</div>
	</div>
</template>

<script>
import {
	editBridge,
	getBridge,
} from '../services/bridgeService'
import { showSuccess } from '@nextcloud/dialogs'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import TalkBridgePart from './RightSidebar/Bridge/TalkBridgePart'
import MatrixBridgePart from './RightSidebar/Bridge/MatrixBridgePart'
import MattermostBridgePart from './RightSidebar/Bridge/MattermostBridgePart'

export default {
	name: 'BridgeSettings',
	components: {
		ActionCheckbox,
		ActionButton,
		Multiselect,
		TalkBridgePart,
		MatrixBridgePart,
		MattermostBridgePart,
	},

	mixins: [
	],

	props: {
	},

	data() {
		return {
			enabled: false,
			parts: [],
			loading: false,
			formatedTypes: [
				{
					displayName: t('spreed', 'Nextcloud Talk'),
					type: 'nctalk',
				},
				{
					displayName: t('spreed', 'Matrix'),
					type: 'matrix',
				},
				{
					displayName: t('spreed', 'Mattermost'),
					type: 'mattermost',
				},
			],
			newPartPlaceholder: t('spreed', 'Add new bridge'),
			selectedType: null,
		}
	},

	computed: {
		show() {
			return this.$store.getters.getSidebarStatus
		},
		opened() {
			return !!this.token && this.show
		},
		token() {
			const token = this.$store.getters.getToken()
			this.getBridge(token)
			return token
		},
		editableParts() {
			return this.parts.filter((p) => {
				return p.type !== 'nctalk' || p.channel !== this.token
			})
		},
		myPart() {
			return this.parts.find((p) => {
				return p.type === 'nctalk' && p.channel === this.token
			})
		},
	},

	beforeMount() {
	},

	beforeDestroy() {
	},

	methods: {
		clickAddPart() {
			const type = this.selectedType.type
			const newPart = {
				type,
				server: '',
				login: '',
				password: '',
				channel: '',
			}
			if (type === 'mattermost') {
				newPart.team = ''
			}
			this.parts.unshift(newPart)
			this.selectedType = null
		},
		onDelete(i) {
			this.parts.splice(i, 1)
			this.onSave()
		},
		onEnabled(checked) {
			this.enabled = checked
			this.onSave()
		},
		onSave() {
			console.debug(this.parts)
			this.editBridge(this.token, this.enabled, this.parts)
		},
		async getBridge(token) {
			this.loading = true
			try {
				const result = await getBridge(token)
				console.debug(result)
				const bridge = result.data.ocs.data
				this.enabled = bridge.enabled
				this.parts = bridge.parts
			} catch (exception) {
				console.debug(exception)
			}
			this.loading = false
		},
		async editBridge() {
			this.loading = true
			try {
				await editBridge(this.token, this.enabled, this.parts)
				showSuccess(t('spreed', 'Bridge saved'))
			} catch (exception) {
				console.debug(exception)
			}
			this.loading = false
		},
	},
}
</script>

<style scoped>
.loading {
	margin-top: 30px;
}

.basic-settings {
	display: flex;
	list-style: none;
}
</style>
