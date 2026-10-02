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
    watchDocumentNavigations,
} from './support';

// Saving from the dialog. The save must be an XHR/fetch POST, never a document navigation, and the
// field must come back with the new record selected.

test.describe('Add from the dialog', () => {
    for (const how of ['click', 'Enter'] as const) {
        test(`Add (${how}) saves via AJAX and selects the new option`, async ({ page }) => {
            await openNewOwner(page);
            const urlBefore = page.url();
            const dialog = await showDialog(page, FIELD.tag);
            const title = uniqueTitle(`Tag ${how}`);
            const input = dialog.getByLabel('Title');
            await input.fill(title);

            const navigations = watchDocumentNavigations(page);
            const posted = waitForAddNewPost(page, FIELD.tag);
            if (how === 'click') {
                await dialog.getByRole('button', { name: 'Add' }).click();
            } else {
                await input.press('Enter');
            }
            const request = await posted;

            expect(['xhr', 'fetch'], 'the save is an AJAX request').toContain(request.resourceType());
            expect((await request.response())?.status()).toBe(200);

            await expect(openDialog(page)).toHaveCount(0);
            await expect(select(page, FIELD.tag).locator('option:checked')).toHaveText(title);

            // No full-page submit: no document request, and the CMS is still on the same URL.
            expect(navigations(), 'document navigations after Add').toEqual([]);
            expect(page.url()).toBe(urlBefore);
        });
    }

    test('a listbox keeps its earlier selections when a new item is added', async ({ page }) => {
        await openNewOwner(page);
        const first = uniqueTitle('List A');
        const second = uniqueTitle('List B');

        for (const title of [first, second]) {
            const dialog = await showDialog(page, FIELD.tags);
            await dialog.getByLabel('Title').fill(title);
            const posted = waitForAddNewPost(page, FIELD.tags);
            await dialog.getByRole('button', { name: 'Add' }).click();
            const request = await posted;
            expect(['xhr', 'fetch']).toContain(request.resourceType());

            if (title === second) {
                // The earlier selection travels with the request (quickaddnew.js "existing").
                expect(request.postData() ?? '').toContain('name="existing"');
            }
            await expect(openDialog(page)).toHaveCount(0);
        }

        const selected = await select(page, FIELD.tags).evaluate((el: HTMLSelectElement) =>
            Array.from(el.selectedOptions).map((o) => o.text),
        );
        expect(selected).toEqual(expect.arrayContaining([first, second]));
        expect(selected).toHaveLength(2);
    });
});

test.describe('Validation', () => {
    test('a required field blocks the submit in the browser', async ({ page }) => {
        // getAddNewValidator() on the fixture makes Title required; Silverstripe renders that as
        // the HTML required attribute, so an empty submit never reaches the server.
        await openNewOwner(page);
        const dialog = await showDialog(page, FIELD.tag);
        const input = dialog.getByLabel('Title');
        await expect(input).toHaveAttribute('required', /.*/);

        let posted = false;
        page.on('request', (r) => {
            if (r.method() === 'POST' && /\/AddNewForm(\?|$)/.test(r.url())) posted = true;
        });
        await dialog.getByRole('button', { name: 'Add' }).click();

        expect(await input.evaluate((el: HTMLInputElement) => el.validity.valueMissing)).toBe(true);
        await page.waitForTimeout(500);
        expect(posted, 'no request for an empty required field').toBe(false);
        await expect(openDialog(page)).toHaveCount(1);
    });

    test('a server-side validation error is shown in the dialog', async ({ page }) => {
        // The fixture rejects titles starting with REJECT in onBeforeWrite(); doAddNew() catches
        // that and re-renders the form with the message, which the dialog must show.
        await openNewOwner(page);
        const dialog = await showDialog(page, FIELD.tag);
        const title = uniqueTitle('REJECT');
        await dialog.getByLabel('Title').fill(title);

        const navigations = watchDocumentNavigations(page);
        const posted = waitForAddNewPost(page, FIELD.tag);
        await dialog.getByRole('button', { name: 'Add' }).click();
        const request = await posted;
        expect(['xhr', 'fetch']).toContain(request.resourceType());

        await expect(dialog.locator('#Form_AddNewForm_error')).toContainText('titles starting with REJECT are not allowed');
        await expect(openDialog(page)).toHaveCount(1);
        await expect(select(page, FIELD.tag).locator('option', { hasText: title })).toHaveCount(0);
        expect(navigations()).toEqual([]);
    });
});
