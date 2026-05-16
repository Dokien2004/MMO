/**
 * Instagram Upload — Playwright automation.
 *
 * Usage: node instagram_upload.js <payload.json>
 *
 * Payload JSON:
 *   { cookies, username, media_path, media_type, caption, title }
 *
 * media_type: 'image' | 'video'
 * caption: post caption (up to 2200 chars)
 *
 * Output: JSON { success, message, post_url?, media_id? }
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

    if (!cookies || !media_path) {
        output({ success: false, message: 'Missing cookies or media_path.' });
        process.exit(1);
    }

    if (!fs.existsSync(media_path)) {
        output({ success: false, message: `Media file not found: ${media_path}` });
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

        // Load cookies from session storage format or raw JSON
        let formattedCookies;
        if (typeof cookies === 'string') {
            try {
                const parsed = JSON.parse(cookies);
                formattedCookies = Array.isArray(parsed) ? parsed : Object.entries(parsed).map(([name, value]) => ({
                    name,
                    value,
                    domain: '.instagram.com',
                    path: '/',
                    httpOnly: false,
                    secure: true,
                    sameSite: 'None',
                }));
            } catch {
                // Assume ini-style cookies: "name=value; name2=value2"
                formattedCookies = cookies.split(';').map(cookie => {
                    const [name, ...valueParts] = cookie.trim().split('=');
                    return {
                        name: name.trim(),
                        value: valueParts.join('=').trim(),
                        domain: '.instagram.com',
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
                domain: c.domain || '.instagram.com',
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

        // Go to Instagram Creator Studio or direct upload
        await page.goto('https://www.instagram.com/', {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        });
        await page.waitForTimeout(3000);

        // Check if logged in
        const loginForm = await page.$('input[name="username"], input[placeholder*="username" i]');
        if (loginForm) {
            output({ success: false, message: 'Instagram session expired. Please re-export cookies.' });
            process.exit(1);
        }

        // Navigate to create post page
        await page.goto('https://www.instagram.com/create/story/', {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        });
        await page.waitForTimeout(2000);

        // Try new post page (Instagram updated URL)
        const uploadSuccess = await tryUpload(page, media_path, media_type);
        if (!uploadSuccess) {
            // Try alternative: Creator Studio
            await page.goto('https://business.instagram.com/creator/studio', {
                waitUntil: 'domcontentloaded',
                timeout: 30000,
            });
            await page.waitForTimeout(2000);
        }

        // Find file input
        const fileInput = await page.$('input[type="file"][accept*="image"], input[type="file"][accept*="video"], input[type="file"]');
        if (!fileInput) {
            // Try clicking create button first
            const createBtn = await page.$('svg[aria-label*="New post"], a[href*="/create"]');
            if (createBtn) {
                await createBtn.click();
                await page.waitForTimeout(2000);
            }
        }

        const success = await tryUpload(page, media_path, media_type);
        if (!success) {
            output({ success: false, message: 'Could not find file input on Instagram. Page structure may have changed.' });
            process.exit(1);
        }

        // Wait for upload to complete
        await page.waitForTimeout(5000);

        // Try to add caption (Instagram has various upload flows)
        try {
            const captionInput = await page.$('textarea[placeholder*="caption" i], textarea[placeholder*="Write" i], div[contenteditable="true"][aria-label*="caption" i]');
            if (captionInput) {
                await captionInput.click();
                await page.keyboard.type(caption || title || '', { delay: 50 });
            }
        } catch {
            // Caption step may not be available in all upload flows
        }

        // Look for share/post button
        const postButtonSelectors = [
            'button[type="submit"]',
            'div[role="button"]:has-text("Share")',
            'button:has-text("Share")',
            'button:has-text("Post")',
            'div[aria-label="Share"]:not([role])',
        ];

        let posted = false;
        for (const selector of postButtonSelectors) {
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

        output({
            success: posted,
            message: posted ? 'Đã đăng bài lên Instagram thành công.' : 'Upload thành công nhưng không tìm thấy nút đăng. Vui lòng kiểm tra thủ công.',
            post_url: '',
            media_id: '',
        });

    } catch (err) {
        output({ success: false, message: 'Instagram upload failed: ' + err.message });
    } finally {
        await browser.close();
    }
}

async function tryUpload(page, mediaPath, mediaType) {
    const acceptAttr = mediaType === 'video'
        ? 'video/*,video/mp4'
        : 'image/*,image/jpeg,image/png,image/gif';

    const fileInput = await page.$('input[type="file"]');
    if (fileInput) {
        try {
            // Instagram may use hidden input
            await fileInput.setInputFiles(mediaPath);
            return true;
        } catch (e) {
            // Try direct page navigation approach
            return false;
        }
    }
    return false;
}

function output(data) {
    process.stdout.write(JSON.stringify(data, null, 0));
}

main().catch(err => {
    output({ success: false, message: 'Unhandled error: ' + err.message });
    process.exit(1);
});