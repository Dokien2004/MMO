/**
 * TikTok Slideshow Generator
 * 
 * Usage: node generate-slideshow.js <content_json>
 * 
 * Content JSON structure:
 * {
 *   "slides": [
 *     {
 *       "imageUrl": "https://...",
 *       "lines": [
 *         { "text": "Da tôi bị phá hủy suốt 3 năm", "size": 88, "weight": "bold", "y": 860, "color": "#FFFFFF" },
 *         { "text": "cho đến khi tôi tìm thấy cái này", "size": 72, "weight": "normal", "y": 970, "color": "#FFFFFF" }
 *       ]
 *     }
 *   ]
 * }
 * 
 * Output: output/slide_01.png ... slide_0N.png (1080x1920)
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const OUTPUT_DIR = path.join(__dirname, '../storage/slides');
const WIDTH = 1080;
const HEIGHT = 1920;

// Ensure output directory exists
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

function downloadImage(url, destPath) {
    const cmd = `curl -s -L "${url}" -o "${destPath}" --max-time 30`;
    try {
        execSync(cmd, { stdio: 'pipe' });
        return fs.existsSync(destPath) && fs.statSync(destPath).size > 0;
    } catch {
        return false;
    }
}

function createSlide(imagePath, lines, slideNum) {
    const paddedNum = String(slideNum).padStart(2, '0');
    const outputPath = path.join(OUTPUT_DIR, `slide_${paddedNum}.png`);
    
    // Build ImageMagick command for text overlay
    let cmd = `convert -size ${WIDTH}x${HEIGHT} xc:"#1a1a2e"`;
    
    // Add background image if exists
    if (imagePath && fs.existsSync(imagePath)) {
        // Resize and crop to fill 1080x1920
        cmd += ` \( "${imagePath}" -resize "${WIDTH}x${HEIGHT}^" -gravity center -extent ${WIDTH}x${HEIGHT} \)`;
        cmd += ` -composite`;
    }
    
    // Add gradient overlay for readability
    cmd += ` -fill "rgba(26,26,46,0.4)" -draw "rectangle 0,600 1080,1920"`;
    
    // Add text lines
    for (const line of lines) {
        const size = line.size || 64;
        const weight = line.weight === 'bold' ? 'bold' : 'normal';
        const y = line.y || 860;
        const color = line.color || '#FFFFFF';
        const text = line.text.replace(/"/g, '\\"');
        
        // Convert to Vietnamese friendly font
        cmd += ` -fill "${color}" -font "DejaVu-Sans-${weight}" -pointsize ${size} -gravity center`;
        cmd += ` -draw "text 0,${y - size/2} '${text}'"`;
    }
    
    cmd += ` "${outputPath}"`;
    
    try {
        execSync(cmd, { stdio: 'pipe' });
        console.log(`✓ Created ${outputPath}`);
        return outputPath;
    } catch (e) {
        console.error(`✗ Error creating slide: ${e.message}`);
        return null;
    }
}

function createSlidesFromJson(jsonPath) {
    if (!fs.existsSync(jsonPath)) {
        console.error('JSON file not found:', jsonPath);
        process.exit(1);
    }
    
    const content = JSON.parse(fs.readFileSync(jsonPath, 'utf-8'));
    const slides = content.slides || [];
    
    if (slides.length === 0) {
        console.error('No slides found in JSON');
        process.exit(1);
    }
    
    console.log(`Creating ${slides.length} slides...`);
    
    const results = [];
    for (let i = 0; i < slides.length; i++) {
        const slide = slides[i];
        const slideNum = i + 1;
        
        // Download background image if URL
        let imagePath = slide.imagePath || slide.imageUrl;
        
        if (slide.imageUrl) {
            const tmpPath = path.join('/tmp', `slide_bg_${slideNum}.jpg`);
            const downloaded = downloadImage(slide.imageUrl, tmpPath);
            if (downloaded) {
                imagePath = tmpPath;
            }
        }
        
        const result = createSlide(imagePath, slide.lines || [], slideNum);
        if (result) results.push(result);
    }
    
    console.log(`\n✓ Created ${results.length} slides in ${OUTPUT_DIR}`);
    return results;
}

// Main
const jsonArg = process.argv[2];
if (!jsonArg) {
    // Demo with sample content
    console.log('Running demo with sample content...');
    
    const demoContent = {
        slides: [
            {
                imagePath: null,
                lines: [
                    { text: "Da tôi bị phá hủy suốt 3 năm", size: 88, weight: 'bold', y: 860 },
                    { text: "cho đến khi tôi tìm thấy cái này", size: 72, weight: 'normal', y: 970 }
                ]
            },
            {
                imagePath: null,
                lines: [
                    { text: "Đây là routine mà tôi áp dụng", size: 80, weight: 'bold', y: 860 },
                    { text: "và kết quả thì các bạn thấy đấy", size: 64, weight: 'normal', y: 970 }
                ]
            },
            {
                imagePath: null,
                lines: [
                    { text: "Bước 1: Rửa mặt nhẹ nhàng", size: 72, weight: 'bold', y: 860 },
                    { text: "không chà xát mạnh", size: 56, weight: 'normal', y: 970 }
                ]
            },
            {
                imagePath: null,
                lines: [
                    { text: "Bước 2: Dùng toner cân bằng", size: 72, weight: 'bold', y: 860 },
                    { text: "thoa đều toàn mặt", size: 56, weight: 'normal', y: 970 }
                ]
            },
            {
                imagePath: null,
                lines: [
                    { text: "Bước 3: Serum Vitamin C", size: 72, weight: 'bold', y: 860 },
                    { text: "tan vào da ngay lập tức", size: 56, weight: 'normal', y: 970 }
                ]
            },
            {
                imagePath: null,
                lines: [
                    { text: "Comment SKINCARE", size: 80, weight: 'bold', y: 860 },
                    { text: "Tôi sẽ gửi bạn routine đầy đủ", size: 52, weight: 'normal', y: 970 }
                ]
            }
        ]
    };
    
    // Create demo slides without background images
    for (let i = 0; i < demoContent.slides.length; i++) {
        createSlide(null, demoContent.slides[i].lines, i + 1);
    }
    
    console.log('\nDemo complete! Check output/slide_*.png');
} else {
    createSlidesFromJson(jsonArg);
}