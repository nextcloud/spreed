import FlowPostToConversation from './views/FlowPostToConversation'

window.OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\Talk\\Flow\\Operation',
	color: 'tomato',
	operation: '',
	options: FlowPostToConversation,
})
