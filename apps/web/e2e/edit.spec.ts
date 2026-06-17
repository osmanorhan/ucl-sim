import { expect, test } from '@playwright/test'
import { installApi } from './fixtures'

test('correcting a played match records a manual result', async ({ page }) => {
  await installApi(page)

  await page.goto('/')
  await page.getByRole('button', { name: 'Create league' }).click()
  await page.getByRole('button', { name: 'Play all' }).click()
  await expect(page.getByText('settled-or-simulated')).toBeVisible()

  const home = page.locator('#m1-home')
  await expect(home).toHaveValue('2')

  await home.fill('5')
  await home.press('Enter')

  await expect(page.locator('#m1-home')).toHaveValue('5')
  await expect(page.getByLabel('Manually updated result').first()).toBeVisible()
})

test('scheduled fixtures are read-only, not editable', async ({ page }) => {
  await installApi(page)

  await page.goto('/')
  await page.getByRole('button', { name: 'Create league' }).click()
  await page.getByRole('button', { name: 'Play week' }).click()

  await expect(page.getByText('Scheduled').first()).toBeVisible()
  await expect(page.locator('#m1-home')).toHaveValue('2')
  await expect(page.locator('#m3-home')).toHaveCount(0)
})
