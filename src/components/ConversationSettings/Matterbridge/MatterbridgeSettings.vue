<!--
  - @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author Julien Veyssier <eneiluj@posteo.net>
  -
  - @license AGPL-3.0-or-later
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
				<div v-show="!enabled"
					class="add-part-wrapper">
					<Plus class="icon" :size="20" />
					<NcSelect v-model="selectedType"
						label="displayName"
						:aria-label-combobox="t('spreed', 'Messaging systems')"
						:placeholder="newPartPlaceholder"
						:options="formatedTypes"
						@input="clickAddPart">
						<template #option="option">
							<img class="icon-multiselect-service"
								:src="option.iconUrl"
								alt="">
							{{ option.displayName }}
						</template>
					</NcSelect>
				</div>
				<div v-show="parts.length > 0"
					class="enable-switch-line">
					<NcCheckboxRadioSwitch :checked="enabled"
						type="switch"
						@update:checked="onEnabled">
						{{ t('spreed', 'Enable bridge') }}
						({{ processStateText }})
					</NcCheckboxRadioSwitch>
					<NcButton v-if="enabled"
						v-tooltip.top="{ content: t('spreed', 'Show Matterbridge log') }"
						type="tertiary"
						:aria-label="t('spreed', 'Show Matterbridge log')"
						@click="showLogContent">
						<template #icon>
							<Message :size="20" />
						</template>
					</NcButton>
					<NcModal v-if="logModal"
						container=".matterbridge-settings"
						@close="closeLogModal">
						<div class="modal__content">
							<NcTextArea :value="processLog"
								class="log-content"
								:label="t('spreed', 'Log content')"
								rows="29"
								readonly
								resize="vertical" />
						</div>
					</NcModal>
				</div>
			</div>
			<ul>
				<li v-for="(part, i) in parts" :key="part.type + i">
					<BridgePart :num="i+1"
						:part="part"
						:type="types[part.type]"
						:editing="part.editing"
						:editable="!enabled"
						container=".matterbridge-settings"
						@edit-clicked="onEditClicked(i)"
						@delete-part="onDelete(i)" />
				</li>
			</ul>
		</div>
	</div>
</template>

<script>
import Vue from 'vue'

import Message from 'vue-material-design-icons/Message.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { imagePath } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'

import BridgePart from './BridgePart.vue'

import {
	editBridge,
	getBridge,
	getBridgeProcessState,
} from '../../../services/matterbridgeService.js'

export default {
	name: 'MatterbridgeSettings',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
		BridgePart,
		Message,
		NcModal,
		NcTextArea,
		Plus,
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
					iconUrl: imagePath('spreed', 'app-dark.svg'),
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
					mainField: 'server',
				},
				matrix: {
					name: 'Matrix',
					iconUrl: imagePath('spreed', 'bridge-services/matrix.svg'),
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
					mainField: 'server',
				},
				mattermost: {
					name: 'Mattermost',
					iconUrl: imagePath('spreed', 'bridge-services/mattermost.svg'),
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
					mainField: 'server',
				},
				rocketchat: {
					name: 'Rocket.Chat',
					iconUrl: imagePath('spreed', 'bridge-services/rocketchat.svg'),
					infoTarget: 'https://github.com/42wim/matterbridge/wiki/Settings#rocketchat',
					fields: {
						server: {
							type: 'url',
							placeholder: t('spreed', 'Rocket.Chat server URL'),
							icon: 'icon-link',
						},
						login: {
							type: 'text',
							placeholder: t('spreed', 'User name or email address'),
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
						skiptls: {
							type: 'checkbox',
							labelText: t('spreed', 'Skip TLS verification'),
						},
					},
					mainField: 'server',
				},
				zulip: {
					name: 'Zulip',
					iconUrl: imagePath('spreed', 'bridge-services/zulip.svg'),
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
					mainField: 'server',
				},
				slack: {
					name: 'Slack',
					iconUrl: imagePath('spreed', 'bridge-services/slack.svg'),
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
					mainField: 'channel',
				},
				discord: {
					name: 'Discord',
					iconUrl: imagePath('spreed', 'bridge-services/discord.svg'),
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
					mainField: 'server',
				},
				telegram: {
					name: 'Telegram',
					iconUrl: imagePath('spreed', 'bridge-services/telegram.svg'),
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
					mainField: 'chatid',
				},
				steam: {
					name: 'Steam',
					iconUrl: imagePath('spreed', 'bridge-services/steam.svg'),
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
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Chat ID'),
							icon: 'icon-group',
						},
					},
					mainField: 'chatid',
				},
				irc: {
					name: 'IRC',
					iconUrl: imagePath('spreed', 'bridge-services/irc.svg'),
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
					mainField: 'channel',
				},
				msteams: {
					name: 'Microsoft Teams',
					iconUrl: imagePath('spreed', 'bridge-services/msteams.svg'),
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
						channel: {
							type: 'text',
							placeholder: t('spreed', 'Thread ID'),
							icon: 'icon-group',
						},
					},
					mainField: 'threadid',
				},
				xmpp: {
					name: 'XMPP/Jabber',
					iconUrl: imagePath('spreed', 'bridge-services/xmpp.svg'),
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
						skiptls: {
							type: 'checkbox',
							labelText: t('spreed', 'Skip TLS verification'),
						},
					},
					mainField: 'channel',
				},
			},
			newPartPlaceholder: t('spreed', 'Add new bridged channel to current conversation'),
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
					iconUrl: t.iconUrl,
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
		container() {
			return this.$store.getters.getMainContainerSelector()
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
		},
		onDelete(i) {
			this.parts.splice(i, 1)
			this.save()
		},
		onEditClicked(i) {
			this.parts[i].editing = !this.parts[i].editing
			if (!this.parts[i].editing) {
				this.save()
			}
		},
		onEnabled(checked) {
			this.enabled = checked
			this.save()
		},
		save() {
			if (this.parts.length === 0) {
				this.enabled = false
			}
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
				console.error(exception)
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
			} catch (exception) {
				console.error(exception)
			}
			this.loading = false
		},
		async getBridgeProcessState(token) {
			try {
				const result = await getBridgeProcessState(token)
				this.processLog = result.data.ocs.data.log
				this.processRunning = result.data.ocs.data.running
			} catch (exception) {
				console.error(exception)
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
.icon-multiselect-service {
	width: 16px !important;
	height: 16px !important;
	margin-right: 10px;
	filter: var(--background-invert-if-dark);
}

:deep(.modal-container) {
	height: 700px;
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
		.save-changes {
			width: 100%;
			text-align: left;

			.icon-checkmark {
				margin: 0 10px 0 2px;
			}
		}
		.multiselect {
			width: calc(100% - 46px);
		}
		.icon {
			display: inline-block;
			width: 40px;
			height: 34px;
			padding: 6px 10px 0;
			vertical-align: middle;
		}
		.add-part-wrapper {
			margin-top: 5px;
		}
		.enable-switch-line {
			display: flex;
			height: 44px;
			margin-top: 5px;

			label {
				flex-grow: 1;
				margin-top: auto;
				margin-bottom: auto;
				&::before {
					margin: 0 10px 0 15px;
				}
			}
		}
	}

	ul {
		margin-bottom: 64px;
	}
}

.log-content {
	width: 590px;
}

:deep(.modal-container__content) {
	padding: 5px;
}
</style>
