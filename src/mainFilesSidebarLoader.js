/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { FileType, getSidebar } from '@nextcloud/files'
import IconTalk from '../img/app-dark.svg?raw'

import './init.js'

const TAB_TAG_NAME = 'talk-files_sidebar_tab'

getSidebar()?.registerTab({
	id: 'chat',
	displayName: t('spreed', 'Chat'),
	iconSvgInline: IconTalk,
	order: 30,
	enabled: ({ node }) => node.type === FileType.File,
	tagName: TAB_TAG_NAME,
	onInit() {
		window.customElements.define(TAB_TAG_NAME, OCA.Talk.newTab())
	},
})
