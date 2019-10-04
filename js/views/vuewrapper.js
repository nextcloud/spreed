/* global OCA, Marionette */

/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

(function(OCA, Marionette) {

	'use strict';

	OCA.Talk = OCA.Talk || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

	/**
	 * Helper class to wrap a Vue instance as a Marionette view.
	 */
	var VueWrapper = Marionette.View.extend({

		template: function() {
			return '';
		},

		/**
		 * @param {Vue} options.vm the Vue instance to wrap.
		 */
		initialize: function(options) {
			this._vm = options.vm;
		},

		onRender: function() {
			this._vm.$mount(this.el);

			this.el = this._vm.$el;
		},

		onBeforeDestroy: function() {
			this._vm.$destroy();
		},

	});

	OCA.Talk.Views.VueWrapper = VueWrapper;

})(OCA, Marionette);
