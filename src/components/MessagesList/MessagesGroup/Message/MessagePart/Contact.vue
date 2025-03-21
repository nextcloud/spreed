<!--
  - @copyright Copyright (c) 2021, Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license AGPL-3.0-or-later
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
	<a class="contact"
		:href="link"
		:aria-label="contactAriaLabel"
		target="_blank">
		<img v-if="contactPhotoFromBase64"
			:class="{
				'contact__image': contactHasPhoto,
				'contact__icon': !contactHasPhoto,
			}"
			alt=""
			:src="contactPhotoFromBase64">
		<div class="contact__lineone">
			<div class="title">
				{{ displayName }}
			</div>
		</div>
	</a>
</template>

<script>
export default {
	name: 'Contact',

	props: {
		name: {
			type: String,
			required: true,
		},

		link: {
			type: String,
			required: true,
		},

		contactName: {
			type: String,
			default: '',
		},

		contactPhoto: {
			type: String,
			default: '',
		},

		contactPhotoMimetype: {
			type: String,
			default: '',
		},
	},

	computed: {
		contactHasPhoto() {
			return this.contactPhotoMimetype && this.contactPhoto
		},
		contactPhotoFromBase64() {
			if (!this.contactHasPhoto) {
				return OC.MimeType.getIconUrl('text/vcard')
			}
			return 'data:' + this.contactPhotoMimetype + ';base64,' + this.contactPhoto
		},
		displayName() {
			return this.contactName || this.name
		},
		contactAriaLabel() {
			return t('spreed', 'Contact')
		},
	},
}
</script>

	<style lang="scss" scoped>
	.contact {
		display: flex;
		transition: box-shadow 0.1s ease-in-out;
		border: 1px solid var(--color-border);
		box-shadow: 0 0 2px 0 var(--color-box-shadow);
		border-radius: var(--border-radius-large);
		font-size: 100%;
		background-color: var(--color-main-background);
		margin: 12px 0;
		max-width: 300px;
		padding: 12px;
		white-space: nowrap;
		align-items: center;
		&:hover,
		&:focus{
			box-shadow: 0 0 5px 0 var(--color-box-shadow);
		}
		&__image {
			display: inline-block;
			border-radius: 50%;
			max-width: 44px;
			max-height: 44px;
		}
		&__icon {
			display: inline-block;
			width: 44px;
			height: 44px;
		}
		&__lineone {
			height: 30px;
			display: flex;
			justify-content: flex-start;
			align-items: center;
			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;

			.title {
				margin-left: 12px;
			}
		}
	}

	.icon-contacts {
		opacity: .8;
	}

	</style>
