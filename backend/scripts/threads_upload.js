/**
 * Threads Upload — Playwright automation.
 *
 * Usage: node threads_upload.js <payload.json>
 *
 * Payload JSON:
 *   { cookies, username, media_path, media_type, caption, title }
 *
 * media_type: 'image' | 'video' | 'none'
 * Threads text posts supported (media_path optional)
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
    const { cookies, username, media_path, media_type, caption, title } = payload;

    if (!cookies) {
        output({ success: false, message: 'Missing cookies.' });
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

        // Format cookies (Threads uses Meta accounts, same cookie structure as Facebook)
        let formattedCookies;
        if (typeof cookies === 'string') {
            try {
                const parsed = JSON.parse(cookies);
                formattedCookies = Array.isArray(parsed) ? parsed : Object.entries(parsed).map(([name, value]) => ({
                    name,
                    value,
                    domain: '.threads.net',
                    path: '/',
                    httpOnly: false,
                    secure: true,
                    sameSite: 'None',
                }));
            } catch {
                formattedCookies = cookies.split(';').map(cookie => {
                    const [name, ...valueParts] = cookie.trim().split('=');
                    return {
                        name: name.trim(),
                        value: valueParts.join('=').trim(),
                        domain: '.threads.net',
                        path: '/',
                        httpOnly: false,
                        secure: true,
                        sameSite: 'Lax',
                    };
                });
            }
        } else if (Array.isArray(cookies)) {
            formattedCookies = cookies.map(c => ({
                name: c.name,
                value: c.value,
                domain: c.domain || '.threads.net',
                path: c.path || '/',
                httpOnly: c.httpOnly ?? false,
                secure: c.secure ?? true,
                sameSite: c.sameSite || 'None',
            }));
        } else {
            output({ success: false, message: 'Invalid cookies format.' });
            process.exit(1);
        }
        await context.addCookies(formattedCookies);

        const page = await context.newPage();

        // Navigate to Threads.net
        await page.goto('https://www.threads.net/', {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        });
        await page.waitForTimeout(3000);

        // Check if logged in (Threads uses Meta auth)
        const loginIndicator = await page.$('a[href*="/login"], button:has-text("Log in")');
        if (loginIndicator) {
            output({ success: false, message: 'Threads session expired. Please re-export cookies from your Meta account.' });
            process.exit(1);
        }

        // Look for create new post button
        const createSelectors = [
            'a[href*="/compose"]',
            'button[aria-label*="New thread" i]',
            'div[aria-label="New post"], svg[aria-label*="New post" i]',
            'button:has-text("New post")',
            'a[href*="create"]',
        ];

        let foundCreateBtn = null;
        for (const selector of createSelectors) {
            const btn = await page.$(selector);
            if (btn && await btn.isVisible()) {
                foundCreateBtn = btn;
                break;
            }
        }

        if (!foundCreateBtn) {
            output({ success: false, message: 'Could not find create post button on Threads. Page structure may have changed.' });
            process.exit(1);
        }

        await foundCreateBtn.click();
        await page.waitForTimeout(2000);

        // Try to type text/caption first
        const textSelectors = [
            'div[contenteditable="true"][aria-label*="thread" i]',
            'div[contenteditable="true"][aria-label*="caption" i]',
            'div[contenteditable="true"]',
            'textarea[placeholder*="thread" i]',
            'textarea[placeholder*="caption" i]',
        ];

        for (const selector of textSelectors) {
            const textInput = await page.$(selector);
            if (textInput && await textInput.isVisible()) {
                await textInput.click();
                await page.keyboard.type(caption || title || '', { delay: 30 });
                await page.waitForTimeout(500);
                break;
            }
        }

        // Upload media if provided
        if (media_path && media_type !== 'none' && fs.existsSync(media_path)) {
            const acceptAttr = media_type === 'video' ? 'video/*' : 'image/*';
            const fileInput = await page.$('input[type="file"]');
            if (fileInput) {
                try {
                    await fileInput.setInputFiles(media_path);
                    await page.waitForTimeout(3000);
                } catch (e) {
                    // Media upload may fail silently
                }
            }
        }

        // Look for post/share button
        const postSelectors = [
            'button[type="submit"]',
            'div[role="button"]:has-text("Post")',
            'button:has-text("Post")',
            'div[aria-label="Post"]',
            'button:has-text("Publish")',
        ];

        let posted = false;
        for (const selector of postSelectors) {
            try {
                const btn = await page.$(selector);
                if (btn && await btn.isVisible()) {
                    await btn.click();
                    posted = true;
                    break;
                }
            } catch {}
        }

        await page.waitForTimeout(3000);

        // Get post URL if possible
        let postUrl = '';
        try {
            const url = page.url();
            if (url.includes('/thread/')) {
                postUrl = url;
            }
        } catch {}

        output({
            success: posted,
            message: posted
                ? 'Đã đăng bài lên Threads thành công.'
                : 'Đã chuẩn bị nội dung nhưng không tìm thấy nút đăng. Vui lòng kiểm tra thủ công.',
            post_url: postUrl,
            post_id: '',
        });

    } catch (err) {
        output({ success: false, message: 'Threads upload failed: ' + err.message });
    } finally {
        await browser.close();
    }
}

function output(data) {
    process.stdout.write(JSON.stringify(data, null, 0));
}

main().catch(err => {
    output({ success: false, message: 'Unhandled error: ' + err.message });
    process.exit(1);
});