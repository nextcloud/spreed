/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { imagePath } from '@nextcloud/router'

type InputField = {
	type: 'url' | 'text' | 'password'
	placeholder: string
	icon: string
} | {
	type: 'checkbox'
	labelText: string
}
type MatterbridgeType = {
	name: string
	iconUrl: string
	infoTarget: string
	fields: Record<string, InputField>
	mainField: string
}

export const matterbridgeTypes: Record<string, MatterbridgeType> = {
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
			skiptls: {
				type: 'checkbox',
				labelText: t('spreed', 'Skip TLS verification'),
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
				placeholder: t('spreed', 'Channel ID (prefixed with "ID:") or name'),
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
}
