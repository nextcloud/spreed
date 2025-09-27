import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'

async function login(page: Page, { login, password, location }: {
	login: string
	password: string
	location: string
}) {
	await page.goto('apps/spreed/')
	await page.getByRole('textbox', { name: 'Account name or email' }).fill('admin')
	await page.getByRole('textbox', { name: 'Password' }).fill('admin')
	await page.getByRole('button', { name: 'Log in', exact: true }).click()
	await page.waitForURL('apps/spreed/')
}

test('can open app', async ({ page }) => {
	await login(page, { login: 'admin', password: 'admin', location: 'apps/spreed/' })
	// Expect a title "to contain" a substring.
	await expect(page).toHaveTitle(/Talk - Nextcloud/)
})

test('can open a conversation', async ({ page }) => {
	await login(page, { login: 'admin', password: 'admin', location: 'apps/spreed/' })
	await page.getByRole('textbox', { name: 'Search …' }).fill('test')
	await page.locator('a[href="/index.php/call/biiwputz"]').click()
	await page.waitForURL('call/biiwputz')

	const selector = '.rich-contenteditable__input'
	await expect(page.locator(selector)).toBeVisible()
	await page.waitForFunction((selector) => document.querySelector(selector)!.getAttribute('contenteditable') === 'true', selector)
	// Expects page to have a heading with the name of Installation.
	// await expect(page.getByRole('textbox', { name: 'Write a message' })).toBeVisible()
	await page.locator(selector).fill('wololo')

	await page.locator('button[aria-label="Send message"]').click()
})
