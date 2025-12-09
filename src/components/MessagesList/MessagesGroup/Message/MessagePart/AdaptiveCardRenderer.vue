<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="adaptive-card-wrapper" :class="{ 'adaptive-card-wrapper--error': hasError }">
		<!-- Card container where SDK will render -->
		<div
			ref="cardContainer"
			class="adaptive-card"
			role="region"
			:aria-label="t('spreed', 'Interactive card from {botName}', { botName })" />

		<!-- Error state -->
		<div v-if="hasError" class="adaptive-card__error">
			<IconAlertCircleOutline :size="20" />
			<span>{{ errorMessage }}</span>
		</div>

		<!-- Loading state -->
		<div v-if="isLoading" class="adaptive-card__loading">
			<div class="icon-loading-small" />
			<span>{{ t('spreed', 'Loading interactive card...') }}</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'

import * as AdaptiveCards from 'adaptivecards'

import type { AdaptiveCardParameter, AdaptiveCardsHostConfig } from '../../../../../types/adaptiveCards.ts'
import { submitAdaptiveCardResponse } from '../../../../../services/botsService.ts'

const props = defineProps<{
	/** Card object from message parameters */
	card: AdaptiveCardParameter
	/** Conversation token */
	token: string
	/** Message ID this card is attached to */
	messageId?: number
}>()

const cardContainer = ref<HTMLDivElement | null>(null)
const hasError = ref(false)
const errorMessage = ref('')
const isLoading = ref(true)
const adaptiveCardInstance = ref<AdaptiveCards.AdaptiveCard | null>(null)

const botName = computed(() => props.card['bot-name'] || t('spreed', 'Bot'))

// Nextcloud Talk host config for Adaptive Cards
const hostConfig: AdaptiveCardsHostConfig = {
	supportsInteractivity: true,
	fontFamily: 'var(--font-face)',
	fontSizes: {
		small: 12,
		default: 14,
		medium: 16,
		large: 20,
		extraLarge: 24,
	},
	fontWeights: {
		lighter: 300,
		default: 400,
		bolder: 600,
	},
	spacing: {
		small: 4,
		default: 8,
		medium: 12,
		large: 16,
		extraLarge: 24,
		padding: 12,
	},
	separator: {
		lineThickness: 1,
		lineColor: 'var(--color-border)',
	},
	imageSizes: {
		small: 40,
		medium: 80,
		large: 120,
	},
	containerStyles: {
		default: {
			backgroundColor: 'var(--color-main-background)',
			foregroundColors: {
				default: { default: 'var(--color-main-text)', subtle: 'var(--color-text-maxcontrast)' },
				accent: { default: 'var(--color-primary-element)', subtle: 'var(--color-primary-element-light)' },
				good: { default: 'var(--color-success)', subtle: 'var(--color-success-hover)' },
				warning: { default: 'var(--color-warning)', subtle: 'var(--color-warning-hover)' },
				attention: { default: 'var(--color-error)', subtle: 'var(--color-error-hover)' },
			},
		},
		emphasis: {
			backgroundColor: 'var(--color-background-dark)',
		},
		good: {
			backgroundColor: 'var(--color-success-background)',
		},
		warning: {
			backgroundColor: 'var(--color-warning-background)',
		},
		attention: {
			backgroundColor: 'var(--color-error-background)',
		},
		accent: {
			backgroundColor: 'var(--color-primary-element-light)',
		},
	},
	actions: {
		maxActions: 5,
		spacing: 'default',
		buttonSpacing: 8,
		showCard: {
			actionMode: 'inline',
			inlineTopMargin: 12,
			style: 'emphasis',
		},
		actionsOrientation: 'horizontal',
		actionAlignment: 'left',
	},
}

/**
 * Handle Action.Submit
 */
