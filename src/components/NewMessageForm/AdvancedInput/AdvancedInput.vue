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
		@at="handleAtEvent">
		<template v-slot:item="scope">
			<span>{{ scope.item.label }}</span>
		</template>
		<template v-slot:embeddedItem="scope">
			<!-- The root element itself is ignored, only its contents are taken
			     into account. -->
			<span>@{{ scope.current.id }}</span>
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

export default {
	name: 'AdvancedInput',
	components: {
		At,
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
