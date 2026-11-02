import { test, expect, type Locator, type Page } from '@playwright/test';
import * as path from 'path';

// Generates the README screenshots in docs/images/. Not a test suite: run it with
// playwright.screenshots.config.ts (see README.md next to this file). The content comes from the
// showcase fixture, tests/browser/fixtures/QanShowcase*.php: an "Events" CMS section whose
// "Spring open day" event has a Venue dropdown, a Speakers listbox and a Topics checkbox set,
// each with quickaddnew enabled.

const IMAGES = path.join(__dirname, '..', '..', '..', 'docs', 'images');

/**
 * Hide what only the test host has: the browser-test fixture's own menu entry
 * ("QuickAddNew browser test"). Presentation only; nothing about the module's UI is changed.
 */
async function hideFixtureChrome(page: Page): Promise<void> {
    await page.addStyleTag({ content: '.cms-menu li:has(> a[href*="admin/qan-browser"]) { display: none !important; }' });
}

/** Open the showcase event's edit form and wait until quickaddnew has added its buttons. */
async function openShowcaseEvent(page: Page): Promise<Locator> {
    await page.goto('/admin/events');
    await page.locator('.ss-gridfield-item').filter({ hasText: 'Spring open day' }).first().click();
    const form = page.locator('#Form_ItemEditForm');
    await expect(form.locator('.quickaddnew-button')).toHaveCount(3);
    await hideFixtureChrome(page);
    return form;
}

/** No hover or focus styling left over from the clicks that got here. */
async function settle(page: Page): Promise<void> {
    await page.mouse.move(1270, 790);
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await page.waitForTimeout(500);
}

/** Bottom edge (in page pixels) of a field holder, for a clip that ends just below it. */
async function bottomOf(locator: Locator): Promise<number> {
    const box = (await locator.boundingBox())!;
    return Math.ceil(box.y + box.height);
}

test('Edit form: an Add New button next to each relation field', async ({ page }) => {
    const form = await openShowcaseEvent(page);
    await settle(page);

    const bottom = await bottomOf(form.locator('#Form_ItemEditForm_Topics_Holder'));
    await page.screenshot({
        path: path.join(IMAGES, 'edit-form.png'),
        clip: { x: 0, y: 0, width: 1280, height: Math.min(800, bottom + 24) },
    });
});

test('Dialog: adding a venue without leaving the form', async ({ page }) => {
    const form = await openShowcaseEvent(page);
    await form.locator('#Form_ItemEditForm_VenueID_Holder .quickaddnew-button').click();

    const dialog = page.locator('.quickaddnew-ui-dialog').filter({ visible: true });
    const addForm = dialog.locator('form#Form_AddNewForm');
    await expect(addForm).toBeVisible();
    await expect(dialog.locator('.ui-dialog-title')).toHaveText('Add new Venue');
    await addForm.locator('input[name="Title"]').fill('Riverside theatre');
    await addForm.locator('input[name="City"]').fill('Amsterdam');
    await settle(page);

    // The whole window: the dialog over the form shows where it opens (a modal on the edit form).
    await page.screenshot({ path: path.join(IMAGES, 'add-new-dialog.png') });

    // Close it unsubmitted: the Venue dropdown is not where the after-add shot is taken (below).
    await dialog.locator('.ui-dialog-titlebar-close').click();
    await expect(dialog).toHaveCount(0);
});

test('After Add: the new speaker is selected, earlier selections kept', async ({ page }) => {
    // The Speakers listbox (many_many) rather than the Venue dropdown: a dropdown can come back
    // with the wrong option selected when the new record does not sort last in its source
    // (filed as a module bug), and a shot must not depend on that. A multi-select is not affected.
    const form = await openShowcaseEvent(page);
    const holder = form.locator('#Form_ItemEditForm_Speakers_Holder');
    await holder.locator('.quickaddnew-button').click();

    const dialog = page.locator('.quickaddnew-ui-dialog').filter({ visible: true });
    const addForm = dialog.locator('form#Form_AddNewForm');
    await expect(addForm).toBeVisible();
    await expect(dialog.locator('.ui-dialog-title')).toHaveText('Add new Speaker');
    await addForm.locator('input[name="Title"]').fill('Eva Jansen');
    await addForm.locator('[name="action_doAddNew"]').click();
    await expect(dialog).toHaveCount(0);

    // The field is refreshed in place: the two seeded speakers plus the new one.
    const selected = () =>
        form
            .locator('#Form_ItemEditForm_Speakers')
            .evaluate((s: HTMLSelectElement) => Array.from(s.selectedOptions).map((o) => o.text.trim()));
    await expect.poll(selected).toEqual(['Anna de Vries', 'Chen Li', 'Eva Jansen']);
    await settle(page);

    const bottom = await bottomOf(holder);
    await page.screenshot({
        path: path.join(IMAGES, 'after-add.png'),
        // Just below the field: a few pixels more and the next menu entry is cut in half.
        clip: { x: 0, y: 0, width: 1280, height: bottom + 4 },
    });
});
