/**
 * TikTok Video Upload — Playwright automation.
 *
 * Usage: node tiktok_upload.js <payload.json>
 *
 * Payload JSON:
 *   { cookies, video_path, caption, username? }
 *
 * Output: JSON { success, message, video_url?, video_id? }
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
    const { cookies, video_path, caption, username } = payload;

    if (!cookies || !video_path) {
        output({ success: false, message: 'Missing cookies or video_path.' });
        process.exit(1);
    }

    if (!fs.existsSync(video_path)) {
        output({ success: false, message: `Video file not found: ${video_path}` });
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
            domain: c.domain || '.tiktok.com',
            path: c.path || '/',
            httpOnly: c.httpOnly ?? false,
            secure: c.secure ?? true,
            sameSite: c.sameSite || 'None',
        }));
        await context.addCookies(formattedCookies);

        const page = await context.newPage();

        // Navigate to TikTok Creator Center upload page
        await page.goto('https://www.tiktok.com/creator-center/upload', {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        });
        await page.waitForTimeout(3000);

        // Check login state
        const loginIndicator = await page.$('a[href*="/login"]');
        if (loginIndicator) {
            output({ success: false, message: 'TikTok cookie session expired. Please re-export cookies.' });
            process.exit(1);
        }

        // Try iframe-based upload (TikTok Studio)
        const iframe = await page.$('iframe[src*="upload"]');
        let uploadPage = page;
        if (iframe) {
            const frame = await iframe.contentFrame();
            if (frame) uploadPage = frame;
        }

        // Upload video file
        const fileInputSelectors = [
            'input[type="file"][accept*="video"]',
            'input[type="file"]',
        ];

        let uploaded = false;
        for (const selector of fileInputSelectors) {
            try {
                const input = await uploadPage.$(selector);
                if (input) {
                    await input.setInputFiles(video_path);
                    uploaded = true;
                    break;
                }
            } catch { /* try next */ }
        }

        if (!uploaded) {
            output({ success: false, message: 'Cannot find file upload input on TikTok Creator page.' });
            process.exit(1);
        }

        // Wait for upload to process
        await page.waitForTimeout(5000);

        // Fill caption
        if (caption) {
            const captionSelectors = [
                '[contenteditable="true"][data-placeholder]',
                '[contenteditable="true"]',
                'div[class*="caption"] [contenteditable]',
            ];

            for (const selector of captionSelectors) {
                try {
                    const editor = await uploadPage.$(selector);
                    if (editor) {
                        await editor.click();
                        // Clear existing text
                        await uploadPage.keyboard.press('Control+A');
                        await uploadPage.keyboard.type(caption, { delay: 15 });
                        break;
                    }
                } catch { /* try next */ }
            }
        }

        // Wait for video processing
        await page.waitForTimeout(10000);

        // Click Post button
        const postBtnSelectors = [
            'button:has-text("Post")',
            'button:has-text("Đăng")',
            '[data-e2e="post-button"]',
            'button[class*="post"]',
        ];

        let posted = false;
        for (const selector of postBtnSelectors) {
            try {
                const btn = await uploadPage.$(selector);
                if (btn && (await btn.isEnabled())) {
                    await btn.click();
                    posted = true;
                    break;
                }
            } catch { /* try next */ }
        }

        if (!posted) {
            output({ success: false, message: 'Cannot find or click Post button. Video may still be processing.' });
            process.exit(1);
        }

        // Wait for post confirmation
        await page.waitForTimeout(8000);

        output({
            success: true,
            message: `Đã upload video lên TikTok${username ? ' (@' + username + ')' : ''} thành công.`,
            video_url: username ? `https://www.tiktok.com/@${username}` : '',
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
