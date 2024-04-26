/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
