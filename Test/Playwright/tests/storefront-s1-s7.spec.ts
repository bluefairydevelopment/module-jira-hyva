import { test, expect, Page } from '@playwright/test';

/**
 * S1–S7 on the HYVÄ theme, against the SPLIT packages.
 *
 * WHY THIS RUN EXISTS. S1–S7 passed on hyvatest before the split — against templates
 * that now live in THIS package. Those results were evidence about markup that has
 * since moved, so they say nothing about either package as it ships today. This is
 * the Hyvä half re-taken; the Luma half needs a Luma-themed rig.
 *
 * THE FIRST ASSERTION IS THE ONE THE SPLIT MADE NECESSARY: the page must be rendering
 * the COMPANION's Alpine template and not the base package's Luma one. Both render a
 * ticket form, both submit through the same GraphQL mutation, and both look broadly
 * right in a screenshot — so every S-assertion below would pass against the WRONG
 * template. Establish which one is on the page before believing anything else about it.
 */

const CUSTOMER = () => {
    const user = process.env.WALK_CUSTOMER_USER;
    const pass = process.env.WALK_CUSTOMER_PASS;
    if (!user || !pass) {
        throw new Error('WALK_CUSTOMER_USER and WALK_CUSTOMER_PASS must be set - this spec '
            + 'must not skip into a pass');
    }
    return { user, pass };
};

/** The ticket form itself. A bare button[type="submit"] matches the header search. */
function ticketForm(page: Page) {
    return page.locator('form').filter({ has: page.locator('#jira-ticket-summary') }).first();
}

async function signIn(page: Page): Promise<void> {
    const { user, pass } = CUSTOMER();
    await page.goto('/customer/account/login/', { waitUntil: 'domcontentloaded' });
    await page.locator('#email, input[name="login[username]"]').first().fill(user);
    await page.locator('#pass, input[name="login[password]"]').first().fill(pass);

    // SCOPE THE SUBMIT TO THE LOGIN FORM. `button[type="submit"]` matches the header
    // search button first on this theme, which is present and permanently hidden, so
    // .first().click() times out against an element the log says it resolved - and
    // the screenshot shows a correctly filled form, which reads as a product fault.
    const loginForm = page.locator('form').filter({ has: page.locator('#pass') }).first();
    await loginForm.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    await expect(
        page.locator('body'),
        'sign-in did not reach the account area'
    ).not.toContainText(/sign-in was incorrect|account is disabled/i, { timeout: 20_000 });
}

test.describe('S1-S7 on Hyva, against the split packages', () => {
    test.setTimeout(300_000);

    test('S0 CONTROL: the page renders the COMPANION template, not the base Luma one', async ({ page }) => {
        await signIn(page);
        await page.goto('/jira/ticket/new/', { waitUntil: 'domcontentloaded' });

        const html = await page.content();

        // The companion's markers.
        expect(html, 'no Alpine x-data on the page - the BASE Luma template is rendering, '
            + 'so the companion override did not apply and every assertion below would be '
            + 'measuring the wrong package').toContain('x-data');
        expect(html).toContain('initJiraTicketForm');

        // The base package's Luma markers must be ABSENT, or BOTH rendered.
        expect(html, 'the Luma template id is present too - both templates rendered')
            .not.toContain('id="jira-ticket-form-wrapper"');
    });

    test('S1/S2: the account nav reaches the form and the form renders its fields', async ({ page }) => {
        await signIn(page);
        await page.goto('/customer/account/', { waitUntil: 'domcontentloaded' });

        const link = page.locator('a[href*="jira/ticket/new"]').first();
        await expect(link, 'no "Submit a Ticket" link in the account navigation').toHaveCount(1);
        await link.click();
        await page.waitForLoadState('domcontentloaded');

        for (const [id, what] of [
            ['#jira-ticket-type', 'ticket type'],
            ['#jira-ticket-summary', 'summary'],
            ['#jira-ticket-description', 'description'],
        ] as const) {
            await expect(page.locator(id), `${what} field missing`).toBeVisible({ timeout: 20_000 });
        }
        // Scoped to the ticket form: the bare selector matches the header search button,
        // which is present and permanently hidden on this theme.
        await expect(
            ticketForm(page).locator('button[type="submit"]').first(),
            'no submit button on the ticket form'
        ).toBeVisible();
    });

    test('S3/S5/S6: submit, then see it listed with its LABEL', async ({ page }) => {
        await signIn(page);
        await page.goto('/jira/ticket/new/', { waitUntil: 'domcontentloaded' });

        // WAIT FOR ALPINE TO FINISH init(). The component's x-init runs loadOrders(),
        // which awaits a GraphQL round trip; submitting before it settles leaves the
        // form interactive but the component half-built, and the success state never
        // renders. The first run of this spec did exactly that and reported "no success
        // state after submitting" against a submission that had reached Jira and been
        // given a key. The order picker losing its "Loading orders…" note is the signal
        // that init() has completed.
        await expect(
            page.locator('#jira-ticket-order'),
            'the order picker never rendered, so Alpine init() did not complete'
        ).toBeVisible({ timeout: 30_000 });
        await expect(
            page.locator('text=Loading orders'),
            'init() is still in flight'
        ).toHaveCount(0, { timeout: 30_000 });

        const stamp = Date.now();
        const summary = `UAT hyva split ${stamp}`;

        await page.locator('#jira-ticket-summary').fill(summary);
        await page.locator('#jira-ticket-description').fill('Filed by the Hyva half of the S1-S7 walk after the package split.');
        await ticketForm(page).locator('button[type="submit"]').first().click();

        // S3 - the customer is told it was received.
        // .first() IS LOAD-BEARING. A comma selector that resolves to two elements -
        // the success block and the <p> inside it - is a Playwright STRICT MODE
        // violation, which fails IMMEDIATELY rather than after the timeout. The run
        // took 19s against a 40s wait and read as "the success state never appeared",
        // when the real message was "your selector is ambiguous".
        await expect(
            page.locator('[x-text="successMessage"]').first(),
            'no success state after submitting'
        ).toBeVisible({ timeout: 40_000 });

        // S5 - it appears in My Tickets.
        await page.goto('/jira/account/tickets/', { waitUntil: 'domcontentloaded' });
        await expect(
            page.locator('table'),
            'My Tickets rendered no table'
        ).toBeVisible({ timeout: 40_000 });
        await expect(
            page.locator('table'),
            'the submitted ticket is not listed'
        ).toContainText(summary, { timeout: 20_000 });

        // S6 - the STATUS COLUMN shows the label, not the stored value. `pending`,
        // `sent` and `failed` are storage; the customer must never see them.
        const table = (await page.locator('table').first().innerText()).replace(/\s+/g, ' ');
        for (const raw of ['pending', 'sent', 'failed', 'retrying']) {
            expect(
                table.split(' ').includes(raw),
                `the raw stored status "${raw}" is rendered to the customer - S6 requires the label`
            ).toBe(false);
        }
        test.info().annotations.push({ type: 'S6 table text', description: table.slice(0, 300) });
    });
});
