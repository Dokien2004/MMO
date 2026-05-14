/**
 * Facebook Group Post — Playwright automation.
 *
 * Usage: node fb_group_post.js <payload.json>
 *
 * Payload JSON:
 *   { group_id, cookies, message, media_path?, media_type? }
 *
 * Output: JSON { success, message, post_url?, post_id? }
 */

const fs = require('fs');
const path = require('path');

async function main() {
    const payloadFile = process.argv[2];
    if (!payloadFile || !fs.existsSync(payloadFile)) {
        output({ success: false, message: 'Payload file not found.' });
        process.exit(1);
    }

    const payload = JSON.parse(fs.readFileSync(payloadFile, 'utf-8'));
    const { group_id, cookies, message, media_path, media_type } = payload;

    if (!group_id || !cookies || !message) {
        output({ success: false, message: 'Missing group_id, cookies or message.' });
        process.exit(1);
    }

    let playwright;
    try {
        playwright = require('playwright');
    } catch {
        output({ success: false, message: 'Playwright not installed. Run: npm install playwright' });
        process.exit(1);
    }

    const browser = await playwright.chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    try {
        const context = await browser.newContext({
            viewport: { width: 1280, height: 800 },
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        });

        // Load cookies
        const parsedCookies = typeof cookies === 'string' ? JSON.parse(cookies) : cookies;
        const formattedCookies = parsedCookies.map(c => ({
            name: c.name,
            value: c.value,
            domain: c.domain || '.facebook.com',
            path: c.path || '/',
            httpOnly: c.httpOnly ?? false,
            secure: c.secure ?? true,
            sameSite: c.sameSite || 'None',
        }));
        await context.addCookies(formattedCookies);

        const page = await context.newPage();

        // Navigate to group
        const groupUrl = `https://www.facebook.com/groups/${group_id}/`;
        await page.goto(groupUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(3000);

        // Check if logged in
        const loginBtn = await page.$('a[href*="/login"]');
        if (loginBtn) {
            output({ success: false, message: 'Cookie session expired. Please re-export cookies.' });
            process.exit(1);
        }

        // Click on the "Write something..." / "Viết gì đó..." box
        const createPostSelectors = [
            '[aria-label="Write something..."]',
            '[aria-label="Viết gì đó..."]',
            '[role="button"][tabindex="0"] span:has-text("Write something")',
            '[role="button"][tabindex="0"] span:has-text("Viết gì đó")',
            'div[data-pagelet="GroupFeed"] [role="button"]:has-text("Write")',
        ];

        let clicked = false;
        for (const selector of createPostSelectors) {
            try {
                await page.click(selector, { timeout: 5000 });
                clicked = true;
                break;
            } catch { /* try next selector */ }
        }

        if (!clicked) {
            output({ success: false, message: 'Cannot find "Write something" button. Group may require approval or layout changed.' });
            process.exit(1);
        }

        await page.waitForTimeout(2000);

        // Type message
        const editorSelectors = [
            '[contenteditable="true"][aria-label*="post"]',
            '[contenteditable="true"][aria-label*="bài"]',
            '[contenteditable="true"][role="textbox"]',
            'div[contenteditable="true"]',
        ];

        let typed = false;
        for (const selector of editorSelectors) {
            try {
                await page.click(selector, { timeout: 3000 });
                await page.keyboard.type(message, { delay: 20 });
                typed = true;
                break;
            } catch { /* try next */ }
        }

        if (!typed) {
            output({ success: false, message: 'Cannot find text editor in post dialog.' });
            process.exit(1);
        }

        // Upload media if available
        if (media_path && fs.existsSync(media_path)) {
            try {
                const photoVideoBtn = await page.$('div[aria-label*="Photo"] button, div[aria-label*="Ảnh"] button, [aria-label*="photo/video"]');
                if (photoVideoBtn) {
                    await photoVideoBtn.click();
                    await page.waitForTimeout(1000);
                }
                const fileInput = await page.$('input[type="file"][accept*="image"],input[type="file"][accept*="video"]');
                if (fileInput) {
                    await fileInput.setInputFiles(media_path);
                    await page.waitForTimeout(3000);
                }
            } catch (err) {
                // Continue without media
                console.error('Media upload warning:', err.message);
            }
        }

        // Click Post button
        const postBtnSelectors = [
            '[aria-label="Post"]',
            '[aria-label="Đăng"]',
            'div[role="dialog"] div[role="button"]:has-text("Post")',
            'div[role="dialog"] div[role="button"]:has-text("Đăng")',
        ];

        let posted = false;
        await page.waitForTimeout(1000);
        for (const selector of postBtnSelectors) {
            try {
                await page.click(selector, { timeout: 5000 });
                posted = true;
                break;
            } catch { /* try next */ }
        }

        if (!posted) {
            output({ success: false, message: 'Cannot find Post button. Post may not have been submitted.' });
            process.exit(1);
        }

        // Wait for post to be published
        await page.waitForTimeout(5000);

        output({
            success: true,
            message: `Đã đăng bài vào Facebook Group ${group_id} thành công.`,
            post_url: groupUrl,
        });

    } catch (err) {
        output({ success: false, message: err.message || 'Unknown error' });
        process.exit(1);
    } finally {
        await browser.close();
    }
}

function output(data) {
    process.stdout.write(JSON.stringify(data));
}

main().catch(err => {
    output({ success: false, message: err.message });
    process.exit(1);
});
