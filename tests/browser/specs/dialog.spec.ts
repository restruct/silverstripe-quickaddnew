import {
    test,
    expect,
    FIELD,
    addNewButton,
    closeButtonGeometry,
    openDialog,
    openNewOwner,
    paintedOffset,
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

        // Centred: the painted X (measured from a screenshot of the button) sits on its centre.
        const ink = await paintedOffset(page, dialog.locator('.ui-dialog-titlebar-close'));
        expect(ink.ink, 'the close button paints something').toBe(true);
        // ...and the ink is a glyph, not the button's own border or fill (which would make the
        // centring check below pass by definition).
        expect(ink.inkWidth).toBeLessThan(ink.width - 4);
        expect(ink.inkHeight).toBeLessThan(ink.height - 4);
        expect(Math.abs(ink.dx), `X is ${ink.dx}px off-centre horizontally`).toBeLessThanOrEqual(2);
        expect(Math.abs(ink.dy), `X is ${ink.dy}px off-centre vertically`).toBeLessThanOrEqual(2);

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