async function handleSubmit(action: AdaptiveCards.SubmitAction): Promise<void> {
	try {
		// Get all input values from the card instance
		const values: Record<string, unknown> = {}

		if (adaptiveCardInstance.value) {
			const inputs = adaptiveCardInstance.value.getAllInputs()
			inputs.forEach(input => {
				if (input.id) {
					values[input.id] = input.value
				}
			})
		}

		// Include any data from the action itself
		if (action.data) {
			Object.assign(values, action.data)
		}

		// Send to bot webhook
		await submitAdaptiveCardResponse(props.token, props.card.id, values)

		showSuccess(t('spreed', 'Response submitted successfully'))
	} catch (error) {
		console.error('Failed to submit adaptive card response:', error)
		showError(t('spreed', 'Failed to submit response'))
	}
}

/**
 * Handle Action.OpenUrl with security checks
 */
function handleOpenUrl(action: AdaptiveCards.OpenUrlAction): void {
	const url = action.url

	// Security checks
	if (!url) {
		showError(t('spreed', 'Invalid URL'))
		return
	}

	// Block dangerous protocols
	const urlLower = url.toLowerCase()
	if (urlLower.startsWith('javascript:') || urlLower.startsWith('data:') || urlLower.startsWith('file:')) {
		showError(t('spreed', 'This URL type is not allowed for security reasons'))
		return
	}

	// TODO: Add domain whitelist check from admin settings
	// For now, show confirmation for external URLs
	if (!url.startsWith('/') && !url.startsWith('#')) {
		// External URL - could show confirmation dialog
		// For phase 1, we'll just open it
		console.warn('Opening external URL:', url)
	}

	// Open URL
	window.open(url, '_blank', 'noopener,noreferrer')
}

/**
 * Handle Action.Execute (custom Nextcloud verbs)
 */
async function handleExecute(action: AdaptiveCards.ExecuteAction): Promise<void> {
	const verb = action.verb

	// Handle x-nextcloud custom actions
	if (verb?.startsWith('x-nextcloud.')) {
		const customAction = verb.substring('x-nextcloud.'.length)

		switch (customAction) {
		case 'startCall':
			// TODO: Implement call start logic
			console.log('Start call action', action.data)
			showError(t('spreed', 'Call actions are not yet implemented'))
			break

		case 'shareFile':
			// TODO: Implement file picker
			console.log('Share file action', action.data)
			showError(t('spreed', 'File sharing from cards is not yet implemented'))
			break

		case 'mentionUser':
			// TODO: Implement mention
			console.log('Mention user action', action.data)
			showError(t('spreed', 'Mention actions are not yet implemented'))
			break

		case 'createPoll':
			// TODO: Implement poll creator
			console.log('Create poll action', action.data)
			showError(t('spreed', 'Poll creation from cards is not yet implemented'))
			break

		default:
			console.warn('Unknown custom action:', customAction)
			showError(t('spreed', 'This action is not supported'))
		}

		return
	}

	// For non-Nextcloud actions, treat as submit
	await handleSubmit(action as unknown as AdaptiveCards.SubmitAction)
}

/**
 * Render the Adaptive Card
 */
