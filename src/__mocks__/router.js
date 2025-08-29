/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createTalkRouter } from '../router/router.ts'

const router = createTalkRouter()
router.addRoute({ path: '/', name: 'none', redirect: '/apps/spreed', component: { template: '<div />' } })

export default router
