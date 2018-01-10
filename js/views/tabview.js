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

		initialize: function() {
			// The tabIds in priority and then insertion order.
			this._tabIds = [];
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

			this._tabIds.splice(tabHeaderIndex, 0, tabId);

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
			// _.map creates an array, so "currentPriorities" will contain a
			// "length" property.
			var currentPriorities = _.map(this._tabIds, _.bind(function(tabId) {
				return this.getRegion(tabId).currentView.getOption('priority');
			}, this));

			var index = _.findIndex(currentPriorities, function(currentPriority) {
				return priority > currentPriority;
			});

			if (index === -1) {
				return currentPriorities.length;
			}

			return index;
		},

		/**
		 * Removes the tab header for the given tabId.
		 *
		 * If the tab header to be removed is the one currently selected and
		 * there are other tab headers the next one (in priority and then
		 * insertion order) is automatically selected; if the tab header to be
		 * removed is the last one, then the previous one is selected instead.
		 *
		 * @param string tabId the ID of the tab.
		 */
		removeTabHeader: function(tabId) {
			var tabIdIndex = _.indexOf(this._tabIds, tabId);

			// If the tab header to be removed is the one currently selected
			// then select the next tab header or, if it is the last tab header,
			// the previous one (or none if there are no other tab headers).
			if (this._currentTabId === tabId) {
				if (this._tabIds.length <= 1) {
					delete this._currentTabId;
				} else if (tabIdIndex === (this._tabIds.length - 1)) {
					this.selectTabHeader(this._tabIds[tabIdIndex - 1]);
				} else {
					this.selectTabHeader(this._tabIds[tabIdIndex + 1]);
				}
			}

			this._tabIds.splice(tabIdIndex, 1);

			var removedRegion = this.removeRegion(tabId);
			// Remove the dummy target element that was replaced by the view
			// when it was shown and that is restored back when the region is
			// removed.
			removedRegion.el.remove();
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
		 * destroy it when the TabView is destroyed, except if the content view
		 * is removed first.
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
		 * Removes the tab for the given tabId.
		 *
		 * If the tab to be removed is the one currently selected and there are
		 * other tabs the next one (in priority and then insertion order) is
		 * automatically selected; if the tab to be removed is the last one,
		 * then the previous one is selected instead. If there are no other tabs
		 * then this TabView is simply emptied.
		 *
		 * In any case the content view given when the tab was added is
		 * returned; this TabView will no longer have ownership of the content
		 * view, and thus the content view must be explicitly destroyed when no
		 * longer needed.
		 *
		 * @param string tabId the ID of the tab to remove.
		 * @return Marionette.View the content view of the removed tab.
		 */
		removeTab: function(tabId) {
			if (!this._tabContentViews.hasOwnProperty(tabId)) {
				return undefined;
			}

			var removedTabContentView = this._tabContentViews[tabId];

			this._tabHeadersView.removeTabHeader(tabId);

			delete this._tabContentViews[tabId];

			// Removing the tab header selects a new tab header, which in turn
			// changes the content view, except when there are no other tabs. In
			// that case the content view would be being shown in the region and
			// thus would have to be removed from there.
			if (Object.keys(this._tabContentViews).length === 0) {
				this.getRegion('tabContent').empty({preventDestroy: true});
			}

			return removedTabContentView;
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
			if (this._selectedTabExtraClass) {
				this.getRegion('tabContent').$el.removeClass(this._selectedTabExtraClass);
			}

			// With Marionette 3.1 "this.detachChildView('tabContent')" would be
			// used instead of the "preventDestroy" option.
			this.showChildView('tabContent', this._tabContentViews[tabId], { preventDestroy: true } );

			this._selectedTabExtraClass = 'tab-' + tabId;
			this.getRegion('tabContent').$el.addClass(this._selectedTabExtraClass);
		}

	});

	OCA.SpreedMe.Views.TabView = TabView;

})(OCA, Marionette, Handlebars);