function renderCard(): void {
	if (!cardContainer.value) {
		return
	}

	try {
		isLoading.value = true
		hasError.value = false

		// Create adaptive card instance and store in ref
		adaptiveCardInstance.value = new AdaptiveCards.AdaptiveCard()

		// Set host config
		adaptiveCardInstance.value.hostConfig = new AdaptiveCards.HostConfig(hostConfig)

		// Handle action execution
		adaptiveCardInstance.value.onExecuteAction = (action: AdaptiveCards.Action) => {
			if (action instanceof AdaptiveCards.SubmitAction) {
				handleSubmit(action)
			} else if (action instanceof AdaptiveCards.OpenUrlAction) {
				handleOpenUrl(action)
			} else if (action instanceof AdaptiveCards.ExecuteAction) {
				handleExecute(action)
			} else if (action instanceof AdaptiveCards.ShowCardAction) {
				// ShowCard is handled automatically by the SDK
				console.log('ShowCard action executed')
			} else if (action instanceof AdaptiveCards.ToggleVisibilityAction) {
				// ToggleVisibility is handled automatically by the SDK
				console.log('ToggleVisibility action executed')
			}
		}

		// Parse and render the card
		adaptiveCardInstance.value.parse(props.card.card)

		// Validate the card
		const validationErrors = adaptiveCardInstance.value.validateProperties()
		if (validationErrors.length > 0) {
			console.warn('Adaptive Card validation warnings:', validationErrors)
		}

		// Render to container
		const renderedCard = adaptiveCardInstance.value.render()

		if (renderedCard) {
			// Clear container and append rendered card
			cardContainer.value.innerHTML = ''
			cardContainer.value.appendChild(renderedCard)
			isLoading.value = false
		} else {
			throw new Error('Failed to render adaptive card')
		}
	} catch (error) {
		console.error('Error rendering adaptive card:', error)
		hasError.value = true
		errorMessage.value = error instanceof Error ? error.message : t('spreed', 'Failed to render card')
		isLoading.value = false
	}
}

// Render card on mount
onMounted(() => {
	renderCard()
})

// Re-render if card changes
watch(() => props.card.card, () => {
	renderCard()
}, { deep: true })
</script>

<style lang="scss" scoped>
.adaptive-card-wrapper {
	margin: 8px 0;
	max-width: 600px;

	&--error {
		border: 1px solid var(--color-error);
		border-radius: var(--border-radius-large);
		padding: 12px;
	}
}

.adaptive-card {
	// The SDK will inject its content here
	// We provide minimal styling, the SDK handles most of it

	// Ensure inputs and buttons use Nextcloud styling
	:deep(input[type="text"]),
	:deep(input[type="number"]),
	:deep(input[type="date"]),
	:deep(input[type="time"]),
	:deep(textarea),
	:deep(select) {
		border: 1px solid var(--color-border-dark);
		border-radius: var(--border-radius);
		padding: 8px;
		font-family: var(--font-face);
		font-size: 14px;
		width: 100%;
		box-sizing: border-box;

		&:focus {
			outline: 2px solid var(--color-primary-element);
			outline-offset: 0;
		}
	}

	:deep(button) {
		background-color: var(--color-primary-element);
		color: var(--color-primary-element-text);
		border: none;
		border-radius: var(--border-radius);
		padding: 8px 16px;
		font-family: var(--font-face);
		font-size: 14px;
		font-weight: 600;
		cursor: pointer;
		transition: background-color 0.2s;

		&:hover {
			background-color: var(--color-primary-element-hover);
		}

		&:active {
			background-color: var(--color-primary-element);
		}

		&:disabled {
			opacity: 0.5;
			cursor: not-allowed;
		}

		// Secondary button style
		&.ac-pushButton.style-default {
			background-color: var(--color-background-dark);
			color: var(--color-main-text);

			&:hover {
				background-color: var(--color-background-hover);
			}
		}

		// Destructive button style
		&.ac-pushButton.style-destructive {
			background-color: var(--color-error);
			color: white;

			&:hover {
				background-color: var(--color-error-hover);
			}
		}

		// Positive button style
		&.ac-pushButton.style-positive {
			background-color: var(--color-success);
			color: white;

			&:hover {
				background-color: var(--color-success-hover);
			}
		}
	}

	// Checkbox styling
	:deep(input[type="checkbox"]) {
		width: 20px;
		height: 20px;
		cursor: pointer;
	}

	// Ensure proper spacing
	:deep(.ac-container) {
		padding: 12px;
		background-color: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
	}
}

.adaptive-card__error {
	display: flex;
	align-items: center;
	gap: 8px;
	color: var(--color-error);
	font-size: 14px;

	svg {
		flex-shrink: 0;
	}
}

.adaptive-card__loading {
	display: flex;
	align-items: center;
	gap: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	padding: 12px;
}
</style>
