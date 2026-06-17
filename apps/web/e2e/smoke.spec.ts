import { expect, test } from '@playwright/test'
import { installApi } from './fixtures'

test('create a league, play the season, then read odds and the benchmark', async ({ page }) => {
  await installApi(page)

  await page.goto('/')
  await expect(page).toHaveURL(/\/leagues\/create$/)

  await page.getByRole('button', { name: 'Create league' }).click()
  await expect(page).toHaveURL(/\/leagues\/L1\/simulation$/)

  await expect(page.getByRole('heading', { name: 'Standings' })).toBeVisible()
  await expect(page.getByText('Available after week 4.')).toBeVisible()

  await page.getByRole('button', { name: 'Play all' }).click()

  await expect(page.getByText('Week 6 of 6', { exact: false })).toBeVisible()
  await expect(page.getByText('Available after week 4.')).toHaveCount(0)
  await expect(page.getByText('settled-or-simulated')).toBeVisible()

  await expect(page.getByRole('row', { name: /Alpha/ })).toContainText('18')

  await page.getByRole('button', { name: 'Run' }).click()
  await expect(page.getByText('monte-carlo')).toBeVisible()
})
