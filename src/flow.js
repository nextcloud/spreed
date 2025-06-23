/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineCustomElement } from 'vue'
import FlowPostToConversation from './views/FlowPostToConversation.vue'

const FlowPostToConversationComponent = defineCustomElement(FlowPostToConversation, {
	shadowRoot: false,
})
const customElementId = 'oca-spreed-flow_post_to_conversation'
window.customElements.define(customElementId, FlowPostToConversationComponent)

window.OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\Talk\\Flow\\Operation',
	color: '#0082c9',
	operation: '',
	element: customElementId,
	options: FlowPostToConversation, // backward "compatibility"
})
