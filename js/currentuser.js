/* global OC, OCA */

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

(function(OC, OCA) {

	'use strict';

	OCA.Talk = OCA.Talk || {};

	/**
	 * Current user as seen by Talk.
	 *
	 * This may differ from the current user returned by OC.getCurrentUser().
	 */
	var currentUser = undefined;

	/**
	 * Returns the current user set in Talk or, if none, the current user as
	 * returned by OC.getCurrentUser().
	 */
	function getCurrentUser() {
		if (currentUser) {
			return currentUser;
		}

		return OC.getCurrentUser();
	}

	/**
	 * Sets the current user returned by getCurrentUser().
	 *
	 * @param string uid
	 * @param string displayName
	 */
	function setCurrentUser(uid, displayName) {
		currentUser = {
			uid: uid,
			displayName: displayName
		};
	}

	OCA.Talk.getCurrentUser = getCurrentUser;
	OCA.Talk.setCurrentUser = setCurrentUser;

})(OC, OCA);
