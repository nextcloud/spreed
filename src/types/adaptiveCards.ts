/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Type definitions for Adaptive Cards integration in Nextcloud Talk
 */

/**
 * Adaptive Card object structure as stored in message parameters
 */
export interface AdaptiveCardParameter {
	/** Object type identifier */
	type: 'adaptivecard'
	/** Unique card identifier */
	id: string
	/** The Adaptive Card JSON payload */
	card: AdaptiveCardSchema
	/** Optional bot name that created the card */
	'bot-name'?: string
}

/**
 * Adaptive Card JSON schema (1.5+)
 * https://adaptivecards.io/explorer/
 */
export interface AdaptiveCardSchema {
	/** Schema URL */
	$schema?: string
	/** Card type */
	type: 'AdaptiveCard'
	/** Schema version */
	version: string
	/** Card body elements */
	body?: AdaptiveCardElement[]
	/** Card actions */
	actions?: AdaptiveCardAction[]
	/** Fallback text for unsupported clients */
	fallbackText?: string
	/** Minimum height */
	minHeight?: string
	/** Vertical content alignment */
	verticalContentAlignment?: 'top' | 'center' | 'bottom'
	/** Select action */
	selectAction?: AdaptiveCardAction
	/** Nextcloud-specific extensions */
	'x-nextcloud'?: NextcloudCardExtensions
}

/**
 * Base properties for all Adaptive Card elements
 */
interface AdaptiveCardElementBase {
	/** Element type */
	type: string
	/** Element ID */
	id?: string
	/** Spacing */
	spacing?: 'none' | 'small' | 'default' | 'medium' | 'large' | 'extraLarge' | 'padding'
	/** Separator */
	separator?: boolean
	/** Height */
	height?: 'auto' | 'stretch'
	/** Visibility */
	isVisible?: boolean
	/** Fallback */
	fallback?: AdaptiveCardElement | 'drop'
	/** Requirements */
	requires?: Record<string, string>
}

/**
 * Union type for all supported Adaptive Card elements
 */
export type AdaptiveCardElement =
	| TextBlockElement
	| ImageElement
	| ContainerElement
	| ColumnSetElement
	| ColumnElement
	| FactSetElement
	| ImageSetElement
	| InputTextElement
	| InputNumberElement
	| InputDateElement
	| InputTimeElement
	| InputToggleElement
	| InputChoiceSetElement
	| ActionSetElement

/**
 * TextBlock element
 */
export interface TextBlockElement extends AdaptiveCardElementBase {
	type: 'TextBlock'
	text: string
	color?: 'default' | 'dark' | 'light' | 'accent' | 'good' | 'warning' | 'attention'
	fontType?: 'default' | 'monospace'
	horizontalAlignment?: 'left' | 'center' | 'right'
	isSubtle?: boolean
	maxLines?: number
	size?: 'small' | 'default' | 'medium' | 'large' | 'extraLarge'
	weight?: 'lighter' | 'default' | 'bolder'
	wrap?: boolean
}

/**
 * Image element
 */
export interface ImageElement extends AdaptiveCardElementBase {
	type: 'Image'
	url: string
	altText?: string
	backgroundColor?: string
	horizontalAlignment?: 'left' | 'center' | 'right'
	selectAction?: AdaptiveCardAction
	size?: 'auto' | 'stretch' | 'small' | 'medium' | 'large'
	style?: 'default' | 'person'
	width?: string
}

/**
 * Container element
 */
export interface ContainerElement extends AdaptiveCardElementBase {
	type: 'Container'
	items: AdaptiveCardElement[]
	selectAction?: AdaptiveCardAction
	style?: 'default' | 'emphasis' | 'good' | 'attention' | 'warning' | 'accent'
	verticalContentAlignment?: 'top' | 'center' | 'bottom'
	bleed?: boolean
	backgroundImage?: string | BackgroundImage
	minHeight?: string
}

/**
 * Background image configuration
 */
export interface BackgroundImage {
	url: string
	fillMode?: 'cover' | 'repeatHorizontally' | 'repeatVertically' | 'repeat'
	horizontalAlignment?: 'left' | 'center' | 'right'
	verticalAlignment?: 'top' | 'center' | 'bottom'
}

