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
					{{ t('spreed', 'Bridge with other services') }}
				</h3>
				<p>
					{{ t('spreed', 'You can bridge channels from various instant messaging systems with Matterbridge.') }}
					<a href="https://github.com/42wim/matterbridge/wiki" target="_blank" rel="noopener">
						{{ t('spreed', 'More info on Matterbridge.') }}
					</a>
				</p>
			</div>
			<div class="basic-settings">
				<ActionCheckbox
					:token="token"
					:checked="enabled"
					@update:checked="onEnabled">
					{{ t('spreed', 'Enabled') }}
					({{ processStateText }})
				</ActionCheckbox>
				<button class="" @click="showLogContent">
					{{ t('spreed', 'Show matterbridge log') }}
				</button>
				<Modal v-if="logModal"
					@close="closeLogModal">
					<div class="modal__content">
						<textarea v-model="processLog" class="log-content" />
					</div>
				</Modal>
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
	getBridgeProcessState,
} from '../../../services/matterbridgeService'
import { showSuccess } from '@nextcloud/dialogs'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import BridgePart from './BridgePart'

export default {
	name: 'MatterbridgeSettings',
	components: {
		ActionCheckbox,
		ActionButton,
		Multiselect,
		BridgePart,
		Modal,
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
			processRunning: null,
			processLog: '',
			logModal: false,
			stateLoop: null,
			types: {
				nctalk: {
					name: 'Nextcloud Talk',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Section-Nextcloud-Talk-%28basic%29',
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
							placeholder: t('spreed', 'Talk conversation'),
							icon: 'icon-group',
						},
					},
				},
				matrix: {
					name: 'Matrix',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#matrix',
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
					name: 'Mattermost',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#mattermost',
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
					name: 'Rocket.Chat',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#rocketchat',
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
					name: 'Zulip',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#zulip',
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
					name: 'Slack',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Slack-bot-setup',
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
					name: 'Discord',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Discord-bot-setup',
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
					name: 'Telegram',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#telegram',
					fields: {
						token: {
							type: 'password',
							placeholder: t('spreed', 'API token'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Channel'),
							icon: 'icon-group',
						},
					},
				},
				steam: {
					name: 'Steam',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#steam',
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
					name: 'IRC',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#irc',
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'IRC server URL (e.g. chat.freenode.net:6667)'),
							icon: 'icon-link',
						},
						nick: {
							type: 'text',
							placeholder: t('spreed', 'Nickname'),
							icon: 'icon-user',
						},
						password: {
							type: 'password',
							placeholder: t('spreed', 'Connection password'),
							icon: 'icon-category-auth',
						},
						channel: {
							type: 'text',
							placeholder: t('spreed', 'IRC channel'),
							icon: 'icon-group',
						},
						channelpassword: {
							type: 'password',
							placeholder: t('spreed', 'Channel password'),
							icon: 'icon-category-auth',
						},
						nickservnick: {
							type: 'text',
							placeholder: t('spreed', 'NickServ nickname'),
							icon: 'icon-user',
						},
						nickservpassword: {
							type: 'password',
							placeholder: t('spreed', 'NickServ password'),
							icon: 'icon-category-auth',
						},
						usetls: {
							type: 'checkbox',
							labelText: t('spreed', 'Use TLS'),
						},
						usesasl: {
							type: 'checkbox',
							labelText: t('spreed', 'Use SASL'),
						},
						skiptls: {
							type: 'checkbox',
							labelText: t('spreed', 'Skip TLS verification'),
						},
					},
				},
				msteams: {
					name: 'Microsoft Teams',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/MS-Teams-setup',
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
					name: 'XMPP/Jabber',
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#xmpp',
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'XMPP/Jabber server URL'),
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
			newPartPlaceholder: t('spreed', 'Add new bridged channel'),
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
			this.relaunchStateLoop(token)
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
		processStateText() {
			return this.processRunning === null
				? t('spreed', 'unknown state')
				: this.processRunning
					? t('spreed', 'running')
					: t('spreed', 'not running')
		},
	},

	beforeMount() {
	},

	beforeDestroy() {
	},

	methods: {
		relaunchStateLoop(token) {
			// start loop to periodically get bridge state
			clearInterval(this.stateLoop)
			this.stateLoop = setInterval(() => this.getBridgeProcessState(token), 60000)
		},
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
		},
		onEnabled(checked) {
			this.enabled = checked
			this.onSave()
		},
		onSave() {
			this.editBridge(this.token, this.enabled, this.parts)
		},
		async getBridge(token) {
			this.loading = true
			try {
				const result = await getBridge(token)
				const bridge = result.data.ocs.data
				this.enabled = bridge.enabled
				this.parts = bridge.parts
				this.processLog = bridge.log
				this.processRunning = bridge.running
			} catch (exception) {
				console.debug(exception)
			}
			this.loading = false
		},
		async editBridge() {
			this.loading = true
			try {
				const result = await editBridge(this.token, this.enabled, this.parts)
				this.processLog = result.data.ocs.data.log
				this.processRunning = result.data.ocs.data.running
				showSuccess(t('spreed', 'Bridge saved'))
			} catch (exception) {
				console.debug(exception)
			}
			this.loading = false
		},
		async getBridgeProcessState(token) {
			try {
				const result = await getBridgeProcessState(token)
				this.processLog = result.data.ocs.data.log
				this.processRunning = result.data.ocs.data.running
			} catch (exception) {
				console.debug(exception)
			}
		},
		showLogContent() {
			this.getBridgeProcessState(this.token)
			this.logModal = true
		},
		closeLogModal() {
			this.logModal = false
		},
	},
}
</script>

<style lang="scss" scoped>
.loading {
	margin-top: 30px;
}

#matterbridge-header {
	padding-left: 40px;
	padding-top: 40px;

	h3 {
		font-weight: bold;
	}

	p {
		color: var(--color-text-maxcontrast);

		a:hover,
		a:focus {
			border-bottom: 2px solid var(--color-text-maxcontrast);
		}
	}
}

.basic-settings {
	button,
	.multiselect {
		width: calc(100% - 40px);
		margin-left: 40px;
	}
}

ul {
	margin-bottom: 64px;
}

.log-content {
	width: 600px;
	height: 400px;
}
</style>
