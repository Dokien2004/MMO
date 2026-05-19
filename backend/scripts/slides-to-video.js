/**
 * Combine slides into MP4 slideshow video
 * 
 * Usage: node slides-to-video.js <output.mp4> [fps]
 * 
 * Output: MP4 video 1080x1920 @ 1fps (1 slide per second)
 */

const { execSync } = require('child_process');
const path = require('path');

const SLIDES_DIR = path.join(__dirname, '../storage/slides');
const fps = parseInt(process.argv[3] || '1');
const outputPath = process.argv[2] || path.join(SLIDES_DIR, 'slideshow.mp4');

// Get all slide PNGs sorted
const slides = require('fs').readdirSync(SLIDES_DIR)
    .filter(f => f.match(/^slide_\d+\.png$/))
    .sort()
    .map(f => path.join(SLIDES_DIR, f));

if (slides.length === 0) {
    console.error('No slides found in', SLIDES_DIR);
    process.exit(1);
}

console.log(`Creating slideshow from ${slides.length} slides...`);
console.log(`FPS: ${fps} (${fps} second(s) per slide)`);

// Create concat list for ffmpeg
const listPath = '/tmp/slides_list.txt';
require('fs').writeFileSync(listPath, slides.map(s => `file '${s}'`).join('\n'));

const cmd = [
    'ffmpeg -y -f concat -safe 0',
    `-i "${listPath}"`,
    '-vf scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2',
    '-r', fps,
    '-c:v libx264 -preset fast -crf 23',
    `-pix_fmt yuv420p "${outputPath}"`
].join(' ');

try {
    execSync(cmd, { stdio: 'inherit' });
    console.log(`\n✓ Video created: ${outputPath}`);
    
    // Get file size
    const size = require('fs').statSync(outputPath).size;
    console.log(`  Size: ${(size / 1024 / 1024).toFixed(2)} MB`);
} catch (e) {
    console.error('Error:', e.message);
}

// Cleanup
require('fs').unlinkSync(listPath);