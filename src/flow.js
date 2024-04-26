/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import FlowPostToConversation from './views/FlowPostToConversation.vue'

window.OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\Talk\\Flow\\Operation',
	color: '#0082c9',
	operation: '',
	options: FlowPostToConversation,
})
