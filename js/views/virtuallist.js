/* global _, $ */

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function(_, $) {

	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	/**
	 * Virtual list of DOM elements.
	 *
	 * The virtual list makes possible to create a list with an "unlimited"*
	 * number of elements. Despite the browser optimizations there is a limit in
	 * the number of elements that can be added to a document before the browser
	 * becomes sluggish when the document is further modified (due to having to
	 * layout/reflow a high number of elements); the virtual list solves that by
	 * keeping in the document only those elements that are currently visible,
	 * and refreshing them as needed when the list is scrolled.
	 *
	 * *The actual limit depends, among other things, on the maximum height for
	 * an element supported by the browser, the available memory to hold the
	 * elements, and the performance traversing linked lists, although it should
	 * be high enough for most common uses.
	 *
	 * The virtual list receives the container of the list (the element that the
	 * list elements would have been appended to if the virtual list was not
	 * used) in its constructor.
	 *
	 * The CSS style of the container must have a "visible" or (preferred) an
	 * "auto" value for its "overflow-y" property. Similarly, the positioning of
	 * the ".wrapper-background" and ".wrapper" elements child of the container
	 * must be set to "absolute".
	 *
	 * Elements are appended to the virtual list by first notifying the list
	 * that elements are going to be appended, then appending the elements, and
	 * finally processing the appended elements. Thus, even if there is only one
	 * element to add, first "appendElementStart()" must be called, followed by
	 * one or more calls to "appendElement()" each one with a single element,
	 * and followed by a final call to "appendElementEnd()". Elements are
	 * prepended in a similar way using the equivalent methods.
	 *
	 * The elements in the list can have different heights, and they can
	 * partially overlap their previous or next element due to the use of a
	 * negative top margin, but their top position must not exceed the top
	 * position of its previous element, and their bottom position must not
	 * exceed the bottom position of its next element.
	 *
	 * It is assumed that the position and size of an element will not change
	 * once added to the list. Changing the size of the container could change
	 * the position and size of all the elements, so in that case "reload()"
	 * needs to be called.
	 *
	 * It is also possible to update single elements when their position and
	 * size changes, but only in a very limited scenario: only for the first or
	 * last loaded element and only while prepending or appending new elements.
	 * This makes possible to "seam" the new elements to the existing ones by
	 * changing the CSS classes of the existing ends if needed.
	 *
	 * Some operations on the virtual list, like reloading it, updating the
	 * visible elements or scrolling to certain element, require that the
	 * container is visible; if called while the container is hidden those
	 * operations will just be ignored.
	 *
	 * Adding new elements is still possible while the virtual list is hidden,
	 * but note that "reload()" must be explicitly called once the container is
	 * visible again for the added elements to be loaded.
	 *
	 *
	 *
	 * Internal description:
	 * ---------------------
	 *
	 * Feast your eyes on this glorious ASCII art representation of the virtual
	 * list:
	 *
	 * ············· - List start / Wrapper background start - Top position = 0
	 * ·           ·
	 * ·  _  _  _  · _ First loaded element
	 * ·           ·
	 * · _ _ _ _ _ · _ Wrapper start - Top position ~= scroll position
	 * :___________: _
	 * | ~~~     | |   Viewport start / Container top
	 * | ~~      |||
	 * | ~~      |||
	 * | ~~~~~   | |   Viewport end / Container bottom
	 * :¯¯¯¯¯¯¯¯¯¯¯: ¯
	 * · ¯ ¯ ¯ ¯ ¯ · ¯ Wrapper end
	 * ·           ·
	 * ·           ·
	 * ·  ¯  ¯  ¯  · ¯ Last loaded element
	 * ·           ·
	 * ·           ·
	 * ·           ·
	 * ·           ·
	 * ············· - List end / Wrapper background end
	 *
	 * When the children of an element are larger than its parent and the parent
	 * can not grow any further the parent becomes a viewport for its children:
	 * it can only show a partial area of the children, but it provides an
	 * scroll bar to move the viewport up and down.
	 *
	 * The virtual list is based on that behaviour. In order to reduce the
	 * elements in the document, when the virtual list is set for a container,
	 * only those children of the container that are currently visible in the
	 * viewport are actually in the document; whenever the container is scrolled
	 * the elements are added and removed as needed.
	 *
	 * Specifically, the visible elements are added to and removed from a direct
	 * child of the container, a wrapper that only holds the visible elements.
	 *
	 * Besides the wrapper, the container has another direct children, a
	 * background element that simulates the full length of the list; although
	 * the background is empty its height is set to the height of all the
	 * elements in the list, so when the list is longer than the container the
	 * background causes the scroll bar to appear in the container as if it
	 * contained the real list.
	 *
	 * Both the background and the wrapper have an absolute position; this
	 * absolute position makes possible for the wrapper to move freely over the
	 * background, and also limits the layout calculations only to the wrapper
	 * itself when adding and removing the visible elements (although for better
	 * performance the updates are also done off-line, that is, with the wrapper
	 * detached from the document so only two reflows, one when it is detached
	 * and one when it is attached again, are done no matter the number of
	 * updated elements).
	 *
	 * Whenever the container is scrolled the elements are updated in the
	 * wrapper as needed; the top position of the wrapper is set so its elements
	 * are at the same distance from the top of the background as they would be
	 * if all their previous elements were in the document.
	 *
	 * In order to know where the elements should be in the full list as well as
	 * whether they are visible or not their position and size must have been
	 * calculated before. Thus, when elements are added to the virtual list they
	 * are briefly added to the document in a temporal wrapper; the position of
	 * this temporal wrapper is set based on the already added elements, so the
	 * browser can layout the new elements and their real position and size can
	 * be cached.
	 *
	 * Reloading the list recalculates the position and size of all the
	 * elements. When the list contains a lot of elements it is not possible to
	 * recalculate the values for all the elements at once, so they are first
	 * recalculated for the visible elements and then they are progressively
	 * recalculated for the rest of elements. During that process it is possible
	 * to scroll only to the already loaded elements (although eventually all
	 * the elements will be loaded and it will be possible to scroll again to
	 * any element).
	 */
	var VirtualList = function($container) {
		this._$container = $container;

		this._$firstElement = null;
		this._$lastElement = null;
		this._$firstLoadedElement = null;
		this._$lastLoadedElement = null;
		this._$firstVisibleElement = null;
		this._$lastVisibleElement = null;

		this._$wrapperBackground = $('<div class="wrapper-background"></div>');
		this._$wrapperBackground.height(0);

		this._$wrapper = $('<div class="wrapper"></div>');
		this._$wrapper._top = 0;

		this._$container.append(this._$wrapperBackground);
		this._$container.append(this._$wrapper);

		var self = this;
		this._$container.on('scroll', function() {
			self._lastKnownScrollPosition = self._$container.scrollTop();

			self.updateVisibleElements();
		});
	};

	VirtualList.prototype = {

		getFirstElement: function() {
			return this._$firstElement;
		},

		getFirstVisibleElement: function() {
			return this._$firstVisibleElement;
		},

		getLastElement: function() {
			return this._$lastElement;
		},

		getLastVisibleElement: function() {
			return this._$lastVisibleElement;
		},

		getLastKnownScrollPosition: function() {
			return this._lastKnownScrollPosition;
		},

		isScrollable: function() {
			// In Firefox the scroll bar appears once the contained element is
			// at least 1 pixel larger than the container.
			return this._getElementOuterHeight(this._$wrapperBackground) > (this._getElementHeight(this._$container) + 1);
		},

		prependElementStart: function() {
			this._prependedElementsBuffer = document.createDocumentFragment();

			delete this._$firstPrependedElement;
			delete this._$lastPrependedElement;
		},

		appendElementStart: function() {
			this._appendedElementsBuffer = document.createDocumentFragment();

			delete this._$firstAppendedElement;
			delete this._$lastAppendedElement;
		},

		prependElement: function($element) {
			// ParentNode.prepend() is not compatible with older browsers.
			this._prependedElementsBuffer.insertBefore($element.get(0), this._prependedElementsBuffer.firstChild);

			if (this._$firstElement) {
				this._$firstElement._previous = $element;
			}
			$element._next = this._$firstElement;
			$element._previous = null;
			this._$firstElement = $element;

			if (!this._$lastElement) {
				this._$lastElement = $element;
			}

			if (!this._$firstPrependedElement) {
				this._$firstPrependedElement = $element;
			}
			this._$lastPrependedElement = $element;
		},

		appendElement: function($element) {
			// ParentNode.append() is not compatible with older browsers.
			this._appendedElementsBuffer.appendChild($element.get(0));

			if (this._$lastElement) {
				this._$lastElement._next = $element;
			}
			$element._previous = this._$lastElement;
			$element._next = null;
			this._$lastElement = $element;

			if (!this._$firstElement) {
				this._$firstElement = $element;
			}

			if (!this._$firstAppendedElement) {
				this._$firstAppendedElement = $element;
			}
			this._$lastAppendedElement = $element;
		},

		prependElementEnd: function() {
			if (this._isContainerHidden()) {
				delete this._prependedElementsBuffer;

				return;
			}

			// If the prepended elements are not immediately before the first
			// loaded element there is nothing to load now; they will be loaded
			// as needed with the other pending elements.
			if (this._$firstPrependedElement._next !== this._$firstLoadedElement) {
				delete this._prependedElementsBuffer;

				return;
			}

			if (this._lastContainerWidth !== this._$container.width()) {
				delete this._prependedElementsBuffer;

				this.reload();

				return;
			}

			this._loadPreviousElements(
				this._$firstPrependedElement,
				this._$lastPrependedElement,
				this._prependedElementsBuffer
			);

			delete this._prependedElementsBuffer;

			this.updateVisibleElements();
		},

		appendElementEnd: function() {
			if (this._isContainerHidden()) {
				delete this._prependedElementsBuffer;

				return;
			}

			// If the appended elements are not immediately after the last
			// loaded element there is nothing to load now; they will be loaded
			// as needed with the other pending elements.
			if (this._$firstAppendedElement._previous !== this._$lastLoadedElement) {
				delete this._appendedElementsBuffer;

				return;
			}

			if (this._lastContainerWidth !== this._$container.width()) {
				delete this._appendedElementsBuffer;

				this.reload();

				return;
			}

			this._loadNextElements(
				this._$firstAppendedElement,
				this._$lastAppendedElement,
				this._appendedElementsBuffer
			);

			delete this._appendedElementsBuffer;

			this.updateVisibleElements();
		},

		/**
		 * Notifies the virtual list that the position and size of the given
		 * element may have changed.
		 *
		 * Updating an element is only possible while new elements are being
		 * prepended or appended, that is, between the calls to
		 * "prepend/appendElementStart" and "prepend/appendElementEnd", and only
		 * for the element at the end being modified.
		 *
		 * @param {jQuery} $element the element to update.
		 */
		updateElement: function($element) {
			if (!this._prependedElementsBuffer && !this._appendedElementsBuffer) {
				return;
			}

			if (this._prependedElementsBuffer && $element !== this._$firstLoadedElement) {
				return;
			}

			if (this._appendedElementsBuffer && $element !== this._$lastLoadedElement) {
				return;
			}

			$element._dirty = true;
		},

		/**
		 * Reloads the list to adjust to the new size of the container.
		 *
		 * This needs to be called whenever the size of the container has
		 * changed.
		 *
		 * When the width of the container has changed it is not possible to
		 * guarantee that exactly the same elements that were visible before
		 * will be visible after the list is reloaded. Due to this, in those
		 * cases reloading the list just ensures that the last element that was
		 * partially visible before will be fully visible after the list is
		 * reloaded.
		 *
		 * On the other hand, when only the height has changed no reload is
		 * needed; in that case the visibility of the elements is updated based
		 * on the new height. If some elements were added to the list while its
		 * container was hidden they will be loaded too without a full reload.
		 *
		 * Reloading the list requires to recalculate the position and size of
		 * all the elements. The initial call reloads the last visible element
		 * (if any) and some of its previous and next siblings; the rest of the
		 * elements will be queued to be progressively updated until all are
		 * loaded. During this process it is possible to scroll only to those
		 * elements already loaded, although further elements can be appended or
		 * prepended if needed and they will be available once the reload ends.
		 *
		 * In browsers with subpixel accuracy for the position and size that use
		 * integer values for the scroll position, like Firefox, reloading the
		 * list causes a wiggly effect (and, in some cases, a slight drift) due
		 * to prepending the elements and trying to keep the scroll position, as
		 * the scroll position is rounded to an int but the position of the
		 * elements is a float.
		 */
		reload: function() {
			if (this._isContainerHidden()) {
				return;
			}

			if (this._lastContainerWidth === this._$container.width()) {
				// If the width is the same the cache is still valid, so no need
				// for a full reload.
				this.updateVisibleElements();

				if (this._$firstLoadedElement !== this._$firstElement ||
						this._$lastLoadedElement !== this._$lastElement) {
					this._queueLoadOfPendingElements();
				}

				return;
			}

			if (this._pendingLoad) {
				clearTimeout(this._pendingLoad);
				delete this._pendingLoad;
			}

			this._lastContainerWidth = this._$container.width();

			var $initialElement = this._$lastVisibleElement;
			if (!$initialElement) {
				// No element was visible; either the list was reloaded when
				// empty or during the first append/prepend of elements.
				$initialElement = this._$lastElement;
			}

			if (!$initialElement) {
				// The list is empty, so there is nothing to load.
				return;
			}

			// Detach all the visible elements from the wrapper
			this._$wrapper.detach();

			while (this._$firstVisibleElement && this._$firstVisibleElement !== this._$lastVisibleElement._next) {
				this._$firstVisibleElement.detach();
				this._$firstVisibleElement = this._$firstVisibleElement._next;
			}

			this._$firstVisibleElement = null;
			this._$lastVisibleElement = null;

			this._$wrapper._top = 0;
			this._$wrapper.css('top', this._$wrapper._top);

			this._$wrapper.appendTo(this._$container);

			// Reset wrapper background
			this._setWrapperBackgroundHeight(0);

			this._loadInitialElements($initialElement);

			// Scroll to the last visible element, or to the top of the next one
			// to prevent it from becoming the last visible element when the
			// visibilities are updated.
			if ($initialElement._next) {
				// The implicit "Math.floor()" on the scroll position when the
				// browser has subpixel accuracy but uses int positions for
				// scrolling ensures that the next element to the last visible
				// one will not become visible (which could happen if the value
				// was rounded instead).
				this._$container.scrollTop($initialElement._next._top - this._getElementOuterHeightWithoutMargins(this._$container));
			} else {
				// As the last visible element is also the last element this
				// simply scrolls the list to the bottom.
				this._$container.scrollTop($initialElement._top + $initialElement._height);
			}

			this.updateVisibleElements();

			this._queueLoadOfPendingElements();
		},

		_loadInitialElements: function($initialElement) {
			var $firstElement = $initialElement;
			var $lastElement = $firstElement;

			var elementsBuffer = document.createDocumentFragment();

			var $currentElement = $firstElement;
			var i;
			for (i = 0; i < 50 && $currentElement; i++) {
				// ParentNode.prepend() is not compatible with older browsers.
				elementsBuffer.insertBefore($currentElement.get(0), elementsBuffer.firstChild);
				$lastElement = $currentElement;
				$currentElement = $currentElement._previous;
			}

			$currentElement = $firstElement._next;
			for (i = 0; i < 50 && $currentElement; i++) {
				// ParentNode.append() is not compatible with older browsers.
				elementsBuffer.appendChild($currentElement.get(0));
				$firstElement = $currentElement;
				$currentElement = $currentElement._next;
			}

			this._$firstLoadedElement = null;
			this._$lastLoadedElement = null;

			this._loadPreviousElements(
				$firstElement,
				$lastElement,
				elementsBuffer
			);

			// FIXME it is happily assumed that the initial load covers the full
			// view with 50 and 50 elements before and after... but it should be
			// actually verified and enforced loading again other elements as
			// needed.
		},

		_queueLoadOfPendingElements: function() {
			if (this._pendingLoad) {
				return;
			}

			// To load the elements they need to be rendered again, so it is a
			// rather costly operation. A small interval between loads, even
			// with just a few elements, could hog the browser and cause its UI
			// to become unresponsive, so a "long" interval is used instead; to
			// compensate for the "long" interval the number of elements loaded
			// in each batch is rather large, but still within a reasonable
			// limit that should be renderable by the browser without causing
			// (much :-) ) jank.
			this._pendingLoad = setTimeout(function() {
				delete this._pendingLoad;

				if (this._isContainerHidden()) {
					return;
				}

				var numberOfElementsToLoad = 200;
				numberOfElementsToLoad -= this._loadPreviousPendingElements(numberOfElementsToLoad/2);
				this._loadNextPendingElements(numberOfElementsToLoad);

				// The loaded elements are out of view (it is assumed that the
				// initial load of elements cover the full visible area), so no
				// need to update the visible elements.
			}.bind(this), 100);
		},

		_loadPreviousPendingElements: function(numberOfElementsToLoad) {
			if (!this._$firstLoadedElement || this._$firstLoadedElement === this._$firstElement) {
				return 0;
			}

			var prependedElementsBuffer = document.createDocumentFragment();

			var $firstPrependedElement = this._$firstLoadedElement._previous;
			var $lastPrependedElement = $firstPrependedElement;

			var $currentElement = $firstPrependedElement;
			var i;
			for (i = 0; i < numberOfElementsToLoad && $currentElement; i++) {
				// ParentNode.prepend() is not compatible with older browsers.
				prependedElementsBuffer.insertBefore($currentElement.get(0), prependedElementsBuffer.firstChild);
				$lastPrependedElement = $currentElement;
				$currentElement = $currentElement._previous;
			}

			this._loadPreviousElements(
				$firstPrependedElement,
				$lastPrependedElement,
				prependedElementsBuffer
			);

			this._queueLoadOfPendingElements();

			return i;
		},

		_loadNextPendingElements: function(numberOfElementsToLoad) {
			if (!this._$lastLoadedElement || this._$lastLoadedElement === this._$lastElement) {
				return 0;
			}

			var appendedElementsBuffer = document.createDocumentFragment();

			var $firstAppendedElement = this._$lastLoadedElement._next;
			var $lastAppendedElement = $firstAppendedElement;

			var $currentElement = $firstAppendedElement;
			var i;
			for (i = 0; i < numberOfElementsToLoad && $currentElement; i++) {
				// ParentNode.append() is not compatible with older browsers.
				appendedElementsBuffer.appendChild($currentElement.get(0));
				$lastAppendedElement = $currentElement;
				$currentElement = $currentElement._next;
			}

			this._loadNextElements(
				$firstAppendedElement,
				$lastAppendedElement,
				appendedElementsBuffer
			);

			this._queueLoadOfPendingElements();

			return i;
		},

		_loadPreviousElements: function($firstElementToLoad, $lastElementToLoad, elementsBuffer) {
			var $wrapper = $('<div class="wrapper"></div>');
			$wrapper._top = 0;

			var elementToUpdateOldHeight = 0;

			var $firstExistingElement = $firstElementToLoad._next;

			if ($firstExistingElement && $firstExistingElement._dirty) {
				// If the first existing element needs to be updated it is
				// loaded again along with the other elements to load; however,
				// as the element is already loaded, its height needs to be
				// removed from the overall height of the list and all the
				// other elements after it.
				elementToUpdateOldHeight = $firstExistingElement._height;

				// If the element was visible appending it to the buffer would
				// remove it from the main wrapper, so a clone that acts as a
				// proxy for the real element is used instead.
				var $firstExistingElementProxy = $firstExistingElement.clone();
				$firstExistingElementProxy._previous = $firstExistingElement._previous;
				$firstExistingElementProxy._next = $firstExistingElement._next;
				$firstExistingElementProxy._updateProxyFor = $firstExistingElement;

				// ParentNode.append() is not compatible with older browsers.
				elementsBuffer.appendChild($firstExistingElementProxy.get(0));

				$firstElementToLoad = $firstExistingElementProxy;

				$firstExistingElement = $firstExistingElement._next;

				if ($firstExistingElement) {
					// If there is another element after the one to update then
					// the height to remove is not the full height of the
					// element, but just until the top raw position of its next
					// element to account for collapsing margins.
					elementToUpdateOldHeight = $firstExistingElement._topRaw - $firstExistingElement._previous._topRaw;
				}
			}

			var $firstExistingElementClone = null;
			if ($firstExistingElement) {
				// The wrapper is already at the top, so no need to set its
				// position.

				$firstExistingElementClone = $firstExistingElement.clone();

				// Include the next element, as its position may change due to
				// collapsing margins.
				$wrapper.append($firstExistingElementClone);
			}

			this._$container.append($wrapper);

			var wrapperHeightWithoutElementsToLoad = this._getElementHeight($wrapper);

			$wrapper.prepend(elementsBuffer);

			var firstExistingElementTopRawDifference = 0;
			if ($firstExistingElement && $firstExistingElement._previous._dirty && $firstExistingElement._previous === this._$firstVisibleElement) {
				// The clone is not a proxy
				this._updateCache($firstExistingElementClone, $wrapper);
				this._updateCache($firstElementToLoad, $wrapper);
				firstExistingElementTopRawDifference = elementToUpdateOldHeight - ($firstExistingElementClone._topRaw - $firstElementToLoad._updateProxyFor._topRaw);
			}

			var wrapperHeightDifference = this._getElementHeight($wrapper) - wrapperHeightWithoutElementsToLoad - elementToUpdateOldHeight;

			this._setWrapperBackgroundHeight(this._getElementHeight(this._$wrapperBackground) + wrapperHeightDifference);

			// Note that the order of "first/last" is not the same for the main
			// elements and the elements passed to this method.
			if (!this._$lastLoadedElement) {
				this._$lastLoadedElement = $firstElementToLoad;
			}
			this._$firstLoadedElement = $lastElementToLoad;

			while ($firstElementToLoad !== $lastElementToLoad._previous) {
				this._updateCache($firstElementToLoad, $wrapper);

				$firstElementToLoad = $firstElementToLoad._previous;
			}

			// Remove the temporal wrapper used to layout and get the height of
			// the added items.
			$wrapper.detach();
			$wrapper.children().detach();
			$wrapper.remove();

			// Update the cached position of elements after the prepended ones.
			while ($firstExistingElement !== this._$lastLoadedElement._next) {
				$firstExistingElement._top += wrapperHeightDifference;
				$firstExistingElement._topRaw += wrapperHeightDifference;

				$firstExistingElement = $firstExistingElement._next;
			}

			// Keep the scrolling at the same point as before the elements were
			// prepended.
			// Despite having subpixel accuracy for positions and sizes, Firefox
			// uses integer values for the scroll position, so the proper scroll
			// position would be implicitly truncated. Instead, the scroll
			// position is explicitly rounded to mitigate a progressive "drift"
			// when several batches of elements are prepended.
			// Note, however, that rounded the value just mitigates, but does
			// not fully prevent the drift, and when several batches are
			// prepended in a row in a short period of time the result is a
			// wiggly effect in the existing elements due to the successive
			// corrections in the scroll positions.
			// Besides that, the drawback of this approach is that the scrolling
			// in browsers with subpixel accuracy and float values for the
			// scroll position (maybe Firefox mobile?) will not be as accurate
			// as it could be.
			this._$container.scrollTop(Math.round(this._$container.scrollTop() + wrapperHeightDifference));

			// Update the position of the wrapper with the visible elements.
			// This is needed even if "updateVisibleElements()" is called later,
			// as it could "short circuit" before reaching the point where the
			// wrapper position is updated.
			if (this._$firstVisibleElement) {
				// Adding the wrapperHeightDifference restores the wrapper
				// position after the update of the scroll position, but it is
				// necessary to add the first existing element top raw
				// difference to restore its position when the previous element
				// was also updated.
				this._$wrapper._top += wrapperHeightDifference + firstExistingElementTopRawDifference;
				this._$wrapper.css('top', this._$wrapper._top);
			}
		},

		_loadNextElements: function($firstElementToLoad, $lastElementToLoad, elementsBuffer) {
			var $wrapper = $('<div class="wrapper"></div>');
			$wrapper._top = 0;

			var elementToUpdateOldHeight = 0;

			var $firstExistingElement = $firstElementToLoad._previous;
			if ($firstExistingElement && $firstExistingElement._dirty) {
				// If the first existing element needs to be updated it is
				// loaded again along with the other elements to load; however,
				// as the element is already loaded, its height needs to be
				// removed from the overall height of the list.
				elementToUpdateOldHeight = $firstExistingElement._height;

				// If the element was visible appending it to the buffer would
				// remove it from the main wrapper, so a clone that acts as a
				// proxy for the real element is used instead.
				var $firstExistingElementProxy = $firstExistingElement.clone();
				$firstExistingElementProxy._previous = $firstExistingElement._previous;
				$firstExistingElementProxy._next = $firstExistingElement._next;
				$firstExistingElementProxy._updateProxyFor = $firstExistingElement;

				// ParentNode.prepend() is not compatible with older browsers.
				elementsBuffer.insertBefore($firstExistingElementProxy.get(0), elementsBuffer.firstChild);

				$firstElementToLoad = $firstExistingElementProxy;
			}

			if ($firstElementToLoad._previous) {
				$wrapper.css('top', $firstElementToLoad._previous._topRaw);
				$wrapper._top = $firstElementToLoad._previous._topRaw;

				// Include the previous element, as it may change the
				// position of the newest element due to collapsing margins
				$wrapper.append($firstElementToLoad._previous.clone());
			}

			this._$container.append($wrapper);

			var wrapperHeightWithoutElementsToLoad = this._getElementHeight($wrapper);

			$wrapper.append(elementsBuffer);

			var wrapperHeightDifference = this._getElementHeight($wrapper) - wrapperHeightWithoutElementsToLoad - elementToUpdateOldHeight;

			this._setWrapperBackgroundHeight(this._getElementHeight(this._$wrapperBackground) + wrapperHeightDifference);

			if (!this._$firstLoadedElement) {
				this._$firstLoadedElement = $firstElementToLoad;
			}
			this._$lastLoadedElement = $lastElementToLoad;

			while ($firstElementToLoad !== $lastElementToLoad._next) {
				this._updateCache($firstElementToLoad, $wrapper);

				$firstElementToLoad = $firstElementToLoad._next;
			}

			// Remove the temporal wrapper used to layout and get the height of
			// the added items.
			$wrapper.detach();
			$wrapper.children().detach();
			$wrapper.remove();
		},

		/**
		 * Updates the cached position and size of the given element.
		 *
		 * The element must be a child of a wrapper currently in the container
		 * (although it can be a temporal wrapper, it does not need to be the
		 * main one); detached elements can not be used, as the values to cache
		 * would be invalid in that case.
		 *
		 * Although the element must be a child of the given wrapper the element
		 * can be acting as a proxy for a different element (for example, the
		 * given element could be a clone in a temporal wrapper and act as an
		 * update proxy for another element in the main wrapper); in that case
		 * the cached values will be set in the element proxied for instead of
		 * in the given element.
		 *
		 * The element top position is relative to the wrapper, and the wrapper
		 * top position plus the element top position is expected to place the
		 * element at the proper offset from the top of the container.
		 *
		 * @param {jQuery} $element the element to update its cache.
		 * @param {jQuery} $wrapper the parent wrapper of the element.
		 */
		_updateCache: function($element, $wrapper) {
			var $elementToUpdate = $element;
			if ($element._updateProxyFor) {
				$elementToUpdate = $element._updateProxyFor;
			}

			delete $elementToUpdate._dirty;

			$elementToUpdate._height = this._getElementOuterHeight($element);

			// The top position of an element must be got from the element
			// itself; it can not be based on the top position and height of the
			// previous element, because the browser may merge/collapse the
			// margins.
			$elementToUpdate._top = $wrapper._top + this._getElementTopPosition($element);
			$elementToUpdate._topRaw = $elementToUpdate._top;
			var marginTop = parseFloat($element.css('margin-top'));
			if (marginTop < 0) {
				$elementToUpdate._topRaw -= marginTop;
			}
		},

		/**
		 * Returns the top position, from the top margin, of the given element.
		 *
		 * The returned value takes into account a negative top margin, which
		 * pulls up the element closer to the previous element.
		 *
		 * @param jQuery $element the jQuery element to get its height.
		 */
		_getElementTopPosition: function($element) {
			// When the margin is positive, jQuery returns the proper top
			// position of the element (that is, including the top margin).
			// However, when it is negative, jQuery returns where the top
			// position of the element would be if there was no margin, so in
			// those cases the top position returned by jQuery is below the
			// actual top position of the element.
			var marginTop = parseFloat($element.css('margin-top'));
			if (marginTop >= 0) {
				return $element.position().top;
			}

			return $element.position().top + marginTop;
		},

		/**
		 * Returns the height of the given element.
		 *
		 * This must be used instead of jQuery.height(); before the 3.0.0
		 * release jQuery rounded the height to the nearest integer, but Firefox
		 * has subpixel accuracy, so the height returned by jQuery can not be
		 * used in the calculations.
		 *
		 * @param jQuery $element the jQuery element to get its height.
		 */
		_getElementHeight: function($element) {
			var paddingTop = parseFloat($element.css('padding-top'));
			var paddingBottom = parseFloat($element.css('padding-bottom'));

			return $element.get(0).getBoundingClientRect().height - paddingTop - paddingBottom;
		},

		/**
		 * Returns the outer height, without margins, of the given element.
		 *
		 * The returned value includes the height, the padding and the border.
		 *
		 * This must be used instead of jQuery.height(); before the 3.0.0
		 * release jQuery rounded the height to the nearest integer, but Firefox
		 * has subpixel accuracy, so the height returned by jQuery can not be
		 * used in the calculations.
		 *
		 * @param jQuery $element the jQuery element to get its height.
		 */
		_getElementOuterHeightWithoutMargins: function($element) {
			// Although before jQuery 3.0.0 the height is rounded to the nearest
			// integer the padding and border width, on the other hand, are
			// returned as a float value as expected.
			var paddingTop = parseFloat($element.css('padding-top'));
			var paddingBottom = parseFloat($element.css('padding-bottom'));
			var borderTop = parseFloat($element.css('border-top-width'));
			var borderBottom = parseFloat($element.css('border-bottom-width'));

			return this._getElementHeight($element) + paddingTop + paddingBottom + borderTop + borderBottom;
		},

		/**
		 * Returns the full outer height, with margins, of the given element.
		 *
		 * The returned value includes the height, the padding, the border and
		 * the margin; negative margins are not taken into account, as they do
		 * not affect the visible height of the element; they only pull up the
		 * element (negative top margin) or its next element (negative bottom
		 * margin), but without modifying its visible height.
		 *
		 * This must be used instead of jQuery.height(); before the 3.0.0
		 * release jQuery rounded the height to the nearest integer, but Firefox
		 * has subpixel accuracy, so the height returned by jQuery can not be
		 * used in the calculations.
		 *
		 * @param jQuery $element the jQuery element to get its height.
		 */
		_getElementOuterHeight: function($element) {
			// Although before jQuery 3.0.0 the height is rounded to the nearest
			// integer the margin, on the other hand, is returned as a float
			// value as expected.
			// Besides that note that outerHeight(true) would return a smaller
			// height than the actual height when there are negative margins, as
			// in that case jQuery would substract the negative margin from the
			// overall height of the element.
			var marginTop = Math.max(0, parseFloat($element.css('margin-top')));
			var marginBottom = Math.max(0, parseFloat($element.css('margin-bottom')));

			return this._getElementOuterHeightWithoutMargins($element) + marginTop + marginBottom;
		},

		_setWrapperBackgroundHeight: function(height) {
			// Although getting the height with jQuery < 3.X rounds to the
			// nearest integer setting the height respects the given float
			// number.
			this._$wrapperBackground.height(height);

			// If the container is scrollable set its "tabindex" attribute so it
			// is included in the sequential keyboard navigation.
			if (this.isScrollable()) {
				this._$container.attr('tabindex', 0);
			} else {
				this._$container.removeAttr('tabindex');
			}
		},

		/**
		 * Updates the visible elements.
		 *
		 * Elements no longer in the viewport are removed, while elements now in
		 * the viewport are added.
		 *
		 * Note that the float precision problems are not handled in the
		 * visibility checks, so in browsers with subpixel accuracy, like
		 * Firefox, elements in which their bottom is very very close to the top
		 * of the container, or elements in which their top is very very close
		 * to the bottom of the container may be shown or hidden when they
		 * should not. However, this should not be a problem, as only fractions
		 * of a pixel would be wrongly shown or hidden.
		 */
		updateVisibleElements: function() {
			if (this._isContainerHidden()) {
				return;
			}

			if (!this._$firstVisibleElement && !this._$firstLoadedElement) {
				return;
			}

			if (!this._$firstVisibleElement) {
				this._$firstVisibleElement = this._$firstLoadedElement;
				this._$lastVisibleElement = this._$firstVisibleElement;

				this._$wrapper.append(this._$firstVisibleElement);
			}

			var visibleAreaTop = this._$container.scrollTop();
			var visibleAreaBottom = visibleAreaTop + this._getElementOuterHeightWithoutMargins(this._$container);

			var firstVisibleElementIsStillPartiallyVisible =
					this._$firstVisibleElement._top <= visibleAreaTop &&
					this._$firstVisibleElement._top + this._$firstVisibleElement._height > visibleAreaTop;
			var lastVisibleElementIsStillPartiallyVisible =
					this._$lastVisibleElement._top < visibleAreaBottom &&
					this._$lastVisibleElement._top + this._$lastVisibleElement._height >= visibleAreaBottom;
			// The first element could be being pulled up into its previous
			// element due to a negative top margin, so it is necessary to
			// ensure that the previous element is not visible even if the first
			// one "crosses" the top of the visible area.
			var previousElementToFirstVisibleElementIsNotVisibleYet =
					!this._$firstVisibleElement._previous ||
					this._$firstVisibleElement._previous._top + this._$firstVisibleElement._previous._height <= visibleAreaTop;
			// The next element could be pulled up into the last visible element
			// due to a negative top margin, so it is necessary to ensure that
			// it is not visible even if the last one "crosses" the bottom of
			// the visible area.
			var nextElementToLastVisibleElementIsNotVisibleYet =
					!this._$lastVisibleElement._next ||
					this._$lastVisibleElement._next._top >= visibleAreaBottom;

			if (firstVisibleElementIsStillPartiallyVisible &&
					lastVisibleElementIsStillPartiallyVisible &&
					previousElementToFirstVisibleElementIsNotVisibleYet &&
					nextElementToLastVisibleElementIsNotVisibleYet) {
				return;
			} else {
				this._$wrapper.detach();
			}

			// The currently visible area does not contain any of the visible
			// elements.
			if (this._$firstVisibleElement._top >= visibleAreaBottom ||
					this._$lastVisibleElement._top + this._$lastVisibleElement._height <= visibleAreaTop) {
				// Remove all visible elements.
				while (this._$firstVisibleElement !== this._$lastVisibleElement._next) {
					this._$firstVisibleElement.detach();
					this._$firstVisibleElement = this._$firstVisibleElement._next;
				}

				// Show the new first visible element.
				this._$firstVisibleElement = this._$firstLoadedElement;
				while (this._$firstVisibleElement._top + this._$firstVisibleElement._height <= visibleAreaTop) {
					this._$firstVisibleElement = this._$firstVisibleElement._next;
				}

				this._$firstVisibleElement.prependTo(this._$wrapper);

				this._$lastVisibleElement = this._$firstVisibleElement;
			}

			// Remove leading elements no longer visible.
			while (this._$firstVisibleElement._top + this._$firstVisibleElement._height <= visibleAreaTop) {
				this._$firstVisibleElement.detach();
				this._$firstVisibleElement = this._$firstVisibleElement._next;
			}

			// Prepend leading elements now visible.
			while (this._$firstVisibleElement._previous &&
					this._$firstVisibleElement._previous !== this._$firstLoadedElement._previous &&
					this._$firstVisibleElement._previous._top + this._$firstVisibleElement._previous._height > visibleAreaTop) {
				this._$firstVisibleElement._previous.prependTo(this._$wrapper);
				this._$firstVisibleElement = this._$firstVisibleElement._previous;
			}

			// Align wrapper with the top raw position (without negative
			// margins) of the first visible element.
			this._$wrapper._top = this._$firstVisibleElement._topRaw;
			this._$wrapper.css('top', this._$wrapper._top);

			// Remove trailing elements no longer visible.
			while (this._$lastVisibleElement._top >= visibleAreaBottom) {
				this._$lastVisibleElement.detach();
				this._$lastVisibleElement = this._$lastVisibleElement._previous;
			}

			// Append trailing elements now visible.
			while (this._$lastVisibleElement._next &&
					this._$lastVisibleElement._next !== this._$lastLoadedElement._next &&
					this._$lastVisibleElement._next._top < visibleAreaBottom) {
				this._$lastVisibleElement._next.appendTo(this._$wrapper);
				this._$lastVisibleElement = this._$lastVisibleElement._next;
			}

			this._$wrapper.appendTo(this._$container);
		},

		_isContainerHidden: function() {
			return this._$container.is(":hidden");
		},

		/**
		 * Scroll the list to the given element.
		 *
		 * The element will be aligned with the top of the list (or as far as
		 * possible, in case the element is at the bottom).
		 *
		 * @param {jQuery} $element the element of the list to scroll to.
		 */
		scrollTo: function($element) {
			if (this._isContainerHidden()) {
				return;
			}

			if (!this._isLoaded($element)) {
				return;
			}

			this._$container.scrollTop($element._top);

			// The visible elements are updated when the scroll event is
			// handled. However, as the scroll event is asynchronous, it is not
			// guaranteed that it will be handled before this method returns; as
			// the caller could expect that the visibility of elements is
			// updated when scrolling programatically this must be explicitly
			// done.
			// Note that, although the event is handled asynchronously (and in
			// some cases several scrolls can be merged in a single event) the
			// value returned by scrollTop() is always the expected one
			// immediately after setting it with scrollTop(value).
			this.updateVisibleElements();
		},

		/**
		 * Returns whether the given element is loaded or not.
		 *
		 * @param {jQuery} $element the element to check.
		 * @return true if the element is loaded, false otherwise.
		 */
		_isLoaded: function($element) {
			if (!this._$firstLoadedElement || !this._$lastLoadedElement) {
				return false;
			}

			var $currentElement = this._$firstLoadedElement;
			while ($currentElement !== this._$lastLoadedElement._next) {
				if ($currentElement === $element) {
					return true;
				}

				$currentElement = $currentElement._next;
			}

			return false;
		},

	};

	OCA.SpreedMe.Views.VirtualList = VirtualList;

})(_, $);
