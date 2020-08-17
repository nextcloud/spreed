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
			<div id="matterbridge-header">
				<h3>
					<span>
						{{ t('spreed', 'Bridge with other services') }}
					</span>
					<a class="icon icon-info"
						href="https://github.com/42wim/matterbridge/wiki"
						target="_blank" />
				</h3>
				<p>
					{{ t('spreed', 'You can bridge channels from various instant messaging systems with Matterbridge.') }}
				</p>
			</div>
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
					<BridgePart v-if="myPart"
						:num="0"
						:deletable="false"
						:part="myPart"
						:type="thisRoomType" />
				</li>
				<li v-for="(part, i) in editableParts" :key="i">
					<BridgePart
						:num="i+1"
						:part="part"
						:type="types[part.type]"
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
import BridgePart from './RightSidebar/Bridge/BridgePart'

export default {
	name: 'BridgeSettings',
	components: {
		ActionCheckbox,
		ActionButton,
		Multiselect,
		BridgePart,
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
			types: {
				nctalk: {
					name: t('spreed', 'Nextcloud Talk'),
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'Nextcloud URL'),
							icon: 'icon-link',
						},
						login: {
							type: 'text',
							placeholder: t('spreed', 'Nextcloud user'),
							icon: 'icon-user',
						},
						password: {
							type: 'password',
							placeholder: t('spreed', 'User password'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Talk room'),
							icon: 'icon-group',
						},
					},
				},
				matrix: {
					name: t('spreed', 'Matrix'),
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'Matrix server URL'),
							icon: 'icon-link',
						},
						login: {
							type: 'text',
							placeholder: t('spreed', 'User'),
							icon: 'icon-user',
						},
						password: {
							type: 'password',
							placeholder: t('spreed', 'User password'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Matrix channel'),
							icon: 'icon-group',
						},
					},
				},
				mattermost: {
					name: t('spreed', 'Mattermost'),
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'Mattermost server URL'),
							icon: 'icon-link',
						},
						login: {
							type: 'text',
							placeholder: t('spreed', 'Mattermost user'),
							icon: 'icon-user',
						},
						password: {
							type: 'password',
							placeholder: t('spreed', 'User password'),
							icon: 'icon-category-auth',
						},
						team: {
							type: 'text',
							placeholder: t('spreed', 'Team name'),
							icon: 'icon-group',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Channel name'),
							icon: 'icon-group',
						},
					},
				},
				rocketchat: {
					name: t('spreed', 'Rocket.Chat'),
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'Rocket.Chat server URL'),
							icon: 'icon-link',
						},
						login: {
							type: 'text',
							placeholder: t('spreed', 'User name or e-mail address'),
							icon: 'icon-user',
						},
						password: {
							type: 'password',
							placeholder: t('spreed', 'Password'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Rocket.Chat channel'),
							icon: 'icon-group',
						},
					},
				},
				zulip: {
					name: t('spreed', 'Zulip'),
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'Zulip server URL'),
							icon: 'icon-link',
						},
						login: {
							type: 'text',
							placeholder: t('spreed', 'Bot user name'),
							icon: 'icon-user',
						},
						token: {
							type: 'password',
							placeholder: t('spreed', 'Bot API key'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Zulip channel'),
							icon: 'icon-group',
						},
					},
				},
				slack: {
					name: t('spreed', 'Slack'),
					fields: {
						token: {
							type: 'password',
							placeholder: t('spreed', 'API token'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Slack channel'),
							icon: 'icon-group',
						},
					},
				},
				discord: {
					name: t('spreed', 'Discord'),
					fields: {
						token: {
							type: 'password',
							placeholder: t('spreed', 'API token'),
							icon: 'icon-category-auth',
						},
						server: {
							type: 'text',
							placeholder: t('spreed', 'Server ID or name'),
							icon: 'icon-group',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Channel ID or name'),
							icon: 'icon-group',
						},
					},
				},
				telegram: {
					name: t('spreed', 'Telegram'),
					fields: {
						token: {
							type: 'password',
							placeholder: t('spreed', 'API token'),
							icon: 'icon-category-auth',
						},
						chatid: {
							type: 'text',
							placeholder: t('spreed', 'Chat ID'),
							icon: 'icon-group',
						},
					},
				},
				steam: {
					name: t('spreed', 'Steam'),
					fields: {
						login: {
							type: 'text',
							placeholder: t('spreed', 'Login'),
							icon: 'icon-user',
						},
						password: {
							type: 'password',
							placeholder: t('spreed', 'Password'),
							icon: 'icon-category-auth',
						},
						chatid: {
							type: 'text',
							placeholder: t('spreed', 'Chat ID'),
							icon: 'icon-group',
						},
					},
				},
				irc: {
					name: t('spreed', 'IRC'),
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'IRC server URL'),
							icon: 'icon-link',
						},
						nick: {
							type: 'text',
							placeholder: t('spreed', 'Nickname'),
							icon: 'icon-user',
						},
						password: {
							type: 'password',
							placeholder: t('spreed', 'Password'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'IRC channel'),
							icon: 'icon-group',
						},
					},
				},
				msteams: {
					name: t('spreed', 'Microsoft Teams'),
					fields: {
						tenantid: {
							type: 'text',
							placeholder: t('spreed', 'Tenant ID'),
							icon: 'icon-user',
						},
						clientid: {
							type: 'password',
							placeholder: t('spreed', 'Client ID'),
							icon: 'icon-user',
						},
						teamid: {
							type: 'text',
							placeholder: t('spreed', 'Team ID'),
							icon: 'icon-category-auth',
						},
						threadid: {
							type: 'text',
							placeholder: t('spreed', 'Thread ID'),
							icon: 'icon-group',
						},
					},
				},
				xmpp: {
					name: t('spreed', 'Xmpp/Jabber'),
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'Xmpp/Jabber server URL'),
							icon: 'icon-link',
						},
						muc: {
							type: 'url',
							placeholder: t('spreed', 'MUC server URL'),
							icon: 'icon-link',
						},
						jid: {
							type: 'text',
							placeholder: t('spreed', 'Jabber ID'),
							icon: 'icon-user',
						},
						nick: {
							type: 'text',
							placeholder: t('spreed', 'Nickname'),
							icon: 'icon-user',
						},
						password: {
							type: 'password',
							placeholder: t('spreed', 'Password'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Channel'),
							icon: 'icon-group',
						},
					},
				},
			},
			thisRoomType: {
				name: t('spreed', 'User who connects to this room to relay bridge messages'),
				fields: {
					login: {
						type: 'text',
						placeholder: t('spreed', 'Nextcloud user'),
						icon: 'icon-user',
					},
					password: {
						type: 'password',
						placeholder: t('spreed', 'User password'),
						icon: 'icon-category-auth',
					},
				},
			},
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
		formatedTypes() {
			return Object.keys(this.types).map((k) => {
				const t = this.types[k]
				return {
					displayName: t.name,
					type: k,
				}
			})
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
			const typeKey = this.selectedType.type
			const type = this.types[typeKey]
			const newPart = {
				type: typeKey,
			}
			for (const fieldKey in type.fields) {
				newPart[fieldKey] = ''
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
	align-items: center;
}

#matterbridge-header {
	padding-left: 15px;
}

.icon {
	display: inline-block;
	width: 8%;
}
</style>
