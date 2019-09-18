/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

const state = {
	messages: {
		1: {
			id: 1,
			userName: 'Marco',
			messageText: 'Hello everyone',
			messageTime: '14:35',
			isFirstMessage: true
		},
		2: {
			id: 2,
			userName: 'Joas',
			messageText: 'Please anwser to this message!!!',
			messageTime: '14:35',
			isFirstMessage: true
		},
		3: {
			id: 3,
			userName: 'Barth',
			messageText: 'Here\'s your answer!',
			messageTime: '14:35',
			isFirstMessage: true,
			parent: 2
		},
		4: {
			id: 4,
			userName: 'Marco',
			messageText: 'Hayy buddaaayy',
			messageTime: '14:35',
			isFirstMessage: true
		},
		5: {
			id: 5,
			userName: 'Marco',
			messageText: 'this is a second message from marco and it\'s going to be very very very very very very very very very very very very very very very very very very very very very veryvery very very very very very very very very very very very long very very very very very very very very very very very very very very very very very very very very very veryvery very very very very very very very very very very very long :)',
			messageTime: '14:35',
			isFirstMessage: false

		},
		6: {
			id: 6,
			userName: 'Joas',
			messageText: 'Please anwser to this message!!!',
			messageTime: '14:35',
			isFirstMessage: true
		},
		7: {
			id: 7,
			userName: 'Barth',
			messageText: 'Here\'s your answer!',
			messageTime: '14:35',
			isFirstMessage: true
		},
		8: {
			id: 8,
			userName: 'sertdyu',
			messageText: 'buddaaayy',
			messageTime: '14:35',
			isFirstMessage: true
		},
		9: {
			id: 9,
			userName: 'sertdyu',
			messageText: 'buddaaayy',
			messageTime: '14:35',
			isFirstMessage: true
		},
		10: {
			id: 10,
			userName: 'Marco',
			messageText: 'Hello everyone',
			messageTime: '14:35',
			isFirstMessage: true

		},
		11: {
			id: 11,
			userName: 'Joas',
			messageText: 'Please anwser to this message!!!',
			messageTime: '14:35',
			isFirstMessage: true
		},
		12: {
			id: 12,
			userName: 'Barth',
			messageText: 'Here\'s your answer!',
			messageTime: '14:35',
			isFirstMessage: true
		},
		13: {
			id: 13,
			userName: 'sertdyu',
			messageText: 'buddaaayy',
			messageTime: '14:35',
			isFirstMessage: true
		}
	}
}

const getters = {
	messagesList: state => Object.values(state.messages),
	messages: state => state.messages
}

const mutations = {
	addMessage(state, message) {
		state.messages[message.id] = message
	}
}

const actions = {
	processMessage(context, message) {
		if (message.parent) {
			context.commit('addMessage', message.parent)
			message.parent = message.parent.id
		}
		context.commit('addMessage', message)
	}
}

export default { state, mutations, getters, actions }
