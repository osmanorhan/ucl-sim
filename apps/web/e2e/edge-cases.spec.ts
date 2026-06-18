import { expect, test } from '@playwright/test'
import { driftedSnapshot, installApi } from './fixtures'

test('a server error surfaces as a toast without crashing the view', async ({ page }) => {
  await installApi(page)
  await page.route('**/api/leagues/*/play-all', (route) =>
    route.fulfill({
      status: 500,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'season engine offline' }),
    }))

  await page.goto('/')
  await page.getByRole('button', { name: 'Create league' }).click()
  await page.getByRole('button', { name: 'Play all' }).click()

  await expect(page.getByRole('alert')).toContainText('season engine offline')
  await expect(page.getByRole('heading', { name: 'Standings' })).toBeVisible()
})

test('a contract-breaking response fails at the boundary, not deep in a component', async ({ page }) => {
  await installApi(page)

  await page.goto('/')
  await page.getByRole('button', { name: 'Create league' }).click()
  await expect(page.getByRole('heading', { name: 'Standings' })).toBeVisible()

  await page.route('**/api/leagues/*/play-week', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(driftedSnapshot()),
    }))

  await page.getByRole('button', { name: 'Play week' }).click()

  await expect(page.getByRole('alert')).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Standings' })).toBeVisible()
})

test('the create form presents a fixed four-team group, not an editable squad list', async ({ page }) => {
  await installApi(page)

  await page.goto('/')

  await expect(page.locator('.team-row')).toHaveCount(4)
  await expect(page.getByRole('button', { name: /Remove|Add team/ })).toHaveCount(0)

  await page.getByRole('button', { name: 'Create league' }).click()

  await expect(page).toHaveURL(/\/leagues\/L1\/simulation$/)
  await expect(page.getByRole('row', { name: /Alpha/ })).toBeVisible()
})
