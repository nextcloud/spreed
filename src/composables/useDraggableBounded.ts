/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { MaybeRefOrGetter, Ref } from 'vue'

import { useDraggable, useElementSize } from '@vueuse/core'
import { watch } from 'vue'

type ElementRef = Ref<HTMLElement | null | undefined>

/**
 * Make an absolutely positioned element draggable within a parent container,
 * clamping it back into bounds whenever the container is resized.
 *
 * @param target element to drag, must be positioned absolute/fixed
 * @param container element that defines the drag bounds
 * @param options initial position and optional drag handle (defaults to target)
 * @param options.initialValue initial drag position
 * @param options.handle element that starts the drag (defaults to target)
 */
export function useDraggableBounded(
	target: ElementRef,
	container: ElementRef,
	options: {
		initialValue?: MaybeRefOrGetter<{ x: number, y: number }>
		handle?: MaybeRefOrGetter<HTMLElement | null | undefined>
	} = {},
) {
	const { position, isDragging, style } = useDraggable(target, {
		containerElement: container,
		initialValue: options.initialValue,
		handle: options.handle,
		restrictInView: true,
	})

	// Reactive container size, used to re-clamp on resize.
	const { width: containerWidth, height: containerHeight } = useElementSize(container)

	/**
	 * Re-clamp the position into the current container bounds.
	 */
	function clampToBounds() {
		if (!target.value || !containerWidth.value || !containerHeight.value) {
			return
		}

		const targetRect = target.value.getBoundingClientRect()

		position.value = {
			x: Math.min(Math.max(0, position.value.x), Math.max(0, containerWidth.value - targetRect.width)),
			y: Math.min(Math.max(0, position.value.y), Math.max(0, containerHeight.value - targetRect.height)),
		}
	}

	// flush: 'post' ensures the target's DOM size (e.g. resized via CSS
	// container queries) is up to date before we read its rect.
	watch([containerWidth, containerHeight], clampToBounds, { flush: 'post' })

	return { isDragging, style }
}
