/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module 'vue-draggable-resizable' {
	import type { DefineComponent } from "vue";

	const VueDraggableResizable: DefineComponent<{
		w?: number | "auto";
		h?: number | "auto";
		x?: number;
		y?: number;
		maxHeight?: number | null;
		maxWidth?: number | null;
		minHeight?: number;
		minWidth?: number;
		parent?: boolean;
		draggable?: boolean;
		resizable?: boolean;
	}, {}, {
		// Data fields
		right: number;
		bottom: number;
		parentWidth: number;
		parentHeight: number;
	}, {}, {
		// Public methods
		checkParentSize(): void;
		moveHorizontally(val: number): void;
		moveVertically(val: number): void;
	}>

	export default VueDraggableResizable
}