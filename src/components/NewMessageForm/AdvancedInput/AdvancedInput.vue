<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<At ref="at"
		v-model="text"
		name-key="label"
		:members="autoCompleteMentionCandidates"
		:filter-match="atFilter"
		@at="handleAtEvent">
		<template v-slot:item="scope">
			<Avatar v-if="isMentionToAll(scope.item.id)"
				:icon-class="'icon-group-forced-white'"
				:disable-tooltip="true"
				:disable-menu="true"
				:is-no-user="true" />
			<div v-else-if="isMentionToGuest(scope.item.id)"
				class="avatar guest"
				:style="getGuestAvatarStyle()">
				{{ getFirstLetterOfGuestName(scope.item.label) }}
			</div>
			<Avatar v-else
				:user="scope.item.id"
				:display-name="scope.item.label"
				:disable-tooltip="true"
				:disable-menu="true" />
			&nbsp;
			<span>{{ scope.item.label }}</span>
		</template>
		<template v-slot:embeddedItem="scope">
			<!-- The root element itself is ignored, only its contents are taken
			     into account. -->
			<span>
				<!-- vue-at seems to try to create an embedded item at some
				     strange times in which no item is selected and thus there
				     is no data, so do not use the Mention component in those
				     cases. -->
				<Mention v-if="scope.current.id" :data="getDataForMentionComponent(scope.current)" :data-mention-id="scope.current.id" />
			</span>
		</template>
		<div ref="contentEditable"
			:contenteditable="activeInput"
			:placeHolder="placeholderText"
			class="new-message-form__advancedinput"
			@keydown.enter="handleKeydown"
			@paste="onPaste" />
	</At>
</template>

<script>
import At from 'vue-at'
import VueAtReparenter from '../../../mixins/vueAtReparenter'
import { EventBus } from '../../../services/EventBus'
import { searchPossibleMentions } from '../../../services/mentionsService'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Mention from '../../MessagesList/MessagesGroup/Message/MessagePart/Mention'

