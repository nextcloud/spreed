/* global Marionette, Handlebars */

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

(function(OCA, Marionette, Handlebars) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var TEMPLATE =
		'<div id="app-sidebar-trigger">' +
		'	<div class="large-outer-left-triangle"/>' +
		'	<div class="large-inner-left-triangle"/>' +
		'</div>' +
		'<div id="app-sidebar" class="detailsView scroll-container">' +
		'	<a class="close icon-close" href="#"><span class="hidden-visually">{{closeLabel}}</span></a>' +
		'</div>';

	/**
	 * View for the right sidebar.
	 *
	 * The right sidebar is an area that can be shown or hidden from the right
	 * border of the document.
	 *
	 * The SidebarView can be shown or hidden programatically using "show()" and
	 * "hide()". It will delegate on "OC.Apps.showAppSidebar()" and
	 * "OC.Apps.hideAppSidebar()", so it must be used along an "#app-content"
	 * that takes into account the "with-app-sidebar" CSS class.
	 *
	 * In order for the user to be able to show the sidebar when it is hidden,
	 * the SidebarView shows a small icon ("#app-sidebar-trigger") on the right
	 * border of the document that shows the sidebar when clicked. When the
	 * sidebar is shown the icon is hidden.
	 *
	 * By default the sidebar is disabled, that is, it is hidden and can not be
	 * shown, neither by the user nor programatically. Calling "enable()" will
	 * make possible for the sidebar to be shown, and calling "disable()" will
	 * prevent it again (also hidden it if it was shown).
	 */
	var SidebarView = Marionette.View.extend({

		id: 'app-sidebar-wrapper',

		ui: {
			trigger: '#app-sidebar-trigger',
			sidebar: '#app-sidebar',
		},

		events: {
			'click @ui.trigger': 'open',
			'click @ui.sidebar a.close': 'close',
		},

		template: Handlebars.compile(TEMPLATE),

		templateContext: {
			closeLabel: t('spreed', 'Close')
		},

		initialize: function() {
			this._enabled = false;

			this.render();

			this.getUI('trigger').hide();
			this.getUI('sidebar').hide();
		},

		enable: function() {
			this._enabled = true;

			this.getUI('trigger').show('slide', { direction: 'right' }, 400);
		},

		disable: function() {
			if (this.getUI('sidebar').css('display') === 'none') {
				this.getUI('trigger').hide('slide', { direction: 'right' }, 200);
			} else {
				// FIXME if the sidebar is being shown or hidden and thus the
				// trigger is only partially visible this would hide it
				// abruptly... But that should not usually happen.
				this.getUI('trigger').hide();
				this.close();
			}

			this._enabled = false;
		},

		open: function() {
			if (!this._enabled) {
				return;
			}

			OC.Apps.showAppSidebar();
		},

		close: function() {
			OC.Apps.hideAppSidebar();
		},

	});

	OCA.SpreedMe.Views.SidebarView = SidebarView;

})(OCA, Marionette, Handlebars);
