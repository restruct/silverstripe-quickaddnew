import { test as base, expect, type Locator, type Page, type Request } from '@playwright/test';

// Shared fixtures and helpers for the quickaddnew specs.
//
// The CMS screen is the fixture ModelAdmin in tests/browser/fixtures/ (copied into the scratch
// host by the runner): a new "Browser Owner" record with three quickaddnew fields.

/** The edit form of a new Browser Owner record (a full page load, not a pjax navigation). */
export const NEW_OWNER_URL = '/admin/qan-browser/owners/EditForm/field/owners/item/new';

/** Field names on that form. */
export const FIELD = {
    tag: 'TagID', // DropdownField, default dialog title
    workArea: 'OtherTagID', // DropdownField, title overridden with setAddNewDialogTitle()
    tags: 'Tags', // ListboxField (many_many)
} as const;

/**
 * test, extended with an automatic console guard: every spec fails if the page logs a console
 * error or throws an uncaught exception at any point, page load included. "Failed to load
 * resource" (any 4xx/5xx asset or request) arrives as a console error too, so a missing module
 * asset is caught here as well.
 */
export const test = base.extend<{ consoleGuard: void }>({
    consoleGuard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => errors.push(`uncaught: ${err.message}`));

            await use();

            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors or uncaught exceptions').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/** A title no earlier run can have used (the host database is reused between runs). */
export function uniqueTitle(prefix: string): string {
    return `${prefix} ${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
}

/** Open the new-record form with a full page load and wait until quickaddnew has bound. */
export async function openNewOwner(page: Page): Promise<void> {
    await page.goto(NEW_OWNER_URL);
    await expect(page.locator(`#Form_ItemEditForm_${FIELD.tag}_Holder .quickaddnew-button`)).toBeVisible();
}

/** The Add New button of a field. */
export function addNewButton(page: Page, field: string): Locator {
    return page.locator(`#Form_ItemEditForm_${field}_Holder .quickaddnew-button`);
}

/**
 * The open quickaddnew dialog. Each field has its own jQuery UI dialog wrapper (hidden until
 * opened), so "the" dialog is the one that is visible.
 */
export function openDialog(page: Page): Locator {
    return page.locator('.quickaddnew-ui-dialog').filter({ visible: true });
}

/** Click a field's Add New button and wait until the dialog's form has loaded. */
export async function showDialog(page: Page, field: string): Promise<Locator> {
    await addNewButton(page, field).click();
    const dialog = openDialog(page);
    await expect(dialog.locator('form#Form_AddNewForm')).toBeVisible();
    return dialog;
}

/** The underlying <select> of a field (the visible widget may be a chosen.js replacement). */
export function select(page: Page, field: string): Locator {
    return page.locator(`#Form_ItemEditForm_${field}`);
}

/**
 * Wait for the dialog's submit request. Matches the AddNewForm POST only, not the AddNewFormHTML
 * GET that loads the dialog.
 */
export function waitForAddNewPost(page: Page, field: string): Promise<Request> {
    const url = new RegExp(`/field/${field.replace(/[[\]]/g, '')}/AddNewForm(\\?|$)`);
    return page.waitForRequest((r) => r.method() === 'POST' && url.test(r.url()));
}

/**
 * Record every DOCUMENT request of the main frame from now on. A full-page form submit is a
 * document navigation; the AJAX save must cause none (the SS5 regression: jquery.form's
 * ajaxSubmit missing on window.jQuery made the dialog post natively and replace the CMS with the
 * bare field HTML). Returns a getter for the URLs seen.
 */
export function watchDocumentNavigations(page: Page): () => string[] {
    const seen: string[] = [];
    page.on('request', (r) => {
        if (r.isNavigationRequest() && r.frame() === page.mainFrame()) {
            seen.push(`${r.method()} ${r.url()}`);
        }
    });
    return () => [...seen];
}

/**
 * Everything that draws a glyph inside the dialog's close button: icon-font pseudo-element
 * content, or a background image (jQuery UI sprite, Bootstrap's .btn-close SVG), on the button or
 * any visible descendant. The double-X regression was the button carrying font-icon-cancel
 * itself while the admin also renders <span class="font-icon-cancel"> inside it.
 */
export async function closeButtonGeometry(dialog: Locator) {
    return dialog.locator('.ui-dialog-titlebar-close').evaluate((btn) => {
        const isVisible = (el: Element) => {
            const r = el.getBoundingClientRect();
            const cs = getComputedStyle(el);
            return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none' && cs.opacity !== '0';
        };
        const glyphs: string[] = [];
        for (const el of [btn, ...Array.from(btn.querySelectorAll('*'))]) {
            if (!isVisible(el)) continue;
            const label = `${el.tagName.toLowerCase()}.${String(el.className).trim().split(/\s+/).join('.')}`;
            if (getComputedStyle(el).backgroundImage !== 'none') glyphs.push(`${label} background-image`);
            for (const pseudo of ['::before', '::after']) {
                const cs = getComputedStyle(el, pseudo);
                const hasContent = !['none', 'normal', '""', "''"].includes(cs.content);
                if (cs.display !== 'none' && (hasContent || cs.backgroundImage !== 'none')) {
                    glyphs.push(`${label}${pseudo} ${cs.content}`);
                }
            }
        }

        const box = (el: Element) => {
            const r = el.getBoundingClientRect();
            return { left: r.left, top: r.top, right: r.right, bottom: r.bottom, cx: r.left + r.width / 2, cy: r.top + r.height / 2 };
        };
        const icon = btn.querySelector('.font-icon-cancel, .ui-icon') ?? btn;
        return {
            glyphs,
            button: box(btn),
            icon: box(icon),
            dialog: box(btn.closest('.ui-dialog')!),
            titlebar: box(btn.closest('.ui-dialog-titlebar')!),
        };
    });
}