export default {
	name: 'AdvancedInput',
	components: {
		At,
		Avatar,
		Mention,
	},
	mixins: [
		VueAtReparenter,
	],
	props: {
		/**
		 * The placeholder for the input field
		 */
		placeholderText: {
			type: String,
			default: t('spreed', 'Write message, @ to mention someone …'),
		},

		/**
		 * Determines if the input is active
		 */
		activeInput: {
			type: Boolean,
			default: true,
		},

		value: {
			type: String,
			required: true,
		},

		/**
		 * The token of the conversation to get candidate mentions for.
		 */
		token: {
			type: String,
			required: true,
		},
	},
	data: function() {
		return {
			text: '',
			autoCompleteMentionCandidates: [],
		}
	},
	watch: {
		text(text) {
			this.$emit('update:contentEditable', this.$refs.contentEditable.cloneNode(true))

			this.$emit('update:value', text)
			this.$emit('input', text)
			this.$emit('change', text)
		},
		value(value) {
			this.text = value
		},
		atwho(atwho) {
			if (!atwho) {
				// Clear mention candidates when closing the panel. Otherwise
				// they would be shown when the panel is opened again until the
				// new ones are received.
				this.autoCompleteMentionCandidates = []
			}
		},
	},
	mounted() {
		this.focusInput()
		/**
		 * Listen to routeChange global events and focus on the
		 */
		EventBus.$on('routeChange', this.focusInput)

		this.addCustomAtWhoStyleSheet()
	},
	beforeDestroy() {
		EventBus.$off('routeChange', this.focusInput)
	},
	methods: {
		onPaste(e) {
			e.preventDefault()
			const text = e.clipboardData.getData('text/plain')
			document.execCommand('insertText', false, text)
		},

		/**
		 * The vue-at library only searches in the display name by default.
		 * But luckily our server responds already only with matching items,
		 * so we just filter none and show them all.
		 * @returns {boolean} True as we never filter anything out
		 */
		atFilter() {
			return true
		},

		/**
		 * Focuses the contenteditable div input
		 */
		focusInput() {
			if (this.$route && this.$route.name === 'conversation') {
				const contentEditable = this.$refs.contentEditable
				// This is a hack but it's the only way I've found to focus a contenteditable div
				setTimeout(function() {
					contentEditable.focus()
				}, 0)
			}
		},
		/**
		 * Emits the submit event when enter is pressed (look
		 * at the v-on in the template) unless shift is pressed:
		 * in this case a new line will be created.
		 *
		 * @param {object} event the event object;
		 */
		handleKeydown(event) {
			// Prevent submit event when vue-at panel is open, as that should
			// just select the mention from the panel.
			if (this.atwho) {
				return
			}

			// TODO: add support for CTRL+ENTER new line
			if (!(event.shiftKey)) {
				event.preventDefault()
				this.$emit('submit', event)
			}
		},

		/**
		 * Sets the autocomplete mention candidates based on the matched text
		 * after the "@".
		 *
		 * @param {String} chunk the matched text to look candidate mentions for.
		 */
		async handleAtEvent(chunk) {
			const response = await searchPossibleMentions(this.token, chunk)
			const possibleMentions = response.data.ocs.data

			// Wrap mention ids with spaces in quotes.
			possibleMentions.forEach(possibleMention => {
				if (possibleMention.id.indexOf(' ') !== -1
					|| possibleMention.id.indexOf('guest/') === 0) {
					possibleMention.id = '"' + possibleMention.id + '"'
				}
			})

			this.autoCompleteMentionCandidates = possibleMentions
		},

		isMentionToAll(mentionId) {
			return mentionId === 'all'
		},

		isMentionToGuest(mentionId) {
			// Guest ids, like ids of users with spaces, are wrapped in quotes.
			return mentionId.startsWith('"guest/')
		},

		getGuestAvatarStyle() {
			return {
				'width': '32px',
				'height': '32px',
				'line-height': '32px',
				'background-color': '#b9b9b9',
				'text-align': 'center',
			}
		},

		getFirstLetterOfGuestName(displayName) {
			const customName = displayName !== t('spreed', 'Guest') ? displayName : '?'
			return customName.charAt(0)
		},

		getDataForMentionComponent(candidate) {
			let type = 'user'
			if (this.isMentionToAll(candidate.id)) {
				type = 'call'
			} else if (this.isMentionToGuest(candidate.id)) {
				type = 'guest'
			}

			return {
				id: candidate.id,
				name: candidate.label,
				type: type,
			}
		},

		/**
		 * Adds a special style sheet to customize atwho elements.
		 *
		 * The <style> section has no effect on the atwho elements, as the atwho
		 * panel is reparented to the body, and the rules added there are rooted
		 * on the AdvancedInput.
		 */
		addCustomAtWhoStyleSheet() {
			for (let i = 0; i < document.styleSheets.length; i++) {
				const sheet = document.styleSheets[i]
				if (sheet.title === 'at-who-custom') {
					return
				}
			}

			const style = document.createElement('style')
			style.setAttribute('title', 'at-who-custom')

			document.head.appendChild(style)

			// Override "width: 180px", as that makes the autocompletion panel
			// too narrow.
			style.sheet.insertRule('.atwho-view { width: unset; }', 0)
			// Override autocompletion panel items height, as they are too short
			// for the avatars and also need some padding.
			style.sheet.insertRule('.atwho-li { height: unset; padding-top: 6px; padding-bottom: 6px; }', 0)

			// Although the height of its wrapper is 32px the height of the icon
			// is the default 16px. This is a temporary fix until it is fixed
			// in the avatar component.
			style.sheet.insertRule('.atwho-li .icon-group-forced-white { width: 32px; height: 32px; }', 0)
		},
	},
}
</script>

<style lang="scss" scoped>
.new-message-form__advancedinput {
	overflow: visible;
	width: 100%;
	border:none;
	margin: 0;
}

//Support for the placehoder text in the div contenteditable
[contenteditable]:empty:before{
	content: attr(placeholder);
	display: block;
	color: gray;
}
</style>
