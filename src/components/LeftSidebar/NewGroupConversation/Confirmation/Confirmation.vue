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
	<div class="confirmation">
		<template v-if="isLoading && !error">
			<template v-if="!success">
				<div class="icon-loading confirmation__icon" />
				<p class="confirmation__warning">
					{{ t('spreed', 'Creating your conversation') }}
				</p>
			</template>
			<template v-if="success && isPublic">
				<div class="icon-checkmark confirmation__icon" />
				<p class="confirmation__warning">
					{{ t('spreed', 'All set') }}
				</p>
				<Button id="copy-link"
					slot="trigger"
					v-clipboard:copy="linkToConversation"
					v-clipboard:success="onCopy"
					v-clipboard:error="onError"
					type="secondary"
					class="confirmation__copy-link">
					<label for="copy-link">{{ t('spreed', 'Copy conversation link') }}</label>
				</Button>
				<p class="confirmation__warning">
					{{ confirmationText }}
				</p>
			</template>
		</template>
		<template v-else>
			<div class="icon-error confirmation__icon" />
			<p class="confirmation__warning">
				{{ t('spreed', 'Error while creating the conversation') }}
			</p>
		</template>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'

export default {
	name: 'Confirmation',
	components: {
		Button,
	},
	props: {
		conversationName: {
			type: String,
			required: true,
		},
		isLoading: {
			type: Boolean,
			required: true,
		},
		success: {
			type: Boolean,
			required: true,
		},
		error: {
			type: Boolean,
			required: true,
		},
		isPublic: {
			type: Boolean,
			required: true,
		},
		linkToConversation: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			showTooltip: false,
			confirmationText: '',
			showConfirmationText: false,
		}
	},

	methods: {
		onCopy() {
			this.confirmationText = t('spreed', 'Link copied to the clipboard!')
			this.showConfirmationText = true
			setTimeout(function() {
				this.showConfirmationText = false
			}, 800)
		},
		onError() {
			this.confirmationText = t('spreed', 'Error')
			this.showConfirmationText = true
			setTimeout(function() {
				this.showConfirmationText = false
			}, 800)
		},
	},

}

</script>

<style lang="scss" scoped>
.confirmation {
	display: flex;
	flex-direction: column;
	&__icon{
		padding-top: 80px;
	}
	&__warning{
		margin-top: 10px;
		text-align: center;
	}
	&__copy-link {
		margin: 50px auto 0 auto;
	}
}
</style>