/**
 * ColumnSet element
 */
export interface ColumnSetElement extends AdaptiveCardElementBase {
	type: 'ColumnSet'
	columns: ColumnElement[]
	selectAction?: AdaptiveCardAction
	style?: 'default' | 'emphasis' | 'good' | 'attention' | 'warning' | 'accent'
	bleed?: boolean
	minHeight?: string
	horizontalAlignment?: 'left' | 'center' | 'right'
}

/**
 * Column element
 */
export interface ColumnElement extends AdaptiveCardElementBase {
	type: 'Column'
	items: AdaptiveCardElement[]
	backgroundImage?: string | BackgroundImage
	bleed?: boolean
	minHeight?: string
	selectAction?: AdaptiveCardAction
	style?: 'default' | 'emphasis' | 'good' | 'attention' | 'warning' | 'accent'
	verticalContentAlignment?: 'top' | 'center' | 'bottom'
	width?: 'auto' | 'stretch' | string
}

/**
 * FactSet element
 */
export interface FactSetElement extends AdaptiveCardElementBase {
	type: 'FactSet'
	facts: Fact[]
}

/**
 * Fact (key-value pair)
 */
export interface Fact {
	title: string
	value: string
}

/**
 * ImageSet element
 */
export interface ImageSetElement extends AdaptiveCardElementBase {
	type: 'ImageSet'
	images: ImageElement[]
	imageSize?: 'small' | 'medium' | 'large'
}

/**
 * Input.Text element
 */
export interface InputTextElement extends AdaptiveCardElementBase {
	type: 'Input.Text'
	id: string
	isMultiline?: boolean
	maxLength?: number
	placeholder?: string
	style?: 'text' | 'tel' | 'url' | 'email'
	inlineAction?: AdaptiveCardAction
	value?: string
	isRequired?: boolean
	errorMessage?: string
	label?: string
	regex?: string
}

/**
 * Input.Number element
 */
export interface InputNumberElement extends AdaptiveCardElementBase {
	type: 'Input.Number'
	id: string
	max?: number
	min?: number
	placeholder?: string
	value?: number
	isRequired?: boolean
	errorMessage?: string
	label?: string
}

/**
 * Input.Date element
 */
export interface InputDateElement extends AdaptiveCardElementBase {
	type: 'Input.Date'
	id: string
	max?: string
	min?: string
	placeholder?: string
	value?: string
	isRequired?: boolean
	errorMessage?: string
	label?: string
}

/**
 * Input.Time element
 */
export interface InputTimeElement extends AdaptiveCardElementBase {
	type: 'Input.Time'
	id: string
	max?: string
	min?: string
	placeholder?: string
	value?: string
	isRequired?: boolean
	errorMessage?: string
	label?: string
}

/**
 * Input.Toggle element
 */
export interface InputToggleElement extends AdaptiveCardElementBase {
	type: 'Input.Toggle'
	id: string
	title: string
	value?: string
	valueOff?: string
	valueOn?: string
	wrap?: boolean
	isRequired?: boolean
	errorMessage?: string
	label?: string
}

/**
 * Input.ChoiceSet element
 */
export interface InputChoiceSetElement extends AdaptiveCardElementBase {
	type: 'Input.ChoiceSet'
	id: string
	choices: Choice[]
	isMultiSelect?: boolean
	style?: 'compact' | 'expanded' | 'filtered'
	value?: string
	placeholder?: string
	wrap?: boolean
	isRequired?: boolean
	errorMessage?: string
	label?: string
}

/**
 * Choice option
 */
export interface Choice {
	title: string
	value: string
}

/**
 * ActionSet element
 */
export interface ActionSetElement extends AdaptiveCardElementBase {
	type: 'ActionSet'
	actions: AdaptiveCardAction[]
}

/**
 * Union type for all supported Adaptive Card actions
 */
export type AdaptiveCardAction =
	| ActionSubmit
	| ActionOpenUrl
	| ActionShowCard
	| ActionToggleVisibility
	| ActionExecute

/**
 * Base properties for all actions
 */
interface AdaptiveCardActionBase {
	type: string
	title?: string
	iconUrl?: string
	id?: string
	style?: 'default' | 'positive' | 'destructive'
	fallback?: AdaptiveCardAction | 'drop'
	requires?: Record<string, string>
	mode?: 'primary' | 'secondary'
	tooltip?: string
	isEnabled?: boolean
	role?: string
}

