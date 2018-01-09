/* global Backbone, OCA */

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

(function(OCA, Backbone) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	/**
	 * Helper button to expand an element in an ancestor.
	 *
	 * The expander works directly on HTML elements and it does not take into
	 * account Backbone or Marionette views; it simply traverses the ancestors
	 * of the given $expandableElement hiding their siblings until $container is
	 * reached.
	 *
	 * The button is automatically prepended to the $expandableElement when
	 * created. Clicking on the button will expand the element and clicking
	 * again will restore it. The button can be removed by calling "remove()" on
	 * the expander object.
	 */
	var ElementExpanderButton = Backbone.View.extend({

		tagName: 'a',
		className:'elementExpanderButton icon-fullscreen',

		events: {
			'click': '_onClick',
		},

		initialize: function(options) {
			if (!('$expandableElement' in options)) {
				throw 'Missing parameter "$expandableElement"';
			}
			if (!('$container' in options)) {
				throw 'Missing parameter "$container"';
			}

			this._$expandableElement = options.$expandableElement;
			this._$container = options.$container;

			this._expanded = false;
			this._hiddenElements = new Array();

			this.render().$el.prependTo(this._$expandableElement);
		},

		render: function() {
			this.$el.attr('href', '#');

			if (this._expanded) {
				this.$el.html('<span class="hidden-visually">' + t('spreed', 'Restore') + '</span>');
			} else {
				this.$el.html('<span class="hidden-visually">' + t('spreed', 'Expand') + '</span>');
			}

			return this;
		},

		_onClick: function() {
			if (!this._expanded) {
				this._expand();
			} else {
				this._restore();
			}
		},

		_expand: function() {
			var self = this;

			this._$expandableElement.parents().each(function(index, parent) {
				var $parent = $(parent);
				if ($parent.is(self._$container)) {
					return false;
				}

				$parent.siblings().each(function(index, sibling) {
					var $sibling = $(sibling);
					self._hiddenElements.push($sibling);
					$sibling.hide();
				});
			});

			this._$expandableElement.addClass('expanded');

			this.$el.find('span').text(t('spreed', 'Restore'));

			this._expanded = true;
		},

		_restore: function() {
			this._hiddenElements.forEach(function($element) {
				$element.show();
			});
			this._hiddenElements = [];

			this._$expandableElement.removeClass('expanded');

			this.$el.find('span').text(t('spreed', 'Expand'));

			this._expanded = false;
		}

	});

	OCA.SpreedMe.Views.ElementExpanderButton = ElementExpanderButton;

})(OCA, Backbone);
