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
	<div class="matterbridge-settings">
		<div v-if="loading" class="loading" />
		<div v-show="!loading">
			<h3>
				<span class="icon icon-category-integration" />
				<p>{{ t('spreed', 'Bridge with other services') }}</p>
			</h3>
			<div id="matterbridge-header">
				<p>
					{{ t('spreed', 'You can bridge channels from various instant messaging systems with Matterbridge.') }}
					<a href="https://github.com/42wim/matterbridge/wiki" target="_blank" rel="noopener">
						<span class="icon icon-external" />
						{{ t('spreed', 'More info on Matterbridge') }}
					</a>
				</p>
			</div>
			<div class="basic-settings">
				<div class="add-part-wrapper">
					<span class="icon icon-add" />
					<Multiselect
						ref="partMultiselect"
						v-model="selectedType"
						label="displayName"
						track-by="type"
						:placeholder="newPartPlaceholder"
						:options="formatedTypes"
						:user-select="true"
						:internal-search="true"
						@input="clickAddPart">
						<template #option="{option}">
							<span :class="option.icon" />
							{{ option.displayName }}
						</template>
					</Multiselect>
				</div>
				<ActionButton
					v-if="canSave"
					icon="icon-checkmark"
					@click="onSave">
					{{ t('spreed', 'Save') }}
				</ActionButton>
				<div class="enable-switch-line">
					<ActionCheckbox
						:token="token"
						:checked="enabled"
						@update:checked="onEnabled">
						{{ enabled ? t('spreed', 'Enabled') : t('spreed', 'Disabled') }}
						({{ processStateText }})
					</ActionCheckbox>
					<button
						v-tooltip.top="{ content: t('spreed', 'Show matterbridge log') }"
						class="icon icon-edit"
						@click="showLogContent" />
					<Modal v-if="logModal"
						@close="closeLogModal">
						<div class="modal__content">
							<textarea v-model="processLog" class="log-content" />
						</div>
					</Modal>
				</div>
			</div>
			<ul>
				<li v-for="(part, i) in parts" :key="part.type + i">
					<BridgePart
						:num="i+1"
						:part="part"
						:type="types[part.type]"
						:editing="part.editing"
						@edit-clicked="onEditClicked(i)"
						@delete-part="onDelete(i)" />
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

