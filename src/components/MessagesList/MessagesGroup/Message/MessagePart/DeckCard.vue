<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<a
		class="deck-card"
		:class="{ wide: wide }"
		:href="link"
		:aria-label="deckCardAriaLabel"
		target="_blank">
		<div class="deck-card__lineone">
			<div class="icon-deck" />
			<div class="title">
				{{ name }}
			</div>
		</div>
		<div class="deck-card__linetwo">
			<div>
				{{ deckLocation }}
			</div>
		</div>
	</a>
</template>

<script>
import { t } from '@nextcloud/l10n'

export default {
	name: 'DeckCard',

	props: {
		type: {
			type: String,
			required: true,
		},

		id: {
			type: String,
			required: true,
		},

		name: {
			type: String,
			required: true,
		},

		boardname: {
			type: String,
			required: true,
		},

		stackname: {
			type: String,
			required: true,
		},

		link: {
			type: String,
			required: true,
		},

		wide: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		deckLocation() {
			return t('spreed', '{stack} in {board}', {
				stack: this.stackname,
				board: this.boardname,
			})
		},

		deckCardAriaLabel() {
			return t('spreed', 'Deck Card')
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
.deck-card {
	display: flex;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large);
	font-size: 100%;
	background-color: var(--color-main-background);
	max-width: 300px;
	padding: 8px 16px;
	flex-direction: column;
	white-space: nowrap;
	transition: border-color 0.1s ease-in-out;

	&:hover,
	&:focus,
	&:focus-visible {
		border-color: var(--color-primary-element);
		outline: none;
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
			margin-inline-start: 8px;
		}
	}

	&__linetwo {
		height: 30px;
		color: var(--color-text-maxcontrast);
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
}

.icon-deck {
	opacity: .8;
}

.wide {
	max-width: 400px;
	width: 100%;
}

</style>
