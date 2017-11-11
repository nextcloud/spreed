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

	var TEMPLATE_TAB_HEADER_VIEW =
		'<a href="#">{{label}}</a>';

	var TEMPLATE_TAB_VIEW =
		'<div class="tabHeaders">' +
		'</div>' +
		'<div class="tabsContainer">' +
		'	<div class="tab">' +
		'	</div>' +
		'</div>';

	var TabHeaderView  = Marionette.View.extend({

		tagName: 'li',
		className: 'tabHeader',

		template: Handlebars.compile(TEMPLATE_TAB_HEADER_VIEW),
		templateContext: function() {
			return {
				label: this.getOption('label')
			};
		},

		events: {
			'click': function() {
				this.triggerMethod('click:tabHeader', this.getOption('tabId'));
			}
		},

		setSelected: function(selected) {
			if (selected) {
				this.$el.addClass('selected');
			} else {
				this.$el.removeClass('selected');
			}
		}

	});

	var TabHeadersView  = Marionette.View.extend({

		tagName: 'ul',
		className: 'tabHeaders',

		// The tab headers are added dynamically using regions, so there is
		// nothing to be rendered with a template.
		template: _.noop,

		childViewEvents: {
			'click:tabHeader': 'selectTabHeader'
		},

		addTabHeader: function(tabId, tabHeaderOptions) {
			var tabHeaderId = 'tabHeader' + tabId.charAt(0).toUpperCase() + tabId.substr(1);

			tabHeaderOptions.id = tabHeaderId;
			// The "tabId" will be passed by the TabHeaderView when triggering
			// "click:tabHeader" events.
			tabHeaderOptions.tabId = tabId;

			tabHeaderOptions.priority = tabHeaderOptions.priority || 0;

			var tabHeaderView = new TabHeaderView(tabHeaderOptions);

			var tabHeaderIndex = this._getIndexForTabHeaderPriority(tabHeaderOptions.priority);

			// When adding a region and showing a view on it the target element
			// of the region must exist in the parent view. Therefore, a dummy
			// target element, which will be replaced with the tab header
			// itself, has to be added to the parent view.
			var dummyElement = '<div id="' + tabHeaderId + '"/>';
			if (tabHeaderIndex === 0) {
				this.$el.prepend(dummyElement);
			} else {
				// When two tab headers have the same priority the new one is
				// added after the existing one.
				this.$el.children().eq(tabHeaderIndex-1).after(dummyElement);
			}

			this.addRegion(tabId, { el: '#' + tabHeaderId, replaceElement: true });
			this.showChildView(tabId, tabHeaderView);
		},

		/**
		 * Return the insertion index for a tab header based on its priority.
		 *
		 * Tab headers with higher priorities go before tab headers with lower
		 * priorities; if the priority is the same as one or more of the current
		 * tab headers the new tab header goes after the last of them.
		 *
		 * @param int priority the priority to get its insertion index.
		 * @return int the insertion index.
		 */
		_getIndexForTabHeaderPriority: function(priority) {
			// this.getRegions() returns an object that acts as a map, but it
			// has no "length" property; _.map creates an array, thus ensuring
			// that there is a "length" property to know the current number of
			// tab headers.
			var currentPriorities = _.map(this.getRegions(), function(region) {
				return region.currentView.getOption('priority');
			});

			// By default sort() converts the values to strings and sorts them
			// in ascending order using their Unicode value; a custom function
			// must be used to sort them by their numerical value instead.
			currentPriorities.sort(function(a, b) {
				return a - b;
			}).reverse();

			var index = _.findIndex(currentPriorities, function(currentPriority) {
				return priority > currentPriority;
			});

			if (index === -1) {
				return currentPriorities.length;
			}

			return index;
		},

		selectTabHeader: function(tabId) {
			if (this._currentTabId !== undefined) {
				this.getChildView(this._currentTabId).setSelected(false);
			}

			this._currentTabId = tabId;

			this.getChildView(this._currentTabId).setSelected(true);

			this.triggerMethod('select:tabHeader', tabId);
		}

	});

	/**
	 * View for tabs (headers and content).
	 *
	 * A TabView contains a set of tab headers and a content area. When a header
	 * is selected its associated content view is shown in the content area;
	 * otherwise its content is hidden (although the header is always shown).
	 */
	var TabView = Marionette.View.extend({

		tagName: 'div',
		className: 'tabs',

		regions: {
			tabHeaders: '.tabHeaders',
			tabContent: '.tab'
		},

		template: Handlebars.compile(TEMPLATE_TAB_VIEW),

		initialize: function() {
			this._tabHeadersView = null;
			this._tabContentViews = {};
		},

		onDestroy: function() {
			_.each(this._tabContentViews, function(tabContentView) {
				// Explicitly destroy all the tab content views, as some of them
				// may be detached from the TabView.
				tabContentView.destroy();
			});
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
		 * The TabView takes ownership of the given content view, and it will
		 * destroy it when the TabView is destroyed.
		 *
		 * @param string tabId the ID of the tab.
		 * @param Object tabHeaderOptions the options for the constructor of the
		 *        TabHeaderView that will be added as the header of the tab.
		 * @param Marionette.View tabContentView the View to be shown when the
		 *        tab is selected.
		 */
		addTab: function(tabId, tabHeaderOptions, tabContentView) {
			if (this._tabHeadersView === null) {
				this._tabHeadersView = new TabHeadersView();
				this.showChildView('tabHeaders', this._tabHeadersView, { replaceElement: true });
			}

			this._tabHeadersView.addTabHeader(tabId, tabHeaderOptions);

			this._tabContentViews[tabId] = tabContentView;

			if (Object.keys(this._tabContentViews).length === 1) {
				this.selectTab(tabId);
			}
		},

		/**
		 * Select the tab associated to the given tabId.
		 *
		 * @param string tabId the ID of the tab to select.
		 */
		selectTab: function(tabId) {
			if (!this._tabContentViews.hasOwnProperty(tabId)) {
				return;
			}

			this._tabHeadersView.selectTabHeader(tabId);
		},

		/**
		 * Shows the content view associated to the selected tab header.
		 *
		 * Only for internal use as an event handler.
		 *
		 * @param string tabId the ID of the selected tab.
		 */
		onChildviewSelectTabHeader: function(tabId) {
			// With Marionette 3.1 "this.detachChildView('tabContent')" would be
			// used instead of the "preventDestroy" option.
			this.showChildView('tabContent', this._tabContentViews[tabId], { preventDestroy: true } );
		}

	});

	OCA.SpreedMe.Views.TabView = TabView;

})(OCA, Marionette, Handlebars);
