import {
    test,
    expect,
    FIELD,
    addNewButton,
    closeButtonGeometry,
    openDialog,
    openNewOwner,
    showDialog,
} from './support';

// The dialog itself: opening, title, close button. Each test name says which manual-check item
// (or earlier field bug) it replaces.

test.describe('Add New dialog', () => {
    test('opens on the FIRST click right after a full page load', async ({ page }) => {
        // Once on SS5 the first click straight after a hard reload did not open the dialog (a
        // second click did). Three fresh loads, one click each, no retry.
        for (let i = 0; i < 3; i++) {
            await openNewOwner(page);
            await addNewButton(page, FIELD.tag).click();
            await expect(openDialog(page).locator('form#Form_AddNewForm')).toBeVisible({ timeout: 5_000 });
        }
    });

    test('default title is "Add new {singular name}"', async ({ page }) => {
        await openNewOwner(page);
        const dialog = await showDialog(page, FIELD.tag);
        await expect(dialog.locator('.ui-dialog-title')).toHaveText('Add new Browser Tag');
    });

    test('setAddNewDialogTitle() overrides the title for that field only', async ({ page }) => {
        await openNewOwner(page);
        const dialog = await showDialog(page, FIELD.workArea);
        await expect(dialog.locator('.ui-dialog-title')).toHaveText('Add a work area');
        await dialog.locator('.ui-dialog-titlebar-close').click();
        await expect(openDialog(page)).toHaveCount(0);

        // The other field on the same class keeps the default.
        const other = await showDialog(page, FIELD.tag);
        await expect(other.locator('.ui-dialog-title')).toHaveText('Add new Browser Tag');
    });

    test('exactly one close X, centred in its button, inside the dialog', async ({ page }) => {
        await openNewOwner(page);
        const dialog = await showDialog(page, FIELD.tag);
        const g = await closeButtonGeometry(dialog);

        // One glyph: the admin's own <span class="font-icon-cancel"> (double X regression, #1).
        expect(g.glyphs, `glyphs drawn in the close button: ${g.glyphs.join(', ')}`).toHaveLength(1);

        // Centred: the icon's box centre sits on the button's centre.
        expect(Math.abs(g.icon.cx - g.button.cx)).toBeLessThanOrEqual(2);
        expect(Math.abs(g.icon.cy - g.button.cy)).toBeLessThanOrEqual(2);

        // Inside the dialog and the titlebar (the pre-3.1 CSS put it at right: -12px, outside).
        expect(g.button.left).toBeGreaterThanOrEqual(g.dialog.left);
        expect(g.button.right).toBeLessThanOrEqual(g.dialog.right);
        expect(g.button.top).toBeGreaterThanOrEqual(g.titlebar.top - 1);
        expect(g.button.bottom).toBeLessThanOrEqual(g.titlebar.bottom + 1);

        // And it works.
        await dialog.locator('.ui-dialog-titlebar-close').click();
        await expect(openDialog(page)).toHaveCount(0);
    });
});