import Vue from 'vue'
import { Tooltip } from '@nextcloud/vue'
Vue.directive('tooltip', Tooltip)

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
			canSave: false,
			processRunning: null,
			processLog: '',
			logModal: false,
			stateLoop: null,
			types: {
				nctalk: {
					name: 'Nextcloud Talk',
					iconClass: 'icon-nctalk',
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
					iconClass: 'icon-matrix',
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
					iconClass: 'icon-mattermost',
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
					iconClass: 'icon-rocketchat',
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
					iconClass: 'icon-zulip',
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
					iconClass: 'icon-slack',
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
					iconClass: 'icon-discord',
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
					iconClass: 'icon-telegram',
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
					iconClass: 'icon-steam',
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
					iconClass: 'icon-irc',
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
					iconClass: 'icon-msteams',
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
					iconClass: 'icon-xmpp',
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
			newPartPlaceholder: t('spreed', 'Add new bridged channel to current room'),
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
					icon: t.iconClass + ' icon-multiselect-service',
				}
			})
		},
		processStateText() {
			return this.processRunning === null
				? t('spreed', 'unknown state')
				: this.processRunning
					? t('spreed', 'running')
					: this.enabled
						? t('spreed', 'not running, check Matterbridge log')
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
				editing: true,
			}
			for (const fieldKey in type.fields) {
				newPart[fieldKey] = ''
			}
			this.parts.unshift(newPart)
			this.selectedType = null
			this.canSave = true
		},
		onDelete(i) {
			this.parts.splice(i, 1)
			this.canSave = true
		},
		onEditClicked(i) {
			this.parts[i].editing = !this.parts[i].editing
			this.canSave = true
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
			this.parts.forEach(part => {
				part.editing = false
			})
			try {
				const result = await editBridge(this.token, this.enabled, this.parts)
				this.processLog = result.data.ocs.data.log
				this.processRunning = result.data.ocs.data.running
				showSuccess(t('spreed', 'Bridge saved'))
				this.canSave = false
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
::v-deep .icon-slack {
	mask: url('./../../../../img/bridge-services/slack.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/slack.svg') no-repeat;
}

::v-deep .icon-matrix {
	mask: url('./../../../../img/bridge-services/matrix.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/matrix.svg') no-repeat;
}

::v-deep .icon-nctalk {
	mask: url('./../../../../img/app-dark.svg') no-repeat;
	-webkit-mask: url('./../../../../img/app-dark.svg') no-repeat;
}

::v-deep .icon-mattermost {
	mask: url('./../../../../img/bridge-services/mattermost.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/mattermost.svg') no-repeat;
}

::v-deep .icon-rocketchat {
	mask: url('./../../../../img/bridge-services/rocketchat.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/rocketchat.svg') no-repeat;
}

::v-deep .icon-zulip {
	mask: url('./../../../../img/bridge-services/zulip.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/zulip.svg') no-repeat;
}

::v-deep .icon-discord {
	mask: url('./../../../../img/bridge-services/discord.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/discord.svg') no-repeat;
}

::v-deep .icon-telegram {
	mask: url('./../../../../img/bridge-services/telegram.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/telegram.svg') no-repeat;
}

::v-deep .icon-steam {
	mask: url('./../../../../img/bridge-services/steam.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/steam.svg') no-repeat;
}

::v-deep .icon-irc {
	mask: url('./../../../../img/bridge-services/irc.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/irc.svg') no-repeat;
}

::v-deep .icon-msteams {
	mask: url('./../../../../img/bridge-services/msteams.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/msteams.svg') no-repeat;
}

::v-deep .icon-xmpp {
	mask: url('./../../../../img/bridge-services/xmpp.svg') no-repeat;
	-webkit-mask: url('./../../../../img/bridge-services/xmpp.svg') no-repeat;
}

::v-deep .icon-multiselect-service {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask-position: center;
	mask-size: 16px auto;
	-webkit-mask-position: center;
	-webkit-mask-size: 16px auto;
	min-width: 32px !important;
	min-height: 32px !important;
}

.matterbridge-settings {
	.loading {
		margin-top: 30px;
	}

	h3 {
		font-weight: bold;
		padding: 0;
		height: 44px;
		display: flex;

		p {
			margin-top: auto;
			margin-bottom: auto;
		}

		.icon {
			display: inline-block;
			width: 40px;
		}
		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	#matterbridge-header {
		padding: 0 0 10px 0;

		p {
			padding-left: 40px;
			color: var(--color-text-maxcontrast);

			a:hover,
			a:focus {
				border-bottom: 2px solid var(--color-text-maxcontrast);
			}

			a .icon {
				display: inline-block;
				margin-bottom: -3px;
			}
		}
	}

	.basic-settings {
		.action {
			list-style: none;
		}
		button {
			width: calc(100% - 40px);
			margin-left: 40px;
		}
		.multiselect {
			width: calc(100% - 44px);
		}
		.icon {
			display: inline-block;
			width: 40px;
			height: 34px;
			background-position: 14px center;
		}
		.add-part-wrapper {
			margin-top: 5px;
		}
		.enable-switch-line {
			display: flex;

			.action {
				flex-grow: 1;
			}
			button {
				opacity: 0.5;
				width: 44px;
				height: 44px;
				border-radius: var(--border-radius-pill);
				background-color: transparent;
				border: none;
				margin: 0;

				&:hover,
				&:focus {
					opacity: 1;
					background-color: var(--color-background-hover);
				}
			}
		}
	}

	ul {
		margin-bottom: 64px;
	}
}

.log-content {
	width: 600px;
	height: 400px;
}
</style>
