/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module 'vue-draggable-resizable' {
	import type { DefineComponent } from 'vue'

	const VueDraggableResizable: DefineComponent<{
		/* PropsOrPropOptions */
		w?: number | 'auto'
		h?: number | 'auto'
		x?: number
		y?: number
		maxHeight?: number | null
		maxWidth?: number | null
		minHeight?: number
		minWidth?: number
		parent?: boolean
		draggable?: boolean
		resizable?: boolean
		classNameDragging?: string
	}, /* RawBindings */unknown, {
		/* D | Data fields */
		right: number
		bottom: number
		parentWidth: number
		parentHeight: number
	}, /* ComputedOptions */ unknown, {
		/* MethodOptions */
		checkParentSize(): void
		moveHorizontally(val: number): void
		moveVertically(val: number): void
	}>

	export default VueDraggableResizable
}
