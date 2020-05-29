/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

import { getBuilder } from '@nextcloud/browser-storage'

/**
 * Note: This uses the browsers sessionStorage not the browserStorage.
 * As per https://stackoverflow.com/q/20325763 this is NOT shared between tabs.
 * For us this is the solution we were looking for, as it allows us to
 * identify if a user reloaded a conversation in the same tab,
 * or entered it in another tab.
 */
export default getBuilder('talk').clearOnLogout().build()
