/* global Marionette */

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

(function(OCA, Marionette) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.Talk = OCA.Talk || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

	/**
	 * View for the right sidebar.
	 *
	 * The right sidebar is an area that can be shown or hidden from the right
	 * border of the document. It contains a view intended to provide details of
	 * the current call at the top and a TabView to which different sections can
	 * be added and removed as needed. The call details view can be set through
	 * "setCallInfoView()" while new tabs can be added through "addTab()" and
	 * removed through "removeTab()".
	 *
	 * Tabs can be selected programatically using "selectTab()".
	 *
	 * No matter if it is done programatically or by the user, selecting a tab
	 * triggers the "select:tab" event with the ID of the tab as parameter;
	 * selecting a new tab deselects the current tab, so before "select:tab" is
	 * triggered "unselect:tab" is triggered with the ID of the previous tab.
	 *
	 * The SidebarView can be opened or closed programatically using "open()"
	 * and "close()".
	 *
	 * No matter if it is done programatically or by the user, opening the
	 * sidebar triggers the "open" and "opened" events, and closing
	 * the sidebar triggers the "close" and "closed" events; in both cases the
	 * first event is triggered when the animation starts and the second one
	 * when the animation ends.
	 *
	 * In order for the user to be able to open the sidebar when it is closed,
	 * the SidebarView shows a small icon ("#app-sidebar-trigger") on the right
	 * border of the document that opens the sidebar when clicked.
	 *
	 * By default the sidebar is disabled, that is, it is closed and can not be
	 * opened, neither by the user nor programatically. Calling "enable()" will
	 * make possible for the sidebar to be opened, and calling "disable()" will
	 * prevent it again (also closing it if it was open).
	 */
	var SidebarView = Marionette.View.extend({

		id: 'app-sidebar-wrapper',

		ui: {
			trigger: '#app-sidebar-trigger',
			sidebar: '#app-sidebar',
		},

		regions: {
			callInfoView: '@ui.sidebar .detailCallInfoContainer',
			tabView: '@ui.sidebar .tabs'
		},

		events: {
			'click @ui.trigger': 'toggle',
			'click @ui.sidebar a.close': 'close',
		},

		childViewTriggers: {
			'unselect:tab': 'unselect:tab',
			'select:tab': 'select:tab',
		},

		template: function(context) {
			// OCA.Talk.Views.Templates may not have been initialized when this
			// view is initialized, so the template can not be directly
			// assigned.
			return OCA.Talk.Views.Templates['sidebarview'](context);
		},

		templateContext: {
			closeLabel: t('spreed', 'Close')
		},

		initialize: function() {
			this._enabled = false;
			this._open = false;

			this._callInfoView = null;

			this._tabView = new OCA.SpreedMe.Views.TabView();

			// In Marionette 3.0 the view is not rendered automatically if
			// needed when showing a child view, so it must be rendered
			// explicitly to ensure that the DOM element in which the child view
			// will be appended exists.
			this.render();
			this.showChildView('tabView', this._tabView, { replaceElement: true } );

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

		toggle: function() {
			if (!this._open) {
				this.open();
			} else {
				this.close();
			}
		},

		open: function() {
			if (!this._enabled) {
				return;
			}

			this.trigger('open');

			this.getUI('sidebar').removeClass('disappear')
					.show('slide', { direction: 'right' }, 300, function() {
							this.trigger('opened');
					}.bind(this));

			this._open = true;
		},

		close: function() {
			this.trigger('close');

			this.getUI('sidebar')
					.hide('slide', { direction: 'right' }, 300, function() {
							this.getUI('sidebar').addClass('disappear');

							this.trigger('closed');
					}.bind(this));

			this._open = false;
		},

		/**
		 * Sets a new call info view.
		 *
		 * Once set, the SidebarView takes ownership of the view, and it will
		 * destroy it if a new one is set.
		 *
		 * @param {Marionette.View} callInfoView the view to set.
		 */
		setCallInfoView: function(callInfoView) {
			this._callInfoView = callInfoView;

			this.showChildView('callInfoView', this._callInfoView);
		},

		/**
		 * Adds a new tab.
		 *
		 * The tabHeaderOptions must provide a 'label' string which will be
		 * rendered as the tab header. Optionally, it can provide a 'priority'
		 * integer to set the order of the tab header with respect to the other
		 * tab headers (tabs with higher priorities appear before tabs with
		 * lower priorities; tabs with the same priority are sorted based on
		 * their insertion order); if it is not explicitly set the value 0 is
		 * used. If needed, the tabHeaderOptions can provide other values that
		 * will override the default TabHeaderView properties (for example, it
		 * can provide an 'onRender' function to extend the default rendering of
		 * the header).
		 *
		 * The SidebarView takes ownership of the given content view, and it
		 * will destroy it when the SidebarView is destroyed, except if the
		 * content view is removed first.
		 *
		 * @param {string} tabId the ID of the tab.
		 * @param {Object} tabHeaderOptions the options for the constructor of the
		 *        TabHeaderView that will be added as the header of the tab.
		 * @param {Marionette.View} tabContentView the View to be shown when the
		 *        tab is selected.
		 */
		addTab: function(tabId, tabHeaderOptions, tabContentView) {
			this._tabView.addTab(tabId, tabHeaderOptions, tabContentView);
		},

		/**
		 * Select the tab associated to the given tabId.
		 *
		 * @param {string} tabId the ID of the tab to select.
		 */
		selectTab: function(tabId) {
			this._tabView.selectTab(tabId);
		},

		/**
		 * Returns the ID of the currently selected tab.
		 *
		 * @return {string} the ID of the currently selected tab.
		 */
		getCurrentTabId: function() {
			return this._tabView.getCurrentTabId();
		},

		/**
		 * Removes the tab for the given tabId.
		 *
		 * If the tab to be removed is the one currently selected and there are
		 * other tabs the next one (in priority and then insertion order) is
		 * automatically selected; if the tab to be removed is the last one,
		 * then the previous one is selected instead. If there are no other tabs
		 * then the TabView is simply emptied.
		 *
		 * In any case the content view given when the tab was added is
		 * returned; this SidebarView will no longer have ownership of the
		 * content view, and thus the content view must be explicitly destroyed
		 * when no longer needed.
		 *
		 * @param {string} tabId the ID of the tab to remove.
		 * @return {Marionette.View} the content view of the removed tab.
		 */
		removeTab: function(tabId) {
			return this._tabView.removeTab(tabId);
		}

	});

	OCA.SpreedMe.Views.SidebarView = SidebarView;

})(OCA, Marionette);