/**
 * Action.Submit
 */
export interface ActionSubmit extends AdaptiveCardActionBase {
	type: 'Action.Submit'
	data?: Record<string, unknown>
	associatedInputs?: 'auto' | 'none'
}

/**
 * Action.OpenUrl
 */
export interface ActionOpenUrl extends AdaptiveCardActionBase {
	type: 'Action.OpenUrl'
	url: string
}

/**
 * Action.ShowCard
 */
export interface ActionShowCard extends AdaptiveCardActionBase {
	type: 'Action.ShowCard'
	card: AdaptiveCardSchema
}

/**
 * Action.ToggleVisibility
 */
export interface ActionToggleVisibility extends AdaptiveCardActionBase {
	type: 'Action.ToggleVisibility'
	targetElements: string[] | TargetElement[]
}

/**
 * Target element for visibility toggle
 */
export interface TargetElement {
	elementId: string
	isVisible?: boolean
}

/**
 * Action.Execute (custom verbs)
 */
export interface ActionExecute extends AdaptiveCardActionBase {
	type: 'Action.Execute'
	verb: string
	data?: Record<string, unknown>
	associatedInputs?: 'auto' | 'none'
}

/**
 * Nextcloud-specific card extensions
 */
export interface NextcloudCardExtensions {
	/** Enable collaborative mode (show responses to all participants) */
	collaborative?: boolean
	/** Show response count/details */
	showResponses?: boolean
	/** Allow multiple responses from same user */
	allowMultipleResponses?: boolean
	/** Card expiration time in seconds */
	expiresIn?: number
	/** Custom callback URL (overrides bot webhook URL) */
	callbackUrl?: string
}

/**
 * Card submission data sent to bot webhook
 */
export interface AdaptiveCardSubmission {
	/** Submission type */
	type: 'adaptivecard_submit'
	/** User who submitted */
	actor: {
		type: string
		id: string
		name: string
	}
	/** Conversation context */
	target: {
		type: 'room'
		id: string
		name: string
	}
	/** Card and response data */
	card: {
		/** Card ID */
		id: string
		/** Collected input values */
		values: Record<string, unknown>
		/** Action that triggered submission */
		action?: string
	}
}

/**
 * Host config for Adaptive Cards renderer
 * Defines styling and behavior
 */
export interface AdaptiveCardsHostConfig {
	supportsInteractivity?: boolean
	fontFamily?: string
	fontSizes?: {
		small?: number
		default?: number
		medium?: number
		large?: number
		extraLarge?: number
	}
	fontWeights?: {
		lighter?: number
		default?: number
		bolder?: number
	}
	containerStyles?: {
		default?: ContainerStyle
		emphasis?: ContainerStyle
		good?: ContainerStyle
		attention?: ContainerStyle
		warning?: ContainerStyle
		accent?: ContainerStyle
	}
	spacing?: {
		small?: number
		default?: number
		medium?: number
		large?: number
		extraLarge?: number
		padding?: number
	}
	separator?: {
		lineThickness?: number
		lineColor?: string
	}
	imageSizes?: {
		small?: number
		medium?: number
		large?: number
	}
	actions?: {
		maxActions?: number
		spacing?: 'default' | 'none' | 'small' | 'medium' | 'large' | 'extraLarge'
		buttonSpacing?: number
		showCard?: {
			actionMode?: 'inline' | 'popup'
			inlineTopMargin?: number
			style?: 'default' | 'emphasis'
		}
		actionsOrientation?: 'horizontal' | 'vertical'
		actionAlignment?: 'left' | 'center' | 'right' | 'stretch'
	}
}

/**
 * Container style configuration
 */
export interface ContainerStyle {
	backgroundColor?: string
	foregroundColors?: {
		default?: ColorConfig
		dark?: ColorConfig
		light?: ColorConfig
		accent?: ColorConfig
		good?: ColorConfig
		warning?: ColorConfig
		attention?: ColorConfig
	}
}

/**
 * Color configuration
 */
export interface ColorConfig {
	default?: string
	subtle?: string
}
