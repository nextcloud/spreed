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
	 * and followed by a final call to "appendElementEnd()".
	 *
	 * The elements in the list can have different heights, and they can
	 * partially overlap their previous or next element due to the use of a
	 * negative top margin, but their top position must not exceed the top
	 * position of its previous element, and their bottom position must not
	 * exceed the bottom position of its next element.
	 *
	 * It is assumed that the position and size of an element will not change
	 * once added to the list.
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
	 * · _ _ _ _ _ · _ Wrapper start - Top position ~= scroll position
	 * :___________: _
	 * | ~~~     | |   Viewport start / Container top
	 * | ~~      |||
	 * | ~~      | |
	 * | ~~~~~   | |   Viewport end / Container bottom
	 * :¯¯¯¯¯¯¯¯¯¯¯: ¯
	 * · ¯ ¯ ¯ ¯ ¯ · ¯ Wrapper end
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
	 */
	var VirtualList = function($container) {
		this._$container = $container;

		this._$firstElement = null;
		this._$lastElement = null;
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
			self.updateVisibleElements();
		});
	};

	VirtualList.prototype = {

		appendElementStart: function() {
			this._appendedElementsBuffer = document.createDocumentFragment();

			delete this._$firstAppendedElement;
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
		},

		appendElementEnd: function() {
			var $wrapper = $('<div class="wrapper"></div>');
			$wrapper._top = 0;

			if (this._$firstAppendedElement._previous) {
				$wrapper.css('top', this._$firstAppendedElement._previous._topRaw);
				$wrapper._top = this._$firstAppendedElement._previous._topRaw;

				// Include the previous element, as it may change the
				// position of the newest element due to collapsing margins
				$wrapper.append(this._$firstAppendedElement._previous.clone());
			}

			this._$container.append($wrapper);

			var previousWrapperHeight = this._getElementHeight($wrapper);

			$wrapper.append(this._appendedElementsBuffer);
			delete this._appendedElementsBuffer;

			var wrapperHeightDifference = this._getElementHeight($wrapper) - previousWrapperHeight;

			// Although getting the height with jQuery < 3.X rounds to the
			// nearest integer setting the height respects the given float
			// number.
			this._$wrapperBackground.height(this._getElementHeight(this._$wrapperBackground) + wrapperHeightDifference);

			while (this._$firstAppendedElement) {
				this._updateCache(this._$firstAppendedElement, $wrapper);

				this._$firstAppendedElement = this._$firstAppendedElement._next;
			}

			// Remove the temporal wrapper used to layout and get the height of
			// the added items.
			$wrapper.detach();
			$wrapper.children().detach();
			$wrapper.remove();

			this.updateVisibleElements();
		},

		/**
		 * Updates the cached position and size of the given element.
		 *
		 * The element must be a child of a wrapper currently in the container
		 * (although it can be a temporal wrapper, it does not need to be the
		 * main one); detached elements can not be used, as the values to cache
		 * would be invalid in that case.
		 *
		 * The element top position is relative to the wrapper, and the wrapper
		 * top position plus the element top position is expected to place the
		 * element at the proper offset from the top of the container.
		 *
		 * @param {jQuery} $element the element to update its cache.
		 * @param {jQuery} $wrapper the parent wrapper of the element.
		 */
		_updateCache: function($element, $wrapper) {
			$element._height = this._getElementOuterHeight($element);

			// The top position of an element must be got from the element
			// itself; it can not be based on the top position and height of the
			// previous element, because the browser may merge/collapse the
			// margins.
			$element._top = $wrapper._top + this._getElementTopPosition($element);
			$element._topRaw = $element._top;
			var marginTop = parseFloat($element.css('margin-top'));
			if (marginTop < 0) {
				$element._topRaw -= marginTop;
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
			return $element.get(0).getBoundingClientRect().height;
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
			if (!this._$firstVisibleElement && !this._$firstElement) {
				return;
			}

			if (!this._$firstVisibleElement) {
				this._$firstVisibleElement = this._$firstElement;
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
				this._$firstVisibleElement = this._$firstElement;
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
					this._$lastVisibleElement._next._top < visibleAreaBottom) {
				this._$lastVisibleElement._next.appendTo(this._$wrapper);
				this._$lastVisibleElement = this._$lastVisibleElement._next;
			}

			this._$wrapper.appendTo(this._$container);
		},

	};

	OCA.SpreedMe.Views.VirtualList = VirtualList;

})(_, $);
