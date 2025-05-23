/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import wrap from '@vue/web-component-wrapper'
import Vue from 'vue'
import FlowPostToConversation from './views/FlowPostToConversation.vue'

const FlowPostToConversationComponent = wrap(Vue, FlowPostToConversation)
const customElementId = 'oca-spreed-flow_post_to_conversation'
window.customElements.define(customElementId, FlowPostToConversationComponent)

// In Vue 2, wrap doesn't support disabling shadow :(
// Disable with a hack
Object.defineProperty(FlowPostToConversationComponent.prototype, 'attachShadow', { value() { return this } })
Object.defineProperty(FlowPostToConversationComponent.prototype, 'shadowRoot', { get() { return this } })

window.OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\Talk\\Flow\\Operation',
	color: '#0082c9',
	operation: '',
	element: customElementId,
	options: FlowPostToConversation, // backward "compatibility"
})
