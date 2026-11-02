import {
    test,
    expect,
    FIELD,
    openDialog,
    openNewOwner,
    select,
    showDialog,
    uniqueTitle,
    waitForAddNewPost,
} from './support';
import type { Page } from '@playwright/test';

// Issue #5: after Add New on a DropdownField the field came back with the LAST option selected
// instead of the new record, so saving the form stored the wrong has_one without any warning.
// add.spec.ts did not catch it: its source is ordered by ID, so the new record is always the last
// option and "last" and "new" are the same. These specs use a source sorted by Title and add a
// record that sorts before an existing one.

/** Add a record through the dialog of a field and wait until the dialog has closed. */
async function addThroughDialog(page: Page, field: string, title: string): Promise<void> {
    const dialog = await showDialog(page, field);
    await dialog.getByLabel('Title').fill(title);
    const posted = waitForAddNewPost(page, field);
    await dialog.getByRole('button', { name: 'Add' }).click();
    const request = await posted;
    expect((await request.response())?.status()).toBe(200);
    await expect(openDialog(page)).toHaveCount(0);
}

test.describe('Dropdown selection after Add New (issue #5)', () => {
    test('the new record is selected when it does not sort last, and saving stores it', async ({ page }) => {
        await openNewOwner(page);
        const field = select(page, FIELD.sortedTag);

        // A record that sorts after the one under test, so the new record is NOT the last option.
        // "Zebra <timestamp>" also sorts after every Zebra an earlier run left in the database.
        const later = uniqueTitle('Zebra');
        await addThroughDialog(page, FIELD.sortedTag, later);

        const title = uniqueTitle('Aardvark');
        await addThroughDialog(page, FIELD.sortedTag, title);

        // The precondition this spec exists for: the new option is not the last one.
        const texts = (await field.locator('option').allTextContents()).map((t) => t.trim());
        expect(texts, 'the new record is in the list').toContain(title);
        expect(texts.indexOf(title), 'the new record does not sort last').toBeLessThan(texts.length - 1);

        // The new record is selected: the checked option AND the select's value.
        await expect(field.locator('option:checked')).toHaveText(title);
        const newValue = await field.locator('option', { hasText: title }).getAttribute('value');
        await expect(field).toHaveValue(newValue!);

        // Save the record and reload its edit form: the stored has_one must be the new record.
        await page.getByRole('button', { name: /^(Create|Save)$/ }).first().click();
        await page.waitForURL(/\/item\/\d+(\/|$|\?)/);
        await page.goto(page.url());
        await expect(select(page, FIELD.sortedTag).locator('option:checked')).toHaveText(title);
    });
});
